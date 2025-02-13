<?php
require 'vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class SignalingServer implements MessageComponentInterface {
    protected $clients;
    protected $rooms;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->rooms = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->roomId = null;
        $conn->role = null;
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg);

        switch ($data->type) {
            case 'join':
                $this->handleJoin($from, $data);
                break;
            case 'offer':
            case 'answer':
            case 'ice-candidate':
            case 'chat':
                $this->broadcastToRoom($from, $msg);
                break;
        }
    }

    protected function handleJoin($conn, $data) {
        $roomId = $data->appointmentId;
        $conn->roomId = $roomId;
        $conn->role = $data->role;

        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }
        $this->rooms[$roomId][$conn->resourceId] = $conn;

        foreach ($this->rooms[$roomId] as $client) {
            if ($client !== $conn) {
                $client->send(json_encode([
                    'type' => 'join',
                    'role' => $conn->role
                ]));
            }
        }
    }

    protected function broadcastToRoom($from, $message) {
        $roomId = $from->roomId;
        if (isset($this->rooms[$roomId])) {
            foreach ($this->rooms[$roomId] as $client) {
                if ($client !== $from) {
                    $client->send($message);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if ($conn->roomId !== null) {
            unset($this->rooms[$conn->roomId][$conn->resourceId]);
            if (empty($this->rooms[$conn->roomId])) {
                unset($this->rooms[$conn->roomId]);
            } else {
 
                $this->broadcastToRoom($conn, json_encode([
                    'type' => 'peer-disconnected',
                    'role' => $conn->role
                ]));
            }
        }
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}


$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SignalingServer()
        )
    ),
    8080
);

echo "Signaling server started on port 8080\n";
$server->run();