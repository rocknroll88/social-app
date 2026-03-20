<?php

namespace App\Services;

use App\Exceptions\ChatServiceException;

class ChatServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly float $timeoutSec,
        private readonly RequestLogger $requestLogger
    ) {
    }

    public function sendMessage(string $requestId, string $fromUserId, string $toUserId, string $text): string
    {
        $responseBody = $this->request(
            $requestId,
            'POST',
            '/internal/dialogs/send',
            [
                'json' => [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'text' => $text,
                ],
            ]
        );

        $messageId = $responseBody['message_id'] ?? null;
        if (!is_string($messageId) || $messageId === '') {
            throw new ChatServiceException('Chat service returned invalid response', 503);
        }

        return $messageId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDialog(
        string $requestId,
        string $userId1,
        string $userId2,
        int $limit,
        int $offset
    ): array {
        $responseBody = $this->request(
            $requestId,
            'GET',
            '/internal/dialogs/list',
            [
                'query' => [
                    'user_id1' => $userId1,
                    'user_id2' => $userId2,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]
        );

        if (!is_array($responseBody)) {
            throw new ChatServiceException('Chat service returned invalid response', 503);
        }

        return $responseBody;
    }

    /**
     * @return array{total_unread:int,dialogs:array<int,array<string,mixed>>,consistency:string}
     */
    public function getUnreadCounters(string $requestId, string $userId, ?string $dialogUserId = null): array
    {
        $query = [
            'user_id' => $userId,
        ];

        if ($dialogUserId !== null && $dialogUserId !== '') {
            $query['dialog_user_id'] = $dialogUserId;
        }

        $responseBody = $this->request(
            $requestId,
            'GET',
            '/internal/dialogs/counters',
            [
                'query' => $query,
            ]
        );

        if (!is_array($responseBody)) {
            throw new ChatServiceException('Chat service returned invalid response', 503);
        }

        return $responseBody;
    }

    /**
     * @return array{marked_as_read:int,dialog_unread:int,total_unread:int,consistency:string}
     */
    public function markDialogAsRead(string $requestId, string $readerUserId, string $dialogUserId): array
    {
        $responseBody = $this->request(
            $requestId,
            'POST',
            '/internal/dialogs/read',
            [
                'json' => [
                    'reader_user_id' => $readerUserId,
                    'dialog_user_id' => $dialogUserId,
                ],
            ]
        );

        if (!is_array($responseBody)) {
            throw new ChatServiceException('Chat service returned invalid response', 503);
        }

        return $responseBody;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $requestId, string $method, string $uri, array $options): array
    {
        $startedAt = microtime(true);
        $headers = [
            'Accept: application/json',
            'X-Request-Id: ' . $requestId,
        ];

        $contextOptions = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'timeout' => $this->timeoutSec,
                'header' => implode("\r\n", $headers),
            ],
        ];

        if (isset($options['json']) && is_array($options['json'])) {
            $headers[] = 'Content-Type: application/json';
            $contextOptions['http']['header'] = implode("\r\n", $headers);
            $contextOptions['http']['content'] = (string) json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $url = $this->buildUrl($uri, $options['query'] ?? []);
        $context = stream_context_create($contextOptions);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];

        if ($body === false) {
            $this->requestLogger->log('chat_service_request_failed', [
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'error' => 'HTTP request failed',
            ]);

            throw new ChatServiceException('Chat service is unavailable', 503);
        }

        $statusCode = $this->extractStatusCode($responseHeaders);
        $decodedBody = json_decode($body, true);

        $this->requestLogger->log('chat_service_request_finished', [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'status' => $statusCode,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        if ($statusCode >= 200 && $statusCode < 300) {
            return is_array($decodedBody) ? $decodedBody : [];
        }

        $message = is_array($decodedBody) && isset($decodedBody['error']) && is_string($decodedBody['error'])
            ? $decodedBody['error']
            : 'Chat service request failed';

        if ($statusCode >= 400 && $statusCode < 500) {
            throw new ChatServiceException($message, $statusCode);
        }

        throw new ChatServiceException('Chat service is unavailable', 503);
    }

    /**
     * @param array<string, scalar|array|null> $query
     */
    private function buildUrl(string $uri, array $query): string
    {
        $url = rtrim($this->baseUrl, '/') . $uri;
        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query);
    }

    /**
     * @param array<int, string> $responseHeaders
     */
    private function extractStatusCode(array $responseHeaders): int
    {
        $statusLine = $responseHeaders[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
            return (int) $matches[1];
        }

        return 503;
    }
}
