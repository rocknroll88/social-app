<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Friend;
use App\Models\User;

class FriendsSeeder extends Seeder
{
    public function run()
    {
        Friend::truncate();

        $users = User::all();

        foreach ($users as $u1) {
            foreach ($users as $u2) {
                if ($u1->user_id === $u2->user_id) continue;

                $friend = Friend::create([
                    'user_id' => $u1->user_id,
                    'friend_id' => $u2->user_id,
                ]);

                dump("Friend {$u1->name} → {$u2->name} | shard_key={$friend->shard_key}");
            }
        }
    }
}