<?php

namespace App\Console\Commands;

use App\Services\FeedCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WarmUpFeedCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:warmup {--user_id= : Specific user ID to warm up}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up feed cache for all users or specific user';

    /**
     * @var FeedCacheService
     */
    private FeedCacheService $feedCacheService;

    /**
     * Create a new command instance.
     *
     * @param FeedCacheService $feedCacheService
     */
    public function __construct(FeedCacheService $feedCacheService)
    {
        parent::__construct();
        $this->feedCacheService = $feedCacheService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $specificUserId = $this->option('user_id');

        if ($specificUserId) {
            // Предзаполнение для конкретного пользователя
            $this->info("Warming up feed cache for user: {$specificUserId}");
            $count = $this->feedCacheService->warmUpFeed($specificUserId);
            $this->info("Added {$count} posts to feed cache");
            return 0;
        }

        // Предзаполнение для всех пользователей
        $this->info('Warming up feed cache for all users...');
        
        $users = DB::table('users')->pluck('user_id');
        $totalUsers = $users->count();
        
        if ($totalUsers === 0) {
            $this->error('No users found in database');
            return 1;
        }

        $this->info("Found {$totalUsers} users");
        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $totalPosts = 0;
        foreach ($users as $userId) {
            $count = $this->feedCacheService->warmUpFeed($userId);
            $totalPosts += $count;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("Successfully warmed up feed cache!");
        $this->info("Total users: {$totalUsers}");
        $this->info("Total posts cached: {$totalPosts}");
        $this->info("Average posts per user: " . round($totalPosts / $totalUsers, 2));

        return 0;
    }
}

