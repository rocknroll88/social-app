<?php

namespace App\Providers;

use App\Repositories\UserRepository;
use App\Services\WebSocketConnectionManager;
use App\Services\WebSocketNotificationService;
use App\WebSocket\WebSocketServer;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Interop\Queue\PsrConnectionFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, function () {
            return new UserRepository();
        });

        $this->app->singleton(WebSocketConnectionManager::class, function () {
            return new WebSocketConnectionManager();
        });

        $this->app->singleton(WebSocketServer::class, function ($app) {
            return new WebSocketServer(
                $app->make(WebSocketConnectionManager::class),
                $app->make(UserRepository::class)
            );
        });

        $this->app->singleton(WebSocketNotificationService::class, function ($app) {
            return new WebSocketNotificationService(
                $app->make(WebSocketServer::class)
            );
        });

        // Register maintenance mode checker for queue worker
        $this->app->bind('isDownForMaintenance', function () {
            return function () {
                return false; // Always return false - not in maintenance mode
            };
        });

        // Register RabbitMQ connector
        $this->app->bind(PsrConnectionFactory::class, function ($app) {
            return new AmqpConnectionFactory([
                'dsn' => env('RABBITMQ_DSN', 'amqp://guest:guest@rabbitmq:5672/%2f'),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register RabbitMQ queue connector
        $this->app['queue']->extend('rabbitmq', function () {
            return new \Enqueue\LaravelQueue\Connector\AmqpConnector(
                $this->app[PsrConnectionFactory::class]
            );
        });
    }
}
