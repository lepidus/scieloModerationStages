# SciELO Moderation Stages Plugin 

This plugin adds stages of moderation to OPS, giving moderators the possibility to better manage the moderation process of preprints.

## Compatibility

The latest release of this plugin is compatible with the following PKP applications:

* OPS 3.5.0

For OPS 3.4.0, use the previous releases of this plugin (`2.x`).

## Plugin Download

To download the plugin, go to the [Releases page](https://github.com/lepidus/scieloModerationStages/releases) and download the tar.gz package of the latest release compatible with your website.

## Installation

1. Enter the administration area of ​​your OJS/OPS website through the __Dashboard__.
2. Navigate to `Settings`>` Website`> `Plugins`> `Upload a new plugin`.
3. Under __Upload file__ select the file __scieloModerationStages.tar.gz__.
4. Click __Save__ and the plugin will be installed on your website.

## Development

From OPS 3.5.0, the moderation workflow tab, the workflow header indicator and the
dashboard column are built with Vue.js. After changing any file under `resources/js`,
rebuild the bundle (run inside the plugin directory):

```bash
npm install
npm run build
```

This generates `public/build/`, which is committed and shipped with the release.

## Limitations on OPS 3.5.0

* The current moderation stage indicator in the workflow header is rendered in the
  header actions area (next to *Preview / Activity Log / Library*), because the status
  badge slot is not extensible in OPS 3.5. The same information is also shown in the
  moderation tab.


# License
__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2022 - 2024 Lepidus Tecnologia__

__Copyright (c) 2022 - 2024 SciELO__
