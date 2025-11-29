<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RouteListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all registered routes';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $routeCollection = app()->router->getRoutes();
        
        $routes = [];
        
        foreach ($routeCollection as $route) {
            $routes[] = [
                'method' => $route['method'],
                'uri' => $route['uri'],
                'action' => $route['action']['uses'] ?? 'Closure',
            ];
        }
        
        if (empty($routes)) {
            $this->info('No routes found.');
            return;
        }
        
        $this->line('');
        $this->line('+' . str_repeat('-', 10) . '+' . str_repeat('-', 40) . '+' . str_repeat('-', 50) . '+');
        $this->line('| ' . str_pad('Method', 8) . ' | ' . str_pad('URI', 38) . ' | ' . str_pad('Action', 48) . ' |');
        $this->line('+' . str_repeat('-', 10) . '+' . str_repeat('-', 40) . '+' . str_repeat('-', 50) . '+');
        
        foreach ($routes as $route) {
            $method = str_pad($route['method'], 8);
            $uri = str_pad($route['uri'], 38);
            $action = str_pad($route['action'], 48);
            
            $this->line("| {$method} | {$uri} | {$action} |");
        }
        
        $this->line('+' . str_repeat('-', 10) . '+' . str_repeat('-', 40) . '+' . str_repeat('-', 50) . '+');
        $this->line('');
        $this->info('Total routes: ' . count($routes));
    }
}

