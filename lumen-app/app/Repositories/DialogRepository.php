<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;
use stdClass;

class DialogRepository
{
    private const STORAGE_SQL = 'sql';
    private const STORAGE_REDIS = 'redis';
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
        private readonly string $storage = ''
    ) {
    }

    /**
     * Отправить сообщение пользователю.
     *
     * @param string $fromUserId
     * @param string $toUserId
     * @param string $text
     * @return string|null UUID сообщения
     */
    public function sendMessage(string $fromUserId, string $toUserId, string $text): ?string
    {
        $recipientExists = $this->recipientExists($toUserId);
        if (!$recipientExists) {
            return null;
        }

        if ($this->isRedisStorage()) {
            return $this->sendMessageToRedis($fromUserId, $toUserId, $text);
        }

        return $this->sendMessageToSql($fromUserId, $toUserId, $text);
    }

    /**
     * Получить список сообщений диалога между двумя пользователями.
     *
     * @param string $userId1
     * @param string $userId2
     * @param int $limit
     * @param int $offset
     * @return array<int, stdClass>
     */
    public function getDialog(string $userId1, string $userId2, int $limit = 100, int $offset = 0): array
    {
        if ($this->isRedisStorage()) {
            return $this->getDialogFromRedis($userId1, $userId2, $limit, $offset);
        }

        return $this->getDialogFromSql($userId1, $userId2, $limit, $offset);
    }

    /**
     * @return array<int, stdClass>
     */
    private function getDialogFromSql(string $userId1, string $userId2, int $limit, int $offset): array
    {
        $effectiveLimit = max(1, min($limit, 500));
        $effectiveOffset = max(0, $offset);

        $messages = DB::table('dialog_messages')
            ->where(function ($query) use ($userId1, $userId2) {
                $query->where('from_user_id', $userId1)
                    ->where('to_user_id', $userId2);
            })
            ->orWhere(function ($query) use ($userId1, $userId2) {
                $query->where('from_user_id', $userId2)
                    ->where('to_user_id', $userId1);
            })
            ->orderBy('created_at', 'asc')
            ->offset($effectiveOffset)
            ->limit($effectiveLimit)
            ->select('from_user_id as from', 'to_user_id as to', 'text')
            ->get();

        return $messages->toArray();
    }

    private function sendMessageToSql(string $fromUserId, string $toUserId, string $text): string
    {
        $messageId = Uuid::uuid4()->toString();

        DB::table('dialog_messages')->insert([
            'id' => $messageId,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'text' => $text,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $messageId;
    }

    private function sendMessageToRedis(string $fromUserId, string $toUserId, string $text): string
    {
        $messageId = Uuid::uuid4()->toString();
        $createdAt = date('Y-m-d H:i:s');
        $score = (string) round(microtime(true) * 1000000);

        Redis::connection('default')->eval(
            self::SEND_MESSAGE_LUA,
            2,
            $this->dialogRedisKey($fromUserId, $toUserId),
            $this->dialogMessageRedisPrefix() . $messageId,
            $messageId,
            $fromUserId,
            $toUserId,
            $text,
            $createdAt,
            $score
        );

        return $messageId;
    }

    /**
     * @return array<int, stdClass>
     */
    private function getDialogFromRedis(string $userId1, string $userId2, int $limit, int $offset): array
    {
        $effectiveLimit = max(1, min($limit, 500));
        $effectiveOffset = max(0, $offset);
        $stop = $effectiveOffset + $effectiveLimit - 1;

        $flatRows = Redis::connection('default')->eval(
            self::GET_DIALOG_LUA,
            2,
            $this->dialogRedisKey($userId1, $userId2),
            $this->dialogMessageRedisPrefix(),
            (string) $effectiveOffset,
            (string) $stop
        );

        if (!is_array($flatRows)) {
            return [];
        }

        $result = [];
        foreach (array_chunk($flatRows, 3) as $row) {
            if (count($row) < 3) {
                continue;
            }

            $item = new stdClass();
            $item->from = (string) $row[0];
            $item->to = (string) $row[1];
            $item->text = (string) $row[2];
            $result[] = $item;
        }

        return $result;
    }

    private function isRedisStorage(): bool
    {
        $configuredStorage = $this->storage !== '' ? $this->storage : (string) config('dialog.storage', self::STORAGE_SQL);
        return strtolower($configuredStorage) === self::STORAGE_REDIS;
    }

    private function dialogRedisKey(string $userId1, string $userId2): string
    {
        $left = $userId1;
        $right = $userId2;

        if (strcmp($left, $right) > 0) {
            $left = $userId2;
            $right = $userId1;
        }

        $prefix = (string) config('dialog.redis_prefix', 'dialog');
        return sprintf('%s:%s:%s', $prefix, $left, $right);
    }

    private function dialogMessageRedisPrefix(): string
    {
        return (string) config('dialog.redis_message_prefix', 'dialog:message:');
    }

    private function recipientExists(string $userId): bool
    {
        if (!$this->isRedisStorage()) {
            return DB::table('users')->where('user_id', $userId)->exists();
        }

        $cacheKey = self::USER_EXISTS_CACHE_PREFIX . $userId;
        $cached = Redis::connection('default')->get($cacheKey);
        if ($cached !== null) {
            return $cached === '1';
        }

        $exists = DB::table('users')->where('user_id', $userId)->exists();
        $ttl = (int) config('dialog.recipient_cache_ttl_sec', 3600);
        Redis::connection('default')->setex($cacheKey, max(60, $ttl), $exists ? '1' : '0');

        return $exists;
    }
}
