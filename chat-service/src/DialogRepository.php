<?php

declare(strict_types=1);

namespace ChatService;

use PDO;
use Redis;

class DialogRepository
{
    private const STORAGE_SQL = 'sql';
    private const USER_EXISTS_CACHE_PREFIX = 'user:exists:';

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
        private readonly int $recipientCacheTtlSec
    ) {
    }

    public function sendMessage(string $fromUserId, string $toUserId, string $text): ?string
    {
        if (!$this->recipientExists($toUserId)) {
            return null;
        }

        if ($this->isSqlStorage()) {
            return $this->sendMessageToSql($fromUserId, $toUserId, $text);
        }

        return $this->sendMessageToRedis($fromUserId, $toUserId, $text);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getDialog(string $userId1, string $userId2, int $limit = 100, int $offset = 0): array
    {
        if ($this->isSqlStorage()) {
            return $this->getDialogFromSql($userId1, $userId2, $limit, $offset);
        }

        return $this->getDialogFromRedis($userId1, $userId2, $limit, $offset);
    }

    private function sendMessageToSql(string $fromUserId, string $toUserId, string $text): string
    {
        $messageId = $this->generateUuid();
        $statement = $this->pdo->prepare(
            'INSERT INTO dialog_messages (shard_key, id, from_user_id, to_user_id, text, created_at)
             VALUES (:shard_key, :id, :from_user_id, :to_user_id, :text, :created_at)'
        );

        $statement->execute([
            ':shard_key' => $this->generateShardKey($fromUserId),
            ':id' => $messageId,
            ':from_user_id' => $fromUserId,
            ':to_user_id' => $toUserId,
            ':text' => $text,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return $messageId;
    }

    private function sendMessageToRedis(string $fromUserId, string $toUserId, string $text): string
    {
        $messageId = $this->generateUuid();
        $createdAt = date('Y-m-d H:i:s');
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

        return $messageId;
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

    private function recipientExists(string $userId): bool
    {
        if ($this->isSqlStorage()) {
            return $this->recipientExistsInDb($userId);
        }

        $cacheKey = self::USER_EXISTS_CACHE_PREFIX . $userId;
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false && $cached !== null) {
            return $cached === '1';
        }

        $exists = $this->recipientExistsInDb($userId);
        $this->redis->setex($cacheKey, max(60, $this->recipientCacheTtlSec), $exists ? '1' : '0');

        return $exists;
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
