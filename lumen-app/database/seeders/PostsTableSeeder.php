<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PostsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Получаем всех пользователей
        $users = DB::table('users')->pluck('user_id')->toArray();
        
        if (empty($users)) {
            $this->command->error('No users found in database. Please seed users first.');
            return;
        }

        // Читаем файл с постами
        $filePath = __DIR__ . '/posts.txt';
        
        if (!file_exists($filePath)) {
            $this->command->error("File not found: {$filePath}");
            return;
        }

        $content = file_get_contents($filePath);
        
        // Разбиваем на абзацы (посты)
        $paragraphs = array_filter(
            explode("\n", $content),
            fn($p) => !empty(trim($p))
        );

        $this->command->info('Found ' . count($paragraphs) . ' paragraphs to import.');
        $this->command->info('Found ' . count($users) . ' users in database.');

        $posts = [];
        $batchSize = 1000;
        $totalPosts = 0;

        foreach ($paragraphs as $index => $text) {
            $text = trim($text);
            
            if (empty($text)) {
                continue;
            }

            // Случайно выбираем пользователя
            $authorUserId = $users[array_rand($users)];

            $posts[] = [
                'id' => Uuid::uuid4()->toString(),
                'author_user_id' => $authorUserId,
                'text' => $text,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 365) . ' days -' . rand(0, 23) . ' hours')),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Вставляем батчами для производительности
            if (count($posts) >= $batchSize) {
                DB::table('posts')->insert($posts);
                $totalPosts += count($posts);
                $this->command->info("Inserted {$totalPosts} posts...");
                $posts = [];
            }
        }

        // Вставляем оставшиеся посты
        if (!empty($posts)) {
            DB::table('posts')->insert($posts);
            $totalPosts += count($posts);
        }

        $this->command->info("Successfully seeded {$totalPosts} posts!");
    }
}

