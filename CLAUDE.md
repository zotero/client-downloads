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
  - `getBuildOverride()` -- Hard-coded version caps for old OS versions (e.g., max 5.0.77 for Vista, max 7.0.32 for Windows <10, max 6.0.37 for macOS 10.9-10.11)
  - `getUpdateDataOverride()` -- Hard-coded full update data for specific legacy transitions
- `lib/ToolkitVersionComparator.inc.php` -- PHP port of Mozilla's version comparator (used for semver-like version comparisons throughout)
- `lib/bootstrap.inc.php` -- Loads config, autoloader, and optional StatsD client

## Manifests

The `manifests/` directory contains per-channel (release, beta, dev) data:

- `manifests/{channel}/updates-{platform}.json` -- JSON arrays of available builds (version, buildID, detailsURL)
- `manifests/{channel}/{version}/files-{mac,win,linux}` -- MAR file listings with SHA-512 hashes and sizes

These are managed by external build tooling (see README.md), not by this codebase.

## Configuration

Copy `config.inc.php-sample` to `config.inc.php`. Sets `$HOST` (download base URL) and optional StatsD connection.
