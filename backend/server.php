<?php
// backend/server.php
// Run: php server.php

require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;

class EventSocket implements MessageComponentInterface {
    /** @var \SplObjectStorage */
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "WebSocket server starting on port 8080\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // We expect JSON messages; handle gracefully if not.
        $decoded = null;
        try {
            $decoded = json_decode($msg, true);
        } catch (\Throwable $e) {
            $decoded = null;
        }

        // Example: broadcast the received message to all other clients
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                // Send original raw message (so non-JSON still works)
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

$port = 8080;
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new EventSocket()
        )
    ),
    $port
);

$server->run();