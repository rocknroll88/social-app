<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use stdClass;

class DialogRepository
{
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
        // Проверяем существование получателя
        $recipientExists = DB::table('users')->where('user_id', $toUserId)->exists();
        
        if (!$recipientExists) {
            return null;
        }

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

    /**
     * Получить список сообщений диалога между двумя пользователями.
     *
     * @param string $userId1
     * @param string $userId2
     * @return array<int, stdClass>
     */
    public function getDialog(string $userId1, string $userId2): array
    {
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
            ->select('from_user_id as from', 'to_user_id as to', 'text')
            ->get();

        return $messages->toArray();
    }
}

