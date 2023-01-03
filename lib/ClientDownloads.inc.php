<?php
namespace Zotero;
require('ToolkitVersionComparator.inc.php');

class ClientDownloads {
	private $channels = [
		'release', 'beta', 'dev'
	];
	// Key is the format passed by the Mozilla update check
	private $platforms = [
		"Darwin" => "mac",
		"WINNT_x86_64" => "win64",
		"WINNT_x86" => "win32",
		"Linux_x86_64" => "linux-x86_64",
		"Linux_x86" => "linux-i686"
	];
	
	
	public function __construct(array $config) {
		$this->manifestsDir = $config['manifestsDir'];
		if (isset($config['host'])) {
			$this->host = $config['host'];
		}
	}
	
	
	public function getUpdates(array $clientInfo) {
		$channel = $clientInfo['channel'];
		$fromVersion = $clientInfo['version'];
		
		if ($channel == 'default') {
			$channel = 'release';
		}
		
		$updates = [];
		
		// Check for valid platform
		foreach ($this->platforms as $key => $value) {
			if (strpos($clientInfo['buildTarget'], $key) === 0) {
				$os = $value;
				break;
			}
		}
		if (empty($os)) {
			error_log("Invalid build target '" . $clientInfo['buildTarget'] . "'");
			return $updates;
		}
		
		// Check for a specific build for this version
		$buildOverride = $this->getBuildOverride($clientInfo['osVersion'], $fromVersion, $clientInfo['manual']);
		if ($buildOverride) {
			$build = $buildOverride;
		}
		else {
			// Find the latest build for this channel and OS
			$build = $this->getBuild($channel, $os);
			if (!$build) {
				error_log("Build $channel/$os not found");
				return false;
			}
		}
		
		// Already on latest (or higher) version
		if (\ToolkitVersionComparator::compare($fromVersion, $build['version']) >= 0) {
			return $updates;
		}
		
		// Check for a hard-coded upgrade for this version
		$updateOverride = $this->getUpdateDataOverride($os, $fromVersion);
		if ($updateOverride) {
			$updates[] = $updateOverride;
			return $updates;
		}
		
		$shortOS = preg_replace('/^(mac|win|linux).+/', "$1", $os);
		$updateFull = "Zotero-" . $build["version"] . "-full_" . $os . ".mar";
		$updatePartial = "Zotero-" . $build["version"] . "-" . $fromVersion . "_" . $os . ".mar";
		$completeHash = false;
		$partialHash = false;
		
		if (isset($build["major"]) && !is_null($build["major"])) {
			$isMinor = !$build["major"];
		}
		// If not explicitly set as major or minor, it's minor if the first 3 characters in the
		// version haven't changed
		else {
			$isMinor = !strncmp($build["version"], $fromVersion, 3);
		}
		$type = $isMinor ? "minor" : "major";
		
		// Read in hashes and files
		$versionDir = $this->manifestsDir . '/' . $channel . '/' . $build['version'];
		
		// Old directory format
		if (file_exists($versionDir . "/files")) {
			$files = file_get_contents($versionDir . "/files");
			$hashes = file_get_contents($versionDir . "/sha512sums");
			
			// Make sure we have a full mar for this build
			$completeHash = $this->hashAndSize($hashes, $files, $updateFull);
			// Check whether we have an incremental mar for this build
			$partialHash = $this->hashAndSize($hashes, $files, $updatePartial);
		}
		// New directory format
		else if (file_exists($versionDir . "/files-" . $shortOS)) {
			$manifest = file_get_contents($versionDir . "/files-" . $shortOS);
			// Make sure we have a full mar for this build
			$completeHash = $this->hashAndSize2($manifest, $updateFull);
			// Check whether we have an incremental mar for this build
			$partialHash = $this->hashAndSize2($manifest, $updatePartial);
		}
		
		$baseURI = $this->getBaseURI($channel, $build['version']);
		
		// Assemble patch info
		$patches = [];
		if ($completeHash) {
			$patch = [];
			$patch['type'] = 'complete';
			$patch['URL'] = $baseURI . urlencode($updateFull);
			foreach ($completeHash as $key => $val) {
				$patch[$key] = $val;
			}
			$patches[] = $patch;
		}
		
		if ($partialHash) {
			$patch = [];
			$patch['type'] = 'partial';
			$patch['URL'] = $baseURI . urlencode($updatePartial);
			foreach ($partialHash as $key => $val) {
				$patch[$key] = $val;
			}
			$patches[] = $patch;
		}
		
		if (!$patches) {
			error_log("No patches found for $channel/$os/{$build['version']}");
			return $updates;
		}
		
		$update = [
			'type' => $type,
			'version' => $build['version'],
			'buildID' => $build['buildID'],
			'detailsURL' => $build['detailsURL'],
			'patches' => $patches
		];
		
		$updates[] = $update;
		return $updates;
	}
	
	
	public function getUpdatesXML(array $clientInfo) {
		$updates = $this->getUpdates($clientInfo);
		if ($updates === false) {
			return false;
		}
		
		$xml = new \SimpleXMLElement('<updates/>');
		if (!$updates) {
			return $xml;
		}
		
		foreach ($updates as $updateInfo) {
			$update = $xml->addChild('update');
			$update['type'] = $updateInfo['type'];
			$update['displayVersion'] = $updateInfo['version'];
			$update['appVersion'] = $updateInfo['version'];
			
			// Deprecated
			$update['version'] = $updateInfo['version'];
			$update['extensionVersion'] = $updateInfo['version'];
			
			$update['buildID'] = $updateInfo['buildID'];
			$update['detailsURL'] = $updateInfo['detailsURL'];
			
			$optionalFields = ['showPrompt', 'promptWaitTime'];
			foreach ($optionalFields as $field) {
				if (isset($updateInfo[$field])) {
					$update[$field] = $updateInfo[$field];
				}
			}
			
			foreach ($updateInfo['patches'] as $patch) {
				$elem = $update->addChild('patch');
				foreach ($patch as $key => $val) {
					$elem[$key] = $val;
				}
			}
		}
		
		return $xml;
	}
	
	
	public function getBuildVersion($channel, $platform) {
		$build = $this->getBuild($channel, $platform);
		return $build ? $build['version'] : false;
	}
	
	
	/**
	 * Return a specific build for some versions
	 */
	public function getBuildOverride($osVersion, $fromVersion, $manual) {
		if (strpos($osVersion, "Windows_NT 5.") === 0
				|| strpos($osVersion, "Windows_NT 6.0") === 0) {
			return [
				"major" => !!preg_match('/^[1234]\./', $fromVersion),
				"version" => "5.0.77",
				"detailsURL" => "https://www.zotero.org/support/5.0_changelog",
				"buildID" => "20191031072159"
			];
		}
		/*if (strpos($fromVersion, '4.0') === 0 && !$manual) {
			switch ($os) {
			case 'mac':
				return [
					"major" => false,
					"version" => "4.0.29.15",
					"detailsURL" => "https://www.zotero.org/support/4.0_changelog",
					"buildID" => "20161003133106"
				];
				break;
			
			case 'win32':
				return [
					"major" => false,
					"version" => "4.0.29.17",
					"detailsURL" => "https://www.zotero.org/support/4.0_changelog",
					"buildID" => "20170119075515"
				];
				break;
			
			case 'linux-i686':
				return [
					"major" => false,
					"version" => "4.0.29.10",
					"detailsURL" => "https://www.zotero.org/support/4.0_changelog",
					"buildID" => "20160511"
				];
				break;
			
			case 'linux-x86_64':
				return [
					"major" => false,
					"version" => "4.0.29.10",
					"detailsURL" => "https://www.zotero.org/support/4.0_changelog",
					"buildID" => "20160511"
				];
				break;
			}
		}*/
		return false;
	}
	
	
	/**
	 * Return hard-coded update data for some versions
	 *
	 * Unlike getBuildOverride(), which specifies a version to use the existing update data from,
	 * this specifies the exact update data to use.
	 */
	private function getUpdateDataOverride($os, $fromVersion) {
		// Check for fixed updates
		if ($os == 'mac') {
			// Don't show updates past 4.0.29.11 for 10.6-10.8 users
			if (isset($_SERVER['HTTP_USER_AGENT'])
					&& (strpos($_SERVER["HTTP_USER_AGENT"], "OS X 10.6;") !== false
					|| strpos($_SERVER["HTTP_USER_AGENT"], "OS X 10.7;") !== false
					|| strpos($_SERVER["HTTP_USER_AGENT"], "OS X 10.8;") !== false)) {
				return [
					'type' => 'minor',
					'version' => '4.0.29.11',
					'buildID' => '20160827171848',
					'detailsURL' => 'http://www.zotero.org/support/4.0_changelog',
					'patches' => [
						[
							'type' => 'complete',
							'URL' => $this->getBaseURI('release', '4.0.29.11') . 'Zotero-4.0.29.11-full_mac.mar',
							'hashFunction' => 'SHA512',
							'hashValue' => '1433f86d7faa28ae46c8c064aa436da20b6eef3cd9403c70aa8eca7e85255e7a2596919377d22f44232714852135c36c591282d438757b0a7c77d6356caf3822',
							'size' => 75353698
						]
					]
				];
			}
			
			switch ($fromVersion) {
			case '4.0.28.6':
			case '4.0.28.7':
				return [
					'type' => 'minor',
					'version' => 'You will need to download this update from zotero.org/download',
					'buildID' => '20151003',
					'detailsURL' => 'http://www.zotero.org/support/4.0_changelog',
					'showPrompt' => 'true',
					'promptWaitTime' => '1',
					'patches' => [
						[
							'type' => 'complete',
							'URL' => 'https://www.zotero.org/download/client/4.0.28.8-mac-update-failure',
							'hashFunction' => 'SHA512',
							'hashValue' => '3a777d6df7c87a496643d1a24261b2bce65a2cea16e9fff1ab7f9dfdb5c752af537783e49d6f14be818f06c9bc92debc6d0e3efa539ff0ff15ec9421a26e8e7b',
							'size' => 44520206
						]
					]
				];
			}
		}
		
		return false;
	}
	
	
	private function getBuilds($channel, $platform) {
		if ($platform == 'win32-zip') {
			$platform = 'win32';
		}
		if ($platform == 'win64-zip') {
			$platform = 'win64';
		}
		if (!in_array($channel, $this->channels)) {
			error_log("Invalid channel '$channel'");
			return false;
		}
		if (!in_array($platform, $this->platforms)) {
			error_log("Invalid platform '$platform'");
			return false;
		}
		$path = $this->manifestsDir . "/$channel/updates-$platform.json";
		if (!file_exists($path)) {
			error_log("$path not found");
			return false;
		}
		return json_decode(file_get_contents($path), true);
	}
	
	
	private function getBuild($channel, $platform) {
		$builds = $this->getBuilds($channel, $platform);
		if (!$builds) {
			return false;
		}
		return array_pop($builds);
	}
	
	
	private function hashAndSize($hashes, $files, $file) {
		$quoted = preg_quote($file);
		
		$matched = preg_match('/(?:\n|^)([^ ]+)  '.$quoted.'/', $hashes, $matches);
		if (!$matched) return false;
		$hash = $matches[1];
		
		$size = preg_match('/([0-9]+) +[^ ]+ +[^ ]+ +[^ ]+ +'.$quoted.'(?:$|\n)/', $files, $matches);
		if (!$size) return false;
		$size = $matches[1];
		
		return [
			'hashFunction' => 'sha512',
			'hashValue' => $hash,
			'size' => $size
		];
	}
	
	
	private function hashAndSize2($manifest, $file) {
		$quoted = preg_quote($file);
		if (!preg_match("/(?:\n|^)$quoted ([a-f0-9]+) ([0-9]+)/", $manifest, $matches)) {
			return false;
		}
		return [
			'hashFunction' => 'sha512',
			'hashValue' => $matches[1],
			'size' => $matches[2]
		];
	}
	
	
	private function getBaseURI($channel, $version) {
		if (empty($this->host)) {
			throw new \Exception("Host is not set");
		}
		return $this->host . "/client/$channel/" . urlencode($version) . "/";
	}
}
