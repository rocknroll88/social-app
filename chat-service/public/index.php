<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/JsonLogger.php';
require_once __DIR__ . '/../src/RequestId.php';
require_once __DIR__ . '/../src/DialogRepository.php';

use ChatService\DialogRepository;
use ChatService\JsonLogger;
use ChatService\RequestId;

$logger = new JsonLogger('chat-service');
$requestId = RequestId::resolve($_SERVER['HTTP_X_REQUEST_ID'] ?? null);
$startedAt = microtime(true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

header('Content-Type: application/json');
header('X-Request-Id: ' . $requestId);

$logger->log('http_request_started', [
    'request_id' => $requestId,
    'method' => $method,
    'path' => $path,
]);

[$statusCode, $payload] = handleRequest($method, $path, $requestId, $logger);

http_response_code($statusCode);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$logger->log('http_request_finished', [
    'request_id' => $requestId,
    'method' => $method,
    'path' => $path,
    'status' => $statusCode,
    'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
]);

/**
 * @return array{0:int,1:array<string,mixed>|array<int,mixed>}
 */
function handleRequest(string $method, string $path, string $requestId, JsonLogger $logger): array
{
    try {
        $repository = new DialogRepository(
            createPdo(),
            createRedis(),
            envValue('DIALOG_STORAGE', 'redis'),
            envValue('DIALOG_REDIS_PREFIX', 'dialog'),
            envValue('DIALOG_REDIS_MESSAGE_PREFIX', 'dialog:message:'),
            envValue('DIALOG_COUNTER_REDIS_PREFIX', 'dialog:counter'),
            (int) envValue('DIALOG_RECIPIENT_CACHE_TTL_SEC', '3600'),
            (int) envValue('DIALOG_COUNTER_CACHE_TTL_SEC', '3600')
        );

        if ($method === 'POST' && $path === '/internal/dialogs/send') {
            $payload = readJsonBody();
            $fromUserId = trim((string) ($payload['from_user_id'] ?? ''));
            $toUserId = trim((string) ($payload['to_user_id'] ?? ''));
            $text = trim((string) ($payload['text'] ?? ''));

            if ($fromUserId === '' || $toUserId === '' || $text === '') {
                return [400, ['error' => 'from_user_id, to_user_id and text are required']];
            }

            if ($fromUserId === $toUserId) {
                return [400, ['error' => 'Cannot send message to yourself']];
            }

            $messageId = $repository->sendMessage($fromUserId, $toUserId, $text);
            if ($messageId === null) {
                return [400, ['error' => 'Recipient not found']];
            }

            $logger->log('dialog_message_sent', [
                'request_id' => $requestId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'message_id' => $messageId,
            ]);

            return [200, ['message_id' => $messageId]];
        }

        if ($method === 'GET' && $path === '/internal/dialogs/list') {
            $userId1 = trim((string) ($_GET['user_id1'] ?? ''));
            $userId2 = trim((string) ($_GET['user_id2'] ?? ''));
            $limit = max(1, min((int) ($_GET['limit'] ?? 100), 500));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));

            if ($userId1 === '' || $userId2 === '') {
                return [400, ['error' => 'user_id1 and user_id2 are required']];
            }

            $messages = $repository->getDialog($userId1, $userId2, $limit, $offset);

            $logger->log('dialog_messages_listed', [
                'request_id' => $requestId,
                'user_id1' => $userId1,
                'user_id2' => $userId2,
                'limit' => $limit,
                'offset' => $offset,
                'message_count' => count($messages),
            ]);

            return [200, $messages];
        }

        if ($method === 'GET' && $path === '/internal/dialogs/counters') {
            $userId = trim((string) ($_GET['user_id'] ?? ''));
            $dialogUserId = trim((string) ($_GET['dialog_user_id'] ?? ''));

            if ($userId === '') {
                return [400, ['error' => 'user_id is required']];
            }

            $counters = $repository->getUnreadCounters($userId, $dialogUserId !== '' ? $dialogUserId : null);

            $logger->log('dialog_counters_listed', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'dialog_user_id' => $dialogUserId !== '' ? $dialogUserId : null,
                'total_unread' => $counters['total_unread'],
                'dialogs_count' => count($counters['dialogs']),
            ]);

            return [200, $counters];
        }

        if ($method === 'POST' && $path === '/internal/dialogs/read') {
            $payload = readJsonBody();
            $readerUserId = trim((string) ($payload['reader_user_id'] ?? ''));
            $dialogUserId = trim((string) ($payload['dialog_user_id'] ?? ''));

            if ($readerUserId === '' || $dialogUserId === '') {
                return [400, ['error' => 'reader_user_id and dialog_user_id are required']];
            }

            if ($readerUserId === $dialogUserId) {
                return [400, ['error' => 'Cannot mark self dialog as read']];
            }

            $result = $repository->markDialogAsRead($readerUserId, $dialogUserId);

            $logger->log('dialog_marked_as_read', [
                'request_id' => $requestId,
                'reader_user_id' => $readerUserId,
                'dialog_user_id' => $dialogUserId,
                'marked_as_read' => $result['marked_as_read'],
                'dialog_unread' => $result['dialog_unread'],
                'total_unread' => $result['total_unread'],
            ]);

            return [200, $result];
        }

        return [404, ['error' => 'Not found']];
    } catch (InvalidArgumentException $exception) {
        return [400, ['error' => $exception->getMessage()]];
    } catch (Throwable $exception) {
        $logger->log('unhandled_exception', [
            'request_id' => $requestId,
            'path' => $path,
            'error' => $exception->getMessage(),
        ]);

        return [500, ['error' => 'Internal server error']];
    }
}

/**
 * @return array<string, mixed>
 */
function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || $rawBody === '') {
        return [];
    }

    $decodedBody = json_decode($rawBody, true);
    if (!is_array($decodedBody)) {
        throw new InvalidArgumentException('Invalid JSON body');
    }

    return $decodedBody;
}

function createPdo(): PDO
{
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        envValue('DB_HOST', 'db'),
        envValue('DB_PORT', '5432'),
        envValue('DB_DATABASE', 'social')
    );

    return new PDO($dsn, envValue('DB_USERNAME', 'postgres'), envValue('DB_PASSWORD', 'secret'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function createRedis(): Redis
{
    $redis = new Redis();
    $redis->connect(envValue('REDIS_HOST', 'redis'), (int) envValue('REDIS_PORT', '6379'));

    $password = envValue('REDIS_PASSWORD', '');
    if ($password !== '') {
        $redis->auth($password);
    }

    return $redis;
}

function envValue(string $key, string $default): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}
