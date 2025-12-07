<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DialogMessagesSeeder extends Seeder
{
    public function run()
    {
        $users = DB::table('users')->pluck('user_id')->toArray();

        if (count($users) < 2) {
            dump("Not enough users for dialog messages");
            return;
        }

        foreach (range(1, 20) as $i) {
            // Выбираем двух разных пользователей
            $from = $users[array_rand($users)];
            $to   = $users[array_rand($users)];

            // Гарантируем, что from != to
            while ($to === $from) {
                $to = $users[array_rand($users)];
            }

            // shard_key = UUID % 2^32 для консистентного шардинга
            $shardKey = hexdec(substr(str_replace('-', '', $from), 0, 8));

            $msgId = Str::uuid()->toString();

            DB::table('dialog_messages')->insert([
                'shard_key'     => $shardKey,
                'id'            => $msgId,
                'from_user_id'  => $from,
                'to_user_id'    => $to,
                'text'          => "Test message {$i}",
                'created_at'    => Carbon::now(),
            ]);

            dump("Dialog msg {$msgId} → shard_key={$shardKey} ({$from} → {$to})");
        }
    }
}