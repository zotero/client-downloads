<?php
require(__DIR__ . '/lib/bootstrap.inc.php');

if (!array_key_exists("PATH_INFO", $_SERVER)) {
	header("HTTP/1.0 400 Bad Request");
	return;
}

$pathParts = explode("/", $_SERVER["PATH_INFO"]);
if(count($pathParts) < 7) {
	header("HTTP/1.0 400 Bad Request");
	return;
}

// GET /download/standalone/update/4.0.29.15/20161003133106/Darwin_x86_64-gcc3-u-i386-x86_64/en-US/release/Darwin%2016.6.0/update.xml
$clientInfo = [
	'version' => $pathParts[1],
	'buildID' => $pathParts[2],
	'buildTarget' => $pathParts[3],
	'locale' => $pathParts[4],
	'channel' => $pathParts[5],
	'osVersion' => $pathParts[6],
];

$cv = new \Zotero\ClientDownloads([
	'manifestsDir' => ROOT_DIR . "/manifests",
	'host' => $HOST
]);
$xml = $cv->getUpdatesXML($clientInfo);

// If no build exists for channel, return 404
if ($xml === false) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

header("Content-Type: text/xml");

$dom = new DOMDocument("1.0");
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());
echo $dom->saveXML();
