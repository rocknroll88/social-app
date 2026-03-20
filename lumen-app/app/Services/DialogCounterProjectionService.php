<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class DialogCounterProjectionService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_PROCESSED = 'processed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_COMPENSATED = 'compensated';

    /**
     * @return array{claimed:int,processed:int,failed:int}
     */
    public function processPendingSagas(int $limit = 100): array
    {
        $events = $this->claimPendingEvents($limit);

        $processed = 0;
        $failed = 0;
        $ownersToRefresh = [];

        foreach ($events as $event) {
            try {
                DB::transaction(function () use ($event) {
                    $freshEvent = DB::table('dialog_counter_sagas')
                        ->where('owner_user_id', $event->owner_user_id)
                        ->where('id', $event->id)
                        ->lockForUpdate()
                        ->first();

                    if ($freshEvent === null || $freshEvent->status !== self::STATUS_PROCESSING) {
                        return;
                    }

                    $this->applyProjectionDelta(
                        (string) $freshEvent->owner_user_id,
                        (string) $freshEvent->dialog_user_id,
                        (int) $freshEvent->delta
                    );

                    DB::table('dialog_counter_sagas')
                        ->where('owner_user_id', $freshEvent->owner_user_id)
                        ->where('id', $freshEvent->id)
                        ->update([
                            'status' => self::STATUS_PROCESSED,
                            'processed_at' => DB::raw('NOW()'),
                            'updated_at' => DB::raw('NOW()'),
                            'last_error' => null,
                        ]);
                });

                $processed++;
                $ownersToRefresh[(string) $event->owner_user_id] = true;
            } catch (\Throwable $exception) {
                $failed++;
                $this->markEventAsFailed((string) $event->owner_user_id, (string) $event->id, $exception->getMessage());
            }
        }

        foreach (array_keys($ownersToRefresh) as $ownerUserId) {
            $this->refreshCounterCache($ownerUserId);
        }

        return [
            'claimed' => $events->count(),
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{owners:int,reconciled:int}
     */
    public function reconcile(?string $ownerUserId = null, int $limit = 100): array
    {
        $owners = $this->ownersForReconciliation($ownerUserId, $limit);
        $reconciled = 0;

        foreach ($owners as $targetOwnerUserId) {
            DB::transaction(function () use ($targetOwnerUserId) {
                DB::table('dialog_counters')
                    ->where('owner_user_id', $targetOwnerUserId)
                    ->delete();

                $rows = DB::table('dialog_messages')
                    ->selectRaw('to_user_id AS owner_user_id, from_user_id AS dialog_user_id, COUNT(*) AS unread_count')
                    ->where('to_user_id', $targetOwnerUserId)
                    ->whereNull('read_at')
                    ->groupBy('to_user_id', 'from_user_id')
                    ->get();

                foreach ($rows as $row) {
                    DB::table('dialog_counters')->insert([
                        'owner_user_id' => $row->owner_user_id,
                        'dialog_user_id' => $row->dialog_user_id,
                        'unread_count' => (int) $row->unread_count,
                        'updated_at' => DB::raw('NOW()'),
                    ]);
                }

                DB::table('dialog_counter_sagas')
                    ->where('owner_user_id', $targetOwnerUserId)
                    ->whereIn('status', [
                        self::STATUS_PENDING,
                        self::STATUS_PROCESSING,
                        self::STATUS_FAILED,
                    ])
                    ->update([
                        'status' => self::STATUS_COMPENSATED,
                        'processed_at' => DB::raw('NOW()'),
                        'updated_at' => DB::raw('NOW()'),
                        'last_error' => null,
                    ]);
            });

            $this->refreshCounterCache($targetOwnerUserId);
            $reconciled++;
        }

        return [
            'owners' => count($owners),
            'reconciled' => $reconciled,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function claimPendingEvents(int $limit): Collection
    {
        return DB::transaction(function () use ($limit) {
            $events = DB::table('dialog_counter_sagas')
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
                ->where('available_at', '<=', date('Y-m-d H:i:s'))
                ->orderBy('created_at')
                ->limit(max(1, $limit))
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            foreach ($events as $event) {
                DB::table('dialog_counter_sagas')
                    ->where('owner_user_id', $event->owner_user_id)
                    ->where('id', $event->id)
                    ->update([
                        'status' => self::STATUS_PROCESSING,
                        'attempts' => DB::raw('attempts + 1'),
                        'updated_at' => DB::raw('NOW()'),
                        'last_error' => null,
                    ]);
            }

            return $events;
        });
    }

    private function applyProjectionDelta(string $ownerUserId, string $dialogUserId, int $delta): void
    {
        $insertUnreadCount = max(0, $delta);

        DB::statement(
            'INSERT INTO dialog_counters (owner_user_id, dialog_user_id, unread_count, updated_at)
             VALUES (?, ?, ?, NOW())
             ON CONFLICT (owner_user_id, dialog_user_id)
             DO UPDATE SET
                 unread_count = GREATEST(dialog_counters.unread_count + ?, 0),
                 updated_at = NOW()',
            [$ownerUserId, $dialogUserId, $insertUnreadCount, $delta]
        );
    }

    private function markEventAsFailed(string $ownerUserId, string $eventId, string $error): void
    {
        DB::table('dialog_counter_sagas')
            ->where('owner_user_id', $ownerUserId)
            ->where('id', $eventId)
            ->update([
                'status' => self::STATUS_FAILED,
                'available_at' => date('Y-m-d H:i:s', time() + 5),
                'updated_at' => DB::raw('NOW()'),
                'last_error' => mb_substr($error, 0, 1000),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function ownersForReconciliation(?string $ownerUserId, int $limit): array
    {
        if ($ownerUserId !== null && $ownerUserId !== '') {
            return [$ownerUserId];
        }

        return DB::table('dialog_counter_sagas')
            ->whereIn('status', [self::STATUS_FAILED, self::STATUS_PROCESSING])
            ->distinct()
            ->orderBy('owner_user_id')
            ->limit(max(1, $limit))
            ->pluck('owner_user_id')
            ->map(static fn ($value) => (string) $value)
            ->all();
    }

    private function refreshCounterCache(string $ownerUserId): void
    {
        $dialogs = DB::table('dialog_counters')
            ->where('owner_user_id', $ownerUserId)
            ->where('unread_count', '>', 0)
            ->orderByDesc('unread_count')
            ->orderBy('dialog_user_id')
            ->get(['dialog_user_id', 'unread_count']);

        $totalUnread = DB::table('dialog_counters')
            ->where('owner_user_id', $ownerUserId)
            ->sum('unread_count');

        $redis = Redis::connection('default');
        $hashKey = sprintf('%s:user:%s', (string) config('dialog.counter_redis_prefix', 'dialog:counter'), $ownerUserId);
        $totalKey = sprintf('%s:total:%s', (string) config('dialog.counter_redis_prefix', 'dialog:counter'), $ownerUserId);
        $ttl = max(60, (int) config('dialog.counter_cache_ttl_sec', 3600));

        $redis->del($hashKey);

        foreach ($dialogs as $dialog) {
            $redis->hset($hashKey, (string) $dialog->dialog_user_id, (string) $dialog->unread_count);
        }

        if ($dialogs->isNotEmpty()) {
            $redis->expire($hashKey, $ttl);
        }

        $redis->setex($totalKey, $ttl, (string) $totalUnread);
    }
}
