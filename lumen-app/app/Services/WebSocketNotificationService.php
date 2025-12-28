<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class WebSocketNotificationService
{
    private const EXCHANGE = 'websocket_notifications';
    private AMQPStreamConnection $connection;
    private $channel;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'rabbitmq'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_LOGIN', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            env('RABBITMQ_VHOST', '/')
        );
        
        $this->channel = $this->connection->channel();
        
        // Объявляем topic exchange для routing keys
        $this->channel->exchange_declare(
            self::EXCHANGE,
            'topic',
            false,
            true,  // durable
            false
        );
    }

    /**
     * Отправить уведомление о новом посте друзьям автора
     *
     * @param array $followerIds Список ID друзей
     * @param string $postId ID поста
     * @param string $postText Текст поста
     * @param string $authorUserId ID автора поста
     */
    public function notifyFollowersAboutNewPost(
        array $followerIds,
        string $postId,
        string $postText,
        string $authorUserId
    ): void {
        foreach ($followerIds as $userId) {
            $this->notifyUserAboutNewPost($userId, $postId, $postText, $authorUserId);
        }
    }

    /**
     * Отправить уведомление конкретному пользователю
     */
    public function notifyUser(string $userId, array $message): void
    {
        // Формируем routing key для конкретного пользователя
        $routingKey = "user.{$userId}.notification";
        
        $messageBody = json_encode($message);
        $amqpMessage = new AMQPMessage($messageBody, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        // Публикуем сообщение с routing key
        $this->channel->basic_publish($amqpMessage, self::EXCHANGE, $routingKey);
        
        echo "Published notification for user {$userId} with routing key: {$routingKey}\n";
    }

    /**
     * Отправить уведомление пользователю о новом посте друга
     *
     * @param string $userId ID пользователя
     * @param string $postId ID поста
     * @param string $postText Текст поста
     * @param string $authorUserId ID автора поста
     */
    public function notifyUserAboutNewPost(
        string $userId,
        string $postId,
        string $postText,
        string $authorUserId
    ): void {
        $message = [
            'channel' => '/post/feed/posted',
            'message' => [
                'id' => $postId,
                'text' => $postText,
                'author_user_id' => $authorUserId,
                'created_at' => date('c'),
            ],
        ];

        $this->notifyUser($userId, $message);
    }
    
    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
