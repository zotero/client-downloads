## Manifests

Current versions and updates are specified by the following files in the 'manifests' directory:

### manifests/{release,beta,dev}/updates-{mac,win32,linux-i686,linux-x86_64}.json

```
[
  {
    "major": false,
    "version": "5.0-beta.209+98544edde",
    "detailsURL": "https://www.zotero.org/support/5.0_changelog",
    "buildID": "20170608144058"
  }
]
```

Managed by [add_version_info](https://github.com/zotero/zotero-standalone-build/blob/master/update-packaging/add_version_info)

### manifests/{release,beta,dev}/{version}/files-{mac,win,linux}

```
Zotero-5.0-beta.200+182cf67-5.0-beta.197+eb42152_mac.mar a06999d3fc5cdd92a0d2ec501f511823d09554d6a6a8efccf74257a3f4f2e843a19f1de4ae178c875548d82258adf2ee85bb65911eef00e0a85acaf55b152999 50216
Zotero-5.0-beta.200+182cf67-5.0-beta.198+f12ae67_mac.mar 9c9ea8f5e01fe304dbbddb186c8bd71f33912d873e98c1a3927a5cfc3f31c934279b224bac73b6bbfd40a2d8d6d0e9954b39f8cf648a3e945aeab6f210c3bc63 58177
Zotero-5.0-beta.200+182cf67-5.0-beta.199+182cf67_mac.mar ccc50eb8f695c98496d84ca7e62aaab037bd21bbf9f0fcdbe117ebdd64963850dc3c1268c56981002b236865156f83c49d6ed4c06c2b6f1b6590d2af0097fce2 40509
Zotero-5.0-beta.200+182cf67-full_mac.mar 3bc3cb56c5793b79ce877e529c06ce9b8f2912b2682d65591c87b3d43c518c6bf070dbd611cbe057c0087fef6d7f0216cb2774f847673a9f55ac08833b55bdfc 77371740
```

Created by [build_autoupdate.sh](https://github.com/zotero/zotero-standalone-build/blob/master/update-packaging/build_autoupdate.sh)

Overrides can be specified in `getBuildOverride()` (to override the updates.json file) or `getUpgradeOverride()` (to override both files) in `lib/ClientDownloads.inc.php`.
