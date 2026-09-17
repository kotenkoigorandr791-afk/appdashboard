<?php
declare(strict_types=1);

$base = __DIR__;
$root = dirname($base, 2);
$vendor = $root . '/vendor/autoload.php';
if (!is_file($vendor)) {
    throw new RuntimeException('Root vendor/autoload.php missing: ' . $vendor);
}
require_once $vendor;

$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('Kanboard\\', $base . '/app/');
$loader->addPsr4('Gregwar\\Captcha\\', $base . '/libs/Captcha/');
$loader->addPsr4('Eluceo\\iCal\\', $base . '/libs/ical/');
$loader->addPsr4('SimpleQueue\\', $base . '/libs/SimpleQueue/');
$loader->addPsr4('SimpleValidator\\', $base . '/libs/SimpleValidator/');
$loader->addPsr4('Symfony\\Component\\EventDispatcher\\', $base . '/libs/event-dispatcher/');
$loader->setUseIncludePath(false);
$loader->add('JsonRPC', $base . '/libs/jsonrpc/src');
$loader->add('PHPQRCode', $base . '/libs/phpqrcode/lib');
$loader->add('PicoDb', $base . '/libs/picodb/lib');
$loader->add('Parsedown', $base . '/libs/erusev/parsedown');
$relMap = require $base . '/autoload_classmap.php';
$map = [];
foreach ($relMap as $class => $rel) {
    $map[$class] = $base . '/' . ltrim(str_replace('\\', '/', $rel), '/');
}
$loader->addClassMap($map);
$loader->register(true);

require_once $base . '/app/functions.php';
require_once $base . '/libs/swiftmailer/swift_required.php';
