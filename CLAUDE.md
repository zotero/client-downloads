# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is the Zotero client download and update server (PHP). It handles two concerns:

1. **Download redirects** (`dl.php`) -- Redirects users to the correct installer file (DMG, EXE, tar.bz2/xz, ZIP) based on platform, channel, and version.
2. **Update checks** (`update.php`) -- Serves Mozilla-style update XML responses so Zotero clients can check for and apply updates (full and incremental MARs).

## Running Tests

Tests use Mocha (Node.js) and run against a local PHP built-in server on port 12562.

```bash
# Run all tests
npm test

# Run a single test file
test/run_tests test/dl_test.js
test/run_tests test/update_test.js

# Run a specific test by name
test/run_tests --grep "should offer 8.0 for Mac"
```

The `test/run_tests` script starts `php -S localhost:12562`, runs mocha, and tears down the server on exit. A `config.inc.php` file (copied from `config.inc.php-sample`) must exist.

## Architecture

- `lib/ClientDownloads.inc.php` -- Core logic. The `ClientDownloads` class reads JSON manifests and handles version comparison, OS/version gating, and update XML generation. Key methods:
  - `getUpdates()` / `getUpdatesXML()` -- Determine which update to serve for a given client
  - `getBuildVersion()` -- Get latest version for a channel/platform (used by `dl.php`)
  - `getVersionOverride()` -- Version caps: permanent OS-version caps (e.g., max 5.0.77 for Vista) are hard-coded; major-version auto-update caps are config-driven via `update-policy.json`
  - `getAutoUpdateCap()` -- Reads `manifests/{channel}/update-policy.json` to determine auto-update caps by source major version and platform
  - `getUpdateDataOverride()` -- Hard-coded full update data for specific legacy transitions
- `lib/ToolkitVersionComparator.inc.php` -- PHP port of Mozilla's version comparator (used for semver-like version comparisons throughout)
- `lib/bootstrap.inc.php` -- Loads config, autoloader, and optional StatsD client

## Manifests

The `manifests/` directory contains per-channel (release, beta, dev) data:

- `manifests/{channel}/updates-{platform}.json` -- JSON arrays of available builds (version, buildID, detailsURL). Updated by the `deploy` script.
- `manifests/{channel}/{version}/files-{mac,win,linux}` -- MAR file listings with SHA-512 hashes and sizes. Uploaded by the build process.
- `manifests/{channel}/{version}/build-{os}.json` -- Build ID and details URL per platform. Uploaded by the build process.
- `manifests/{channel}/incrementals-{platform}` -- Lists of deployed versions used to generate incremental updates. Updated by the `deploy` script.
- `manifests/{channel}/update-policy.json` -- (optional) Auto-update caps by source major version. See "Update Policy" below.

## Deploy

The `deploy` script makes a previously built version live. It runs on the deploy server:

```bash
./deploy <channel> <version> <platforms>
# e.g.: ./deploy release 9.0.1 mwl
```

It updates `updates-{platform}.json`, appends to `incrementals-{platform}`, and runs `$DEPLOY_CMD` from `config.inc.php` if configured. For beta/dev channels, this is called automatically by the build scripts via SSH.

## Update Policy

`manifests/release/update-policy.json` controls auto-update gating for major releases:

```json
{
  "autoUpdateCap": {
    "from8": {
      "mac": "8.0.5",
      "win": "8.0.4",
      "linux": "8.0.4",
      "bypassPercent": 5
    }
  }
}
```

- `from{N}` -- Caps auto-updates for clients on major version N to the specified version per platform
- Manual updates (`?force=1`) always bypass caps and get the latest version
- `bypassPercent` -- (optional) Allow this percentage of auto-update clients through the cap
- Remove an entry to uncap auto-updates for that major version

## Configuration

Copy `config.inc.php-sample` to `config.inc.php`. Sets `$HOST` (download base URL) and optional StatsD connection.

`$DEPLOY_CMD` in `config.inc.php` sets the command to run after staging to push changes live.
