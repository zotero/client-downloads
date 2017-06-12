<?php
require(__DIR__ . '/lib/bootstrap.inc.php');

if (empty($_GET['platform'])) {
	http_response_code(400);
	exit;
}

$cv = new \Zotero\ClientDownloads([
	'manifestsDir' => ROOT_DIR . "/manifests"
]);

$platform = $_GET['platform'];
$channel = !empty($_GET['channel']) ? $_GET['channel'] : 'release';
$from = !empty($_GET['from']) ? $_GET['from'] : null;

$build = $cv->getBuildOverride($platform, $from);
if ($build) {
	$version = $build['version'];
}
if (!isset($version)) {
	$version = $cv->getBuildVersion($channel, $platform);
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
case 'linux-x86_64':
	$filename = "Zotero-{$version}_$platform.tar.bz2";
	break;

case 'win32':
	$filename = "Zotero-{$version}_setup.exe";
	//$filename = "Zotero-{$version}_$platform.zip"
	break;

default:
	http_response_code(400);
	exit;
}

if (!empty($_GET['fn'])) {
	echo $filename;
	exit;
}

$subdir = $cv->getDownloadSubdir($channel);
$version = urlencode($version);
$filename = urlencode($filename);

header("Location: $HOST/standalone/{$subdir}$version/$filename");
