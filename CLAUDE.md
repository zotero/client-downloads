# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is the Zotero client download and update server (PHP). It handles three concerns:

1. **Download redirects** (`dl.php`) -- Redirects users to the correct installer file (DMG, EXE, tar.bz2/xz, ZIP) based on platform, channel, and version.
2. **Update checks** (`update.php`) -- Serves Mozilla-style update XML responses so Zotero clients can check for and apply updates (full and incremental MARs).
3. **Version checks** (`version.php`) -- Returns the latest available version for a channel/platform (or all platforms) without triggering download stats. Used by the download page and third-party packagers so they honor `restrictedVersions` gating instead of reading `updates-*.json` directly.

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

- `manifests/{channel}/updates-{platform}.json` -- JSON arrays of available builds (version, buildID, detailsURL). Updated by the `deploy` script, which keeps the last 5 builds plus the latest build of each major. The latest build is determined by version, not position, so a point release of an earlier major can be deployed after a later major.
- `manifests/{channel}/{version}/files-{mac,win,linux}` -- MAR file listings with SHA-512 hashes and sizes. Uploaded by the build process.
- `manifests/{channel}/{version}/build-{os}.json` -- Build ID and details URL per platform. Uploaded by the build process.
- `manifests/{channel}/incrementals-{platform}` -- Lists of deployed versions used to generate incremental updates. Updated by the `deploy` script.
- `manifests/{channel}/pinned-incrementals-{platform}` -- Versions that always get incremental MARs built, regardless of the normal incrementals window (e.g., last version of each major release).
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
    "mac": "10.0.8",
    "win": "10.0.8",
    "linux": "10.0.8",
    "bypassPercent": 5,
    "from7": {
      "mac": "7.0.32",
      "win": "7.0.32",
      "linux": "7.0.32"
    }
  },
  "restrictedVersions": ["11.0"]
}
```

- Top-level platform keys -- Caps auto-updates at the specified version per platform for all clients whose major version is at or below the cap's major version (e.g., a 10.0.8 cap applies to 7.x–10.x clients but not 11.x)
- Cap values can be a full version ("10.0.8") or a bare major version ("10"). A bare major resolves to the latest allowed build of that major in `updates-{platform}.json`, so point releases of the previous major deployed during a rollout are picked up automatically. If no build of that major is found, no update is served.
- `from{N}` -- (optional) Overrides the top-level cap for clients on major version N
- Manual updates (`?force=1`) always bypass caps and get the latest version
- `bypassPercent` -- (optional) Allow this percentage of auto-update clients through the cap. 100 uncaps everyone, so a full release can leave the cap in place.
- Remove a cap to uncap auto-updates (removing a `from{N}` entry falls back to the top-level cap)
- `restrictedVersions` -- Versions only served to test IPs (from `$TEST_IPS` in `config.inc.php`). Entries can be exact versions ("9.0.1") or prefixes ("9.0") matching any version starting with that prefix. Affects both downloads and updates.

## Configuration

Copy `config.inc.php-sample` to `config.inc.php`. Sets `$HOST` (download base URL) and optional StatsD connection.

`$DEPLOY_CMD` in `config.inc.php` sets the command to run after staging to push changes live.
