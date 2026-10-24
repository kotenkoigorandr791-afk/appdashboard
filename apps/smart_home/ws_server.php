<?php
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/webssh/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/sentry_bootstrap.php';
app_sentry_bootstrap('smart_home_ws');
require __DIR__ . '/SmartHomeWsHandler.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

$port = (int)(($argv[1] ?? '') ?: (getenv('SMART_HOME_WS_PORT') ?: 8766));
$bind = (string)(getenv('SMART_HOME_WS_BIND') ?: '127.0.0.1');
$handler = new SmartHomeWsHandler(__DIR__, $port);
$server = IoServer::factory(new HttpServer(new WsServer($handler)), $port, $bind);
$server->loop->addPeriodicTimer(0.2, function () use ($handler) {
    $handler->tick();
});
$server->loop->addPeriodicTimer(0.05, function () use ($handler) {
    $handler->drainInbox();
});
fwrite(STDOUT, "Smart Home WebSocket on {$bind}:{$port}\n");
$server->run();
