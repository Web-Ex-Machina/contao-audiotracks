Extension "Audiotracks" for Contao Open Source CMS
========

2.0.0
---
- ADDED: Contao 5.7 compatibility
- ADDED: Design overall
- ADDED: English translations
- REMOVED: Contao 4.13 compatibility
- FIXED: AJAX handling (likes & session sync) was never reached; it now lives in a dedicated frontend route (`wem_audiotracks_ajax`, `POST /_wem_audiotracks/{feedback|syncSession}`) protected by the Contao request token
- FIXED: Custom hooks (`WEMAUDIOTRACKS*`) called non-existing `importStatic()`/`import()` methods
- FIXED: Missing `Exception` import in `AudiotrackContainer`, wrong DCA reference in list filters
- UPDATED: Declared controller properties (no more dynamic properties), `generateBreadcrumb` registered with `#[AsHook]`, SVG icons for backend operations
- ADDED: Twig templates (`frontend_module/wem_audiotracks_*.html.twig`, `audiotracks/item.html.twig`, `audiotracks/item/full.html.twig`, `audiotracks/_player.html.twig`). Legacy `wemaudiotrack_*` template selections are mapped automatically; custom `.html5` overrides must be ported
- UPDATED: The player is now vanilla JS (`public/audiotracks.js`), jQuery is no longer required
- CHANGED: `WEMAUDIOTRACKSPARSEITEM` now receives and returns the template data array (see docs/HOOKS.md)
- ADDED: schema.org JSON-LD (`SchemaOrgBuilder`): `PodcastEpisode` with `PodcastSeason`, `PodcastSeries` (category), `AudioObject`, authors, keywords, duration, likes on the reader; a single `ItemList` on the list. The inline microdata has been removed. The data is available in the item template data as `schemaOrg` (and can be altered in the `WEMAUDIOTRACKSPARSEITEM` hook)
- UPDATED: Requires PHP ^8.3 and `contao/core-bundle` ^5.7

1.1.0
---
- PHP 8.2 Compatibility
- Contao 5.3 compatibility
- Feat: Improve load time with audiotrack duration calculation and storage
- Feat: Allow user to download audiotrack in the module settings
- Feat: Sound level is now global and will be kept between audiotracks
- Feat: System will display a confirm box if the tab/window is closed when a file is currently playing
- Feat: You can now generate a public RSS Feed for each category

1.0.3 - 2023-08-16
---
- ADDED : content in README [issue #5](https://github.com/Web-Ex-Machina/contao-audiotracks/issues/5)
- UPDATED : bundle now requires [webexmachina/contao-utils](https://github.com/Web-Ex-Machina/contao-utils) ^1.0

1.0.2 - 2023-08-08
---
- ADDED : better interaction with `marcel-mathias-nolte/contao-filesmanager-fileusage`'s bundle
- FIXED : wrong `issues` & `source` URLs in `composer.json` [issue #4](https://github.com/Web-Ex-Machina/contao-audiotracks/issues/4#issue-1841268970)

1.0.1 - 2023-08-08
---
- FIXED : audiotrack "like" button not working as intended

1.0.0 - 2023-08-07
---
First release

1.0.0-rc1 - 2023-07-25
---
- ADDED : documentation for our hooks
- ADDED : documentation for using PHPStan
- UPDATED : compatibility with PHP 8
- FIXED : `WEMAUDIOTRACKSPARSEITEM` hook