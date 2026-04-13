<?php
require __DIR__ . '/lib/bootstrap.inc.php';

$CD = new \Zotero\ClientDownloads([
	'manifestsDir' => ROOT_DIR . "/manifests"
]);

$platform = !empty($_GET['platform']) ? $_GET['platform'] : null;
$channel = !empty($_GET['channel']) ? $_GET['channel'] : 'release';

switch ($channel) {
case 'release':
case 'beta':
case 'dev':
	break;
default:
	http_response_code(400);
	exit;
}

header('Cache-Control: no-cache');

if ($platform) {
	$version = $CD->getBuildVersion($channel, $platform);
	if (!$version) {
		http_response_code(400);
		exit;
	}
	header('Content-Type: text/plain');
	echo $version;
}
else {
	$versions = [];
	foreach ($CD->getPlatforms() as $p) {
		$v = $CD->getBuildVersion($channel, $p);
		if ($v) {
			$versions[$p] = $v;
		}
	}
	header('Content-Type: application/json');
	echo json_encode($versions, JSON_PRETTY_PRINT) . "\n";
}
