<?php

declare(strict_types=1);

namespace ChatService;

use PDO;
use Redis;

class DialogRepository
{
    private const STORAGE_SQL = 'sql';
    private const STORAGE_REDIS = 'redis';
    private const USER_EXISTS_CACHE_PREFIX = 'user:exists:';

    private const EVENT_MESSAGE_SENT = 'message_sent';
    private const EVENT_DIALOG_READ = 'dialog_read';

    private const SAGA_STATUS_PENDING = 'pending';
    private const SAGA_STATUS_PROCESSING = 'processing';
    private const SAGA_STATUS_FAILED = 'failed';

    private const SEND_MESSAGE_LUA = <<<'LUA'
local dialogKey = KEYS[1]
local messageKey = KEYS[2]
local messageId = ARGV[1]
local fromUserId = ARGV[2]
local toUserId = ARGV[3]
local text = ARGV[4]
local createdAt = ARGV[5]
local score = ARGV[6]

redis.call('HSET', messageKey, 'from', fromUserId, 'to', toUserId, 'text', text, 'created_at', createdAt)
redis.call('ZADD', dialogKey, score, messageId)
return messageId
LUA;

    private const GET_DIALOG_LUA = <<<'LUA'
local dialogKey = KEYS[1]
local messagePrefix = KEYS[2]
local start = tonumber(ARGV[1])
local stop = tonumber(ARGV[2])
local ids = redis.call('ZRANGE', dialogKey, start, stop)
local result = {}

for _, id in ipairs(ids) do
  local messageKey = messagePrefix .. id
  local values = redis.call('HMGET', messageKey, 'from', 'to', 'text')
  table.insert(result, values[1] or '')
  table.insert(result, values[2] or '')
  table.insert(result, values[3] or '')
end

return result
LUA;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Redis $redis,
        private readonly string $storage,
        private readonly string $dialogPrefix,
        private readonly string $messagePrefix,
        private readonly string $counterPrefix,
        private readonly int $recipientCacheTtlSec,
        private readonly int $counterCacheTtlSec
    ) {
    }

    public function sendMessage(string $fromUserId, string $toUserId, string $text): ?string
    {
        if (!$this->recipientExists($toUserId)) {
            return null;
        }

        $messageId = $this->generateUuid();
        $createdAt = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();

        try {
            $this->insertMessageToSql($messageId, $fromUserId, $toUserId, $text, $createdAt);
            $this->appendSagaEvent($toUserId, $fromUserId, $messageId, self::EVENT_MESSAGE_SENT, 1);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        $this->cacheDialogMessageSafely($messageId, $fromUserId, $toUserId, $text, $createdAt);

        return $messageId;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getDialog(string $userId1, string $userId2, int $limit = 100, int $offset = 0): array
    {
        if ($this->isRedisStorage()) {
            try {
                $messages = $this->getDialogFromRedis($userId1, $userId2, $limit, $offset);
                if ($messages !== [] || $this->dialogExistsInRedis($userId1, $userId2)) {
                    return $messages;
                }
            } catch (\Throwable) {
                // Redis is an optimization only. SQL remains the fallback path.
            }
        }

        return $this->getDialogFromSql($userId1, $userId2, $limit, $offset);
    }

    /**
     * @return array{total_unread:int,dialogs:array<int,array<string,int|string>>,consistency:string}
     */
    public function getUnreadCounters(string $userId, ?string $dialogUserId = null): array
    {
        if ($this->hasOutstandingSagaEvents($userId, $dialogUserId)) {
            return [
                'total_unread' => $this->fetchTotalUnreadCountFromSourceOfTruth($userId),
                'dialogs' => $this->fetchUnreadCountersFromSourceOfTruth($userId, $dialogUserId),
                'consistency' => 'source_of_truth',
            ];
        }

        try {
            $cached = $this->getUnreadCountersFromCache($userId, $dialogUserId);
        } catch (\Throwable) {
            $cached = null;
        }

        if ($cached !== null) {
            $cached['consistency'] = 'projection';
            return $cached;
        }

        return [
            'total_unread' => $this->fetchTotalUnreadCount($userId),
            'dialogs' => $this->fetchUnreadCountersFromSql($userId, $dialogUserId),
            'consistency' => 'projection',
        ];
    }

    /**
     * @return array{marked_as_read:int,dialog_unread:int,total_unread:int,consistency:string}
     */
    public function markDialogAsRead(string $readerUserId, string $dialogUserId): array
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'UPDATE dialog_messages
                 SET read_at = NOW()
                 WHERE to_user_id = :reader_user_id
                   AND from_user_id = :dialog_user_id
                   AND read_at IS NULL'
            );

            $statement->execute([
                ':reader_user_id' => $readerUserId,
                ':dialog_user_id' => $dialogUserId,
            ]);

            $markedAsRead = $statement->rowCount();

            if ($markedAsRead > 0) {
                $this->appendSagaEvent(
                    $readerUserId,
                    $dialogUserId,
                    null,
                    self::EVENT_DIALOG_READ,
                    -$markedAsRead
                );
            }

            $dialogUnread = $this->fetchUnreadCountForDialogFromSourceOfTruth($readerUserId, $dialogUserId);
            $totalUnread = $this->fetchTotalUnreadCountFromSourceOfTruth($readerUserId);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return [
            'marked_as_read' => $markedAsRead,
            'dialog_unread' => $dialogUnread,
            'total_unread' => $totalUnread,
            'consistency' => 'source_of_truth',
        ];
    }

    private function insertMessageToSql(
        string $messageId,
        string $fromUserId,
        string $toUserId,
        string $text,
        string $createdAt
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO dialog_messages (shard_key, id, from_user_id, to_user_id, text, created_at, read_at)
             VALUES (:shard_key, :id, :from_user_id, :to_user_id, :text, :created_at, NULL)'
        );

        $statement->execute([
            ':shard_key' => $this->generateShardKey($fromUserId),
            ':id' => $messageId,
            ':from_user_id' => $fromUserId,
            ':to_user_id' => $toUserId,
            ':text' => $text,
            ':created_at' => $createdAt,
        ]);
    }

    private function appendSagaEvent(
        string $ownerUserId,
        string $dialogUserId,
        ?string $messageId,
        string $eventType,
        int $delta
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO dialog_counter_sagas (
                owner_user_id,
                id,
                dialog_user_id,
                message_id,
                event_type,
                delta,
                status,
                attempts,
                available_at,
                created_at,
                updated_at
            ) VALUES (
                :owner_user_id,
                :id,
                :dialog_user_id,
                :message_id,
                :event_type,
                :delta,
                :status,
                0,
                NOW(),
                NOW(),
                NOW()
            )'
        );

        $statement->execute([
            ':owner_user_id' => $ownerUserId,
            ':id' => $this->generateUuid(),
            ':dialog_user_id' => $dialogUserId,
            ':message_id' => $messageId,
            ':event_type' => $eventType,
            ':delta' => $delta,
            ':status' => self::SAGA_STATUS_PENDING,
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getDialogFromSql(string $userId1, string $userId2, int $limit, int $offset): array
    {
        $effectiveLimit = max(1, min($limit, 500));
        $effectiveOffset = max(0, $offset);

        $statement = $this->pdo->prepare(
            'SELECT from_user_id AS "from", to_user_id AS "to", text
             FROM dialog_messages
             WHERE (from_user_id = :user_id1 AND to_user_id = :user_id2)
                OR (from_user_id = :user_id2_reverse AND to_user_id = :user_id1_reverse)
             ORDER BY created_at ASC
             OFFSET :offset LIMIT :limit'
        );

        $statement->bindValue(':user_id1', $userId1);
        $statement->bindValue(':user_id2', $userId2);
        $statement->bindValue(':user_id2_reverse', $userId2);
        $statement->bindValue(':user_id1_reverse', $userId1);
        $statement->bindValue(':offset', $effectiveOffset, PDO::PARAM_INT);
        $statement->bindValue(':limit', $effectiveLimit, PDO::PARAM_INT);
        $statement->execute();

        /** @var array<int, array<string, string>> $rows */
        $rows = $statement->fetchAll();
        return $rows;
    }

    private function cacheDialogMessageSafely(
        string $messageId,
        string $fromUserId,
        string $toUserId,
        string $text,
        string $createdAt
    ): void {
        if (!$this->isRedisStorage()) {
            return;
        }

        try {
            $score = (string) round(microtime(true) * 1000000);

            $this->redis->eval(
                self::SEND_MESSAGE_LUA,
                [
                    $this->dialogRedisKey($fromUserId, $toUserId),
                    $this->messagePrefix . $messageId,
                    $messageId,
                    $fromUserId,
                    $toUserId,
                    $text,
                    $createdAt,
                    $score,
                ],
                2
            );
        } catch (\Throwable) {
            // Redis here is only a fast read model. On failure we fall back to SQL.
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getDialogFromRedis(string $userId1, string $userId2, int $limit, int $offset): array
    {
        $effectiveLimit = max(1, min($limit, 500));
        $effectiveOffset = max(0, $offset);
        $stop = $effectiveOffset + $effectiveLimit - 1;

        $flatRows = $this->redis->eval(
            self::GET_DIALOG_LUA,
            [
                $this->dialogRedisKey($userId1, $userId2),
                $this->messagePrefix,
                (string) $effectiveOffset,
                (string) $stop,
            ],
            2
        );

        if (!is_array($flatRows)) {
            return [];
        }

        $result = [];
        foreach (array_chunk($flatRows, 3) as $row) {
            if (count($row) < 3) {
                continue;
            }

            $result[] = [
                'from' => (string) $row[0],
                'to' => (string) $row[1],
                'text' => (string) $row[2],
            ];
        }

        return $result;
    }

    private function dialogExistsInRedis(string $userId1, string $userId2): bool
    {
        return (int) $this->redis->zCard($this->dialogRedisKey($userId1, $userId2)) > 0;
    }

    private function hasOutstandingSagaEvents(string $userId, ?string $dialogUserId): bool
    {
        $sql = 'SELECT 1
                FROM dialog_counter_sagas
                WHERE owner_user_id = :owner_user_id
                  AND status IN (:pending, :processing, :failed)';

        if ($dialogUserId !== null) {
            $sql .= ' AND dialog_user_id = :dialog_user_id';
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':owner_user_id', $userId);
        $statement->bindValue(':pending', self::SAGA_STATUS_PENDING);
        $statement->bindValue(':processing', self::SAGA_STATUS_PROCESSING);
        $statement->bindValue(':failed', self::SAGA_STATUS_FAILED);

        if ($dialogUserId !== null) {
            $statement->bindValue(':dialog_user_id', $dialogUserId);
        }

        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return array{total_unread:int,dialogs:array<int,array<string,int|string>>}|null
     */
    private function getUnreadCountersFromCache(string $userId, ?string $dialogUserId): ?array
    {
        $totalKey = $this->counterTotalRedisKey($userId);
        $totalUnread = $this->redis->get($totalKey);

        if ($totalUnread === false || $totalUnread === null) {
            return null;
        }

        $hashKey = $this->counterHashRedisKey($userId);
        $hashExists = (int) $this->redis->exists($hashKey) > 0;

        if ($dialogUserId !== null) {
            $dialogUnread = $this->redis->hGet($hashKey, $dialogUserId);

            if (($dialogUnread === false || $dialogUnread === null) && (int) $totalUnread > 0 && !$hashExists) {
                return null;
            }

            return [
                'total_unread' => (int) $totalUnread,
                'dialogs' => [
                    [
                        'user_id' => $dialogUserId,
                        'unread_count' => $dialogUnread === false || $dialogUnread === null ? 0 : (int) $dialogUnread,
                    ],
                ],
            ];
        }

        /** @var array<string, string> $rawDialogs */
        $rawDialogs = $this->redis->hGetAll($hashKey);

        if ($rawDialogs === [] && (int) $totalUnread > 0 && !$hashExists) {
            return null;
        }

        $dialogs = [];
        foreach ($rawDialogs as $dialogId => $unreadCount) {
            $dialogs[] = [
                'user_id' => $dialogId,
                'unread_count' => (int) $unreadCount,
            ];
        }

        usort($dialogs, static function (array $left, array $right): int {
            $countComparison = $right['unread_count'] <=> $left['unread_count'];
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp((string) $left['user_id'], (string) $right['user_id']);
        });

        return [
            'total_unread' => (int) $totalUnread,
            'dialogs' => $dialogs,
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function fetchUnreadCountersFromSql(string $userId, ?string $dialogUserId): array
    {
        $sql = 'SELECT dialog_user_id AS user_id, unread_count
                FROM dialog_counters
                WHERE owner_user_id = :owner_user_id
                  AND unread_count > 0';

        if ($dialogUserId !== null) {
            $sql .= ' AND dialog_user_id = :dialog_user_id';
        }

        $sql .= ' ORDER BY unread_count DESC, dialog_user_id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':owner_user_id', $userId);

        if ($dialogUserId !== null) {
            $statement->bindValue(':dialog_user_id', $dialogUserId);
        }

        $statement->execute();

        /** @var array<int, array<string, int|string>> $rows */
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['unread_count'] = (int) $row['unread_count'];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function fetchUnreadCountersFromSourceOfTruth(string $userId, ?string $dialogUserId): array
    {
        $sql = 'SELECT from_user_id AS user_id, COUNT(*) AS unread_count
                FROM dialog_messages
                WHERE to_user_id = :owner_user_id
                  AND read_at IS NULL';

        if ($dialogUserId !== null) {
            $sql .= ' AND from_user_id = :dialog_user_id';
        }

        $sql .= ' GROUP BY from_user_id
                  ORDER BY COUNT(*) DESC, from_user_id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':owner_user_id', $userId);

        if ($dialogUserId !== null) {
            $statement->bindValue(':dialog_user_id', $dialogUserId);
        }

        $statement->execute();

        /** @var array<int, array<string, int|string>> $rows */
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['unread_count'] = (int) $row['unread_count'];
        }

        return $rows;
    }

    private function fetchTotalUnreadCount(string $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(unread_count), 0)
             FROM dialog_counters
             WHERE owner_user_id = :owner_user_id'
        );

        $statement->execute([
            ':owner_user_id' => $userId,
        ]);

        $value = $statement->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function fetchTotalUnreadCountFromSourceOfTruth(string $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM dialog_messages
             WHERE to_user_id = :owner_user_id
               AND read_at IS NULL'
        );

        $statement->execute([
            ':owner_user_id' => $userId,
        ]);

        $value = $statement->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function fetchUnreadCountForDialogFromSourceOfTruth(string $ownerUserId, string $dialogUserId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM dialog_messages
             WHERE to_user_id = :owner_user_id
               AND from_user_id = :dialog_user_id
               AND read_at IS NULL'
        );

        $statement->execute([
            ':owner_user_id' => $ownerUserId,
            ':dialog_user_id' => $dialogUserId,
        ]);

        $value = $statement->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function recipientExists(string $userId): bool
    {
        if ($this->isSqlStorage()) {
            return $this->recipientExistsInDb($userId);
        }

        try {
            $cacheKey = self::USER_EXISTS_CACHE_PREFIX . $userId;
            $cached = $this->redis->get($cacheKey);
            if ($cached !== false && $cached !== null) {
                return $cached === '1';
            }

            $exists = $this->recipientExistsInDb($userId);
            $this->redis->setex($cacheKey, max(60, $this->recipientCacheTtlSec), $exists ? '1' : '0');

            return $exists;
        } catch (\Throwable) {
            return $this->recipientExistsInDb($userId);
        }
    }

    private function recipientExistsInDb(string $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE user_id = :user_id LIMIT 1');
        $statement->execute([':user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    private function isSqlStorage(): bool
    {
        return strtolower($this->storage) === self::STORAGE_SQL;
    }

    private function isRedisStorage(): bool
    {
        return strtolower($this->storage) === self::STORAGE_REDIS;
    }

    private function dialogRedisKey(string $userId1, string $userId2): string
    {
        $left = $userId1;
        $right = $userId2;

        if (strcmp($left, $right) > 0) {
            $left = $userId2;
            $right = $userId1;
        }

        return sprintf('%s:%s:%s', $this->dialogPrefix, $left, $right);
    }

    private function counterHashRedisKey(string $userId): string
    {
        return sprintf('%s:user:%s', $this->counterPrefix, $userId);
    }

    private function counterTotalRedisKey(string $userId): string
    {
        return sprintf('%s:total:%s', $this->counterPrefix, $userId);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function generateShardKey(string $seed): int
    {
        return unpack('N', hash('crc32b', $seed, true))[1];
    }
}
