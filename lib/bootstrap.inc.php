<?php
define('ROOT_DIR', __DIR__ . '/..');
require ROOT_DIR . '/vendor/autoload.php';
require ROOT_DIR . '/config.inc.php';
require __DIR__ . '/ClientDownloads.inc.php';

$statsd = new \Domnikl\Statsd\Client(
	new \Domnikl\Statsd\Connection\UdpSocket($STATSD_HOST, $STATSD_PORT), ""
);
