<?php
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/webssh/vendor/autoload.php';
require __DIR__ . '/SmartHomeWsHandler.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

$port = (int)(getenv('SMART_HOME_WS_PORT') ?: 8766);
$bind = (string)(getenv('SMART_HOME_WS_BIND') ?: '127.0.0.1');
$handler = new SmartHomeWsHandler(__DIR__);
$server = IoServer::factory(new HttpServer(new WsServer($handler)), $port, $bind);
$server->loop->addPeriodicTimer(0.2, function () use ($handler) {
    $handler->tick();
});
fwrite(STDOUT, "Smart Home WebSocket on {$bind}:{$port}\n");
$server->run();
