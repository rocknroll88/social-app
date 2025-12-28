<?php

namespace App\WebSocket;

use App\Repositories\UserRepository;
use App\Services\WebSocketConnectionManager;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use React\EventLoop\Loop;

class WebSocketServer implements MessageComponentInterface
{
    private const EXCHANGE = 'websocket_notifications';
    private WebSocketConnectionManager $connectionManager;
    private UserRepository $userRepository;
    private array $connections = [];
    private AMQPStreamConnection $amqpConnection;
    private $amqpChannel;

    public function __construct(
        WebSocketConnectionManager $connectionManager,
        UserRepository $userRepository
    ) {
        $this->connectionManager = $connectionManager;
        $this->userRepository = $userRepository;
    }

    public function start(string $host = '0.0.0.0', int $port = 8090): void
    {
        echo "WebSocket server started on ws://$host:$port\n";
        echo "Subscribe to /post/feed/posted for friend posts updates\n";
        
        // Инициализируем RabbitMQ
        $this->initRabbitMQ();

        $server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $port,
            $host
        );

        // Подписываемся на RabbitMQ сообщения
        $this->subscribeToRabbitMQ();

        $server->run();
    }
    
    private function initRabbitMQ(): void
    {
        $this->amqpConnection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_LOGIN', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            env('RABBITMQ_VHOST', '/')
        );
        
        $this->amqpChannel = $this->amqpConnection->channel();
        
        // Объявляем exchange
        $this->amqpChannel->exchange_declare(
            self::EXCHANGE,
            'topic',
            false,
            true,  // durable
            false
        );
        
        echo "RabbitMQ connection established\n";
    }
    
    private function subscribeToRabbitMQ(): void
    {
        $loop = Loop::get();
        
        // Периодически проверяем новые сообщения из RabbitMQ
        $loop->addPeriodicTimer(0.1, function() {
            // Получаем сообщения из всех пользовательских очередей
            foreach ($this->connections as $resourceId => $connData) {
                if (!$connData['authenticated'] || !$connData['user_id']) {
                    continue;
                }
                
                $userId = $connData['user_id'];
                $queueName = "user_{$userId}_notifications";
                $routingKey = "user.{$userId}.notification";
                
                // Объявляем очередь для пользователя (если еще не существует)
                $this->amqpChannel->queue_declare(
                    $queueName,
                    false,
                    true,  // durable
                    false,
                    false
                );
                
                // Привязываем очередь к exchange с routing key
                $this->amqpChannel->queue_bind(
                    $queueName,
                    self::EXCHANGE,
                    $routingKey
                );
                
                // Пытаемся получить одно сообщение
                $message = $this->amqpChannel->basic_get($queueName, true); // auto_ack = true
                
                if ($message) {
                    $this->handleRabbitMQMessage($userId, $message->body);
                }
            }
        });
    }
    
    private function handleRabbitMQMessage(string $userId, string $payload): void
    {
        try {
            $data = json_decode($payload, true);
            
            if (!$data) {
                return;
            }
            
            // Отправляем сообщение конкретному пользователю
            $this->connectionManager->sendToUser($userId, $data);
            
            echo "[" . date('H:i:s') . "] Sent notification to user: $userId\n";
        } catch (\Exception $e) {
            echo "Error handling RabbitMQ message: " . $e->getMessage() . "\n";
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        echo "New connection: {$conn->resourceId}\n";
        $this->connections[$conn->resourceId] = [
            'conn' => $conn,
            'user_id' => null,
            'authenticated' => false
        ];
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['action'])) {
                $from->send(json_encode([
                    'error' => 'Invalid message format'
                ]));
                return;
            }

            switch ($data['action']) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                
                case 'subscribe':
                    $this->handleSubscribe($from, $data);
                    break;
                
                default:
                    $from->send(json_encode([
                        'error' => 'Unknown action'
                    ]));
            }
        } catch (\Exception $e) {
            $from->send(json_encode([
                'error' => $e->getMessage()
            ]));
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        echo "Connection closed: {$conn->resourceId}\n";
        
        if (isset($this->connections[$conn->resourceId])) {
            $userId = $this->connections[$conn->resourceId]['user_id'];
            if ($userId) {
                $this->connectionManager->removeConnection($userId);
            }
            unset($this->connections[$conn->resourceId]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuth(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['token'])) {
            $conn->send(json_encode([
                'error' => 'Missing token'
            ]));
            return;
        }

        $user = $this->userRepository->findByToken($data['token']);
        
        if (!$user) {
            $conn->send(json_encode([
                'error' => 'Invalid token'
            ]));
            return;
        }

        $this->connections[$conn->resourceId]['user_id'] = $user->user_id;
        $this->connections[$conn->resourceId]['authenticated'] = true;
        
        // Store connection in manager
        $this->connectionManager->addConnection($user->user_id, $conn);

        $conn->send(json_encode([
            'action' => 'authenticated',
            'user_id' => $user->user_id
        ]));
        
        echo "User authenticated: {$user->user_id}\n";
    }

    private function handleSubscribe(ConnectionInterface $conn, array $data): void
    {
        if (!$this->connections[$conn->resourceId]['authenticated']) {
            $conn->send(json_encode([
                'error' => 'Not authenticated'
            ]));
            return;
        }

        if (!isset($data['channel'])) {
            $conn->send(json_encode([
                'error' => 'Missing channel'
            ]));
            return;
        }

        $conn->send(json_encode([
            'action' => 'subscribed',
            'channel' => $data['channel']
        ]));
        
        echo "User subscribed to: {$data['channel']}\n";
    }

    public function broadcastToUser(string $userId, array $message): void
    {
        $this->connectionManager->sendToUser($userId, $message);
    }

    public function broadcastToUsers(array $userIds, array $message): void
    {
        foreach ($userIds as $userId) {
            $this->broadcastToUser($userId, $message);
        }
    }
}
