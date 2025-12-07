<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Str;

class PostsSeeder extends Seeder
{
    public function run()
    {
        Post::truncate();

        $users = User::all();

        foreach ($users as $user) {
            $post = Post::create([
                'author_user_id' => $user->user_id,
                'text' => "Test post from {$user->name}",
            ]);

            dump("Post {$post->id} → shard_key={$post->shard_key}");
        }
    }
}