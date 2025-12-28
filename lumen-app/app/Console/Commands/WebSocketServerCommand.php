<?php

namespace App\Console\Commands;

use App\WebSocket\WebSocketServer;
use Illuminate\Console\Command;

class WebSocketServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:serve {--host=0.0.0.0} {--port=8081}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the WebSocket server for real-time post updates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Starting WebSocket server on {$host}:{$port}");

        try {
            /** @var WebSocketServer $server */
            $server = app(WebSocketServer::class);
            $server->start($host, $port);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to start WebSocket server: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
