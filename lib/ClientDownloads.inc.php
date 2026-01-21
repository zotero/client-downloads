<?php
namespace Zotero;
require('ToolkitVersionComparator.inc.php');

class ClientDownloads {
	private $channels = [
		'release', 'beta', 'dev', 'test'
	];
	// Key is the format passed by the Mozilla update check
	private $platforms = [
		"Darwin" => "mac",
		"WINNT_x86_64" => "win-x64",
		"WINNT_aarch64" => "win-arm64",
		"WINNT_x86" => "win32",
		"Linux_x86_64" => "linux-x86_64",
		"Linux_x86" => "linux-i686",
		"Linux_aarch64" => "linux-arm64"
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
		
		$fromZotero7OrLater = \ToolkitVersionComparator::compare($fromVersion, "6.999") >= 0;
		
		// Fix incorrect channel after update in early fx128 builds
		if ($channel == 'esr' && $os == 'mac' && preg_match('/^7\.1-beta\.[1234]/', $fromVersion)) {
			$channel = 'beta';
		}
		
		// Check for a specific build for this version
		$buildOverride = $this->getBuildOverride($channel, $os, $clientInfo['osVersion'], $fromVersion, $clientInfo['manual']);
		if ($buildOverride) {
			$build = $buildOverride;
		}
		else {
			// Find the latest build for this channel and OS
			$builds = $this->getBuilds($channel, $os);
			if (!$builds) {
				error_log("No builds found for $channel/$os");
				return false;
			}
			$build = array_pop($builds);
			if (!$build) {
				error_log("Build not found for $channel/$os");
			}
		}
		
		if (!empty($_SERVER['HTTP_UPDATER_FIXED'])) {
			error_log("Received Updater-Fixed request");
		}
		// Don't serve updates for 7.0.16 on Windows or 7.0.16/18 on Linux,
		// since they have a broken updater
		else {
			if (preg_match("/^win/", $os) && $channel == 'release' && $fromVersion == '7.0.16') {
				return [];
			}
			if (preg_match("/^linux/", $os)
					&& $channel == 'release'
					&& ($fromVersion == '7.0.16' || $fromVersion == '7.0.18')) {
				return [];
			}
		}
		
		// Already on latest (or higher) version
		if (\ToolkitVersionComparator::compare($fromVersion, $build['version']) >= 0) {
			return $updates;
		}
		
		// Check for a hard-coded upgrade for this version
		$updateOverride = $this->getUpdateDataOverride($channel, $os, $fromVersion);
		if ($updateOverride) {
			$updates[] = $updateOverride;
			return $updates;
		}
		
		// For updates from pre-7.0 builds to 7+, offer bzip2 full mar
		$bz = '';
		if (!$fromZotero7OrLater && \ToolkitVersionComparator::compare($build['version'], "6.999") > 0) {
			$bz = 'bz_';
		}
		
		$shortOS = preg_replace('/^(mac|win|linux).+/', "$1", $os);
		$updateFull = "Zotero-{$build["version"]}-full_{$bz}$os.mar";
		$updatePartial = "Zotero-{$build["version"]}-{$fromVersion}_$os.mar";
		$completeHash = false;
		$partialHash = false;
		
		if (isset($build["major"]) && !is_null($build["major"])) {
			$isMinor = !$build["major"];
		}
		// If not explicitly set as major or minor, it's minor if the first 3 characters in the
		// version haven't changed
		else {
			// TEMP: Don't consider 7.0/7.1 → 8.0 a major upgrade, since they don't know how to prompt
			//$isMinor = !strncmp($build["version"], $fromVersion, 3);
			$isMinor = !strncmp($build["version"], $fromVersion, 2)
				|| (preg_match('/^7\.[01]/', $fromVersion) && $this->str_starts_with($build["version"], '8.0'));
		}
		$type = $isMinor ? "minor" : "major";
		
		// Read in hashes and files
		$versionDir = $this->manifestsDir . '/' . $channel . '/' . $build['version'];
		$manifest = file_get_contents($versionDir . "/files-" . $shortOS);
		// Make sure we have a full mar for this build
		$completeHash = $this->hashAndSize($manifest, $updateFull);
		// Check whether we have an incremental mar for this build
		$partialHash = $this->hashAndSize($manifest, $updatePartial);
		
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
		$builds = $this->getBuilds($channel, $platform);
		$build = array_pop($builds);
		return $build ? $build['version'] : false;
	}
	
	
	/**
	 * Return a specific build for some versions
	 */
	private function getBuildOverride($channel, $os, $osVersion, $fromVersion, $manual) {
		// TODO: Switch to real str_starts_with()
		if ($this->str_starts_with($os, "win")) {
			// Don't serve past 5.0.77 for Vista or earlier
			if ($this->str_starts_with($osVersion, "Windows_NT 5.")
					|| $this->str_starts_with($osVersion, "Windows_NT 6.0")) {
				return [
					"major" => !!preg_match('/^[1234]\./', $fromVersion),
					"version" => "5.0.77",
					"detailsURL" => "https://www.zotero.org/support/5.0_changelog",
					"buildID" => "20191031072159"
				];
			}
		}
		else if ($os == 'mac') {
			// Don't serve past 6.0.37 for macOS 10.9-10.11
			if (isset($_SERVER['HTTP_USER_AGENT'])
					&& preg_match('/OS X 10\.(9|10|11);/', $_SERVER["HTTP_USER_AGENT"])) {
				return [
					"major" => !!preg_match('/^[12345]\./', $fromVersion),
					"version" => "6.0.37",
					"detailsURL" => "https://www.zotero.org/support/6.0_changelog",
					"buildID" => "20240319052808"
				];
			}
			
			// TEMP? "Darwin%2018.2.0"
			$osVersion = urldecode($osVersion);
			list($_, $darwinVersion) = explode(' ', $osVersion);
			list($darwinMajorVersion) = explode('.', $darwinVersion);
			
			// Don't serve past 7.0/7.1 beta for 10.14 Mojave or earlier
			if ($darwinMajorVersion <= 18) {
				/*if ($channel == 'release') {
					return [
						"major" => !!preg_match('/^[12345]\./', $fromVersion),
						"version" => "7.0.22",
						"detailsURL" => "https://www.zotero.org/support/7.0_changelog",
						"buildID" => "20250725113910"
					];
				}
				else */if ($channel == 'beta') {
					return [
						"major" => false,
						"version" => "7.1-beta.48+735922a2b",
						"detailsURL" => "https://www.zotero.org/support/7.0_changelog",
						"buildID" => "20250724122241"
					];
				}
				/*else {
					return false;
				}*/
			}
		}
		
		// Serve Z6 for automatic updates from <7.0 for now
		if (!$manual && $channel == 'release' && preg_match('/^[123456]\./', $fromVersion)) {
			switch ($os) {
				case 'mac':
					return [
						"major" => false,
						"version" => "6.0.37",
						"detailsURL" => "https://www.zotero.org/support/6.0_changelog",
						"buildID" => "20240319052808"
					];
					break;
				
				case 'win32':
					return [
						"major" => false,
						"version" => "6.0.36",
						"detailsURL" => "https://www.zotero.org/support/6.0_changelog",
						"buildID" => "20240313202508"
					];
					break;
				
				case 'linux-i686':
					return [
						"major" => false,
						"version" => "6.0.35",
						"detailsURL" => "https://www.zotero.org/support/6.0_changelog",
						"buildID" => "20240304070404"
					];
					break;
				
				case 'linux-x86_64':
					return [
						"major" => false,
						"version" => "6.0.35",
						"detailsURL" => "https://www.zotero.org/support/6.0_changelog",
						"buildID" => "20240304070404"
					];
					break;
			}
		}
		return false;
	}
	
	
	/**
	 * Return hard-coded update data for some versions
	 *
	 * Unlike getBuildOverride(), which specifies a version to use the existing update data from,
	 * this specifies the exact update data to use.
	 */
	private function getUpdateDataOverride($channel, $os, $fromVersion) {
		// Check for fixed updates
		if ($os == 'mac') {
			if ($channel == 'release') {
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
				return;
			}
			/*if ($channel == 'beta') {
				// Don't show updates past 6.0.27-beta.3 for 10.11 users
				if (isset($_SERVER['HTTP_USER_AGENT'])
						&& (strpos($_SERVER["HTTP_USER_AGENT"], "OS X 10.11;") !== false) {
					return [
						'type' => 'minor',
						'version' => '6.0.27-beta.3+3e12f3f20',
						'buildID' => '20230501021418',
						'detailsURL' => 'http://www.zotero.org/support/6.0_changelog',
						'patches' => [
							[
								'type' => 'complete',
								'URL' => $this->getBaseURI('beta', '6.0.27-beta.3+3e12f3f20') . 'Zotero-6.0.27-beta.3+3e12f3f20-full_mac.mar',
								'hashFunction' => 'SHA512',
								'hashValue' => '73d6790fde9f4bafdc6772a809695b109fc4ebc7e4490108a1e2025f5995c0f55401acbd365f2ccfca3299900f091704ce32c3f54572e64f43e3935ac3d642a4',
								'size' => 73649457
							]
						]
					];
				}
			}*/
		}
		
		return false;
	}
	
	
	private function getBuilds($channel, $platform) {
		if ($platform == 'win32-zip') {
			$platform = 'win32';
		}
		if ($platform == 'win-x64-zip') {
			$platform = 'win-x64';
		}
		if ($platform == 'win-arm64-zip') {
			$platform = 'win-arm64';
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
	
	
	private function hashAndSize($manifest, $file) {
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
	
	// TEMP
	private function str_starts_with(string $haystack, string $needle): bool {
		return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
	}
}
