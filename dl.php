<?php
require __DIR__ . '/lib/bootstrap.inc.php';

if (empty($_GET['platform'])) {
	http_response_code(400);
	exit;
}

$CD = new \Zotero\ClientDownloads([
	'manifestsDir' => ROOT_DIR . "/manifests"
]);

$platform = $_GET['platform'];
$channel = !empty($_GET['channel']) ? $_GET['channel'] : 'release';
$version = !empty($_GET['version']) ? $_GET['version'] : null;

switch ($channel) {
case 'release':
case 'beta':
case 'dev':
	break;
default:
	http_response_code(400);
	exit;
}

if ($version) {
	if (!preg_match('/\d\.\d(\.\d)?(\.\d)?/', $version)) {
		http_response_code(400);
		exit;
	}
}
else {
	$version = $CD->getBuildVersion($channel, $platform);
}

if (!$version) {
	http_response_code(400);
	exit;
}

switch ($platform) {
case 'mac':
	$filename = "Zotero-$version.dmg";
	break;

case 'linux-i686':
case 'linux-arm64':
case 'linux-x86_64':
	$filename = "Zotero-{$version}_$platform.tar.bz2";
	break;

case 'win-x64':
	$filename = "Zotero-{$version}_x64_setup.exe";
	break;

case 'win-x64-zip':
	$filename = "Zotero-{$version}_win-x64.zip";
	break;

case 'win-arm64':
	$filename = "Zotero-{$version}_arm64_setup.exe";
	break;

case 'win-arm64-zip':
	$filename = "Zotero-{$version}_win-arm64.zip";
	break;


case 'win32':
	if (\ToolkitVersionComparator::compare('6.999', $version) < 0) {
		$filename = "Zotero-{$version}_win32_setup.exe";
	}
	else {
		$filename = "Zotero-{$version}_setup.exe";
	}
	break;

case 'win32-zip':
	$filename = "Zotero-{$version}_win32.zip";
	break;

default:
	http_response_code(400);
	exit;
}

if (!empty($_GET['fn'])) {
	echo $filename;
	exit;
}

$version = urlencode($version);
$filename = urlencode($filename);

if (isset($statsd)) {
	$statsd->increment("downloads.client.$channel.$platform");
}
header("Location: $HOST/client/$channel/$version/$filename");
