<?php
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}
define('APP_ROOT', dirname(__DIR__));
require __DIR__ . '/vendor/autoload.php';
require APP_ROOT . '/ssh_terminal_handler.php';
require __DIR__ . '/SshWsHandler.php';
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
$port = (int)(getenv('SSH_WS_PORT') ?: 8765);
$bind = (string)(getenv('SSH_WS_BIND') ?: '127.0.0.1');
$handler = new SshWsHandler();
$server = IoServer::factory(new HttpServer(new WsServer($handler)), $port, $bind);
$server->loop->addPeriodicTimer(0.05, function () use ($handler) {
    $handler->poll();
});
fwrite(STDOUT, "SSH WebSocket on {$bind}:{$port}\n");
$server->run();
