<?php

namespace App\Services;

use Ratchet\ConnectionInterface;

class WebSocketConnectionManager
{
    /** @var array<string, ConnectionInterface> */
    private array $connections = [];

    /**
     * Add a connection for a user
     */
    public function addConnection(string $userId, ConnectionInterface $connection): void
    {
        $this->connections[$userId] = $connection;
    }

    /**
     * Remove a connection for a user
     */
    public function removeConnection(string $userId): void
    {
        unset($this->connections[$userId]);
    }

    /**
     * Send a message to a specific user
     */
    public function sendToUser(string $userId, array $message): void
    {
        if (isset($this->connections[$userId])) {
            $this->connections[$userId]->send(json_encode($message));
        }
    }

    /**
     * Send a message to multiple users
     */
    public function sendToUsers(array $userIds, array $message): void
    {
        $json = json_encode($message);
        foreach ($userIds as $userId) {
            if (isset($this->connections[$userId])) {
                $this->connections[$userId]->send($json);
            }
        }
    }

    /**
     * Broadcast a message to all connected clients
     */
    public function broadcast(array $message): void
    {
        $json = json_encode($message);
        foreach ($this->connections as $connection) {
            $connection->send($json);
        }
    }

    /**
     * Get count of active connections
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Check if user is connected
     */
    public function isUserConnected(string $userId): bool
    {
        return isset($this->connections[$userId]);
    }
}
