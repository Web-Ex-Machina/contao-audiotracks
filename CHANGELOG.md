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
- CHANGED (privacy): visitors (likes, listening sessions) are no longer identified by their IP in clear. The IP is encrypted with the `wem.encryption_util` service of `webexmachina/contao-utils` (`ClientIdentifier`). The IPs already stored are encrypted by a migration (`contao:migrate`)
- ADDED: daily cron (`PurgeTrackingDataCron`) deleting the listening sessions not updated for `audio_tracks.retention_months` months (default `0` = keep forever, nothing is purged unless you configure it) and detaching the older likes from the visitor (the likes counters do not change)
- ADDED: unique key `pid, ip` on the feedbacks and sessions tables (one like / session per visitor and track), the AJAX endpoint handles parallel requests. The duplicates and the likes detached from their visitor are handled by `UniqueTrackingDataMigration`
- CHANGED: the `date` of a track is an integer column (`DateColumnMigration`, like the news), `start` and `stop` stay `varchar(10)` as in Contao
- CHANGED: the item templates of the modules are renamed by `TemplateNameMigration` (`wemaudiotrack_default` -> `audiotracks/item`, `wemaudiotrack_full` -> `audiotracks/item/full`), an unknown template falls back to the default one
- CHANGED: the palette of the tracks of a remote category is a real DCA palette (`remote`), `Category::getRssFeedUrl()` and `getRssFeedPath()` return `null` without RSS filename, `RssFeed` does not initialize the framework in its constructor anymore and uses `StringUtil::deserialize()`
- CHANGED (cache): the pages of the list and of the reader do not depend on the visitor anymore (no `liked`, no listening `session`, no request token in the HTML), so they can be cached and shared. The player loads the likes and the listening sessions from a private, never cached endpoint (`StateController`, `GET /_wem_audiotracks/state?ids=`), which also gives the request token used by the AJAX calls. The `liked` and `session` variables are no longer given to the item templates: custom templates and `WEMAUDIOTRACKSPARSEITEM` hooks that use them must be adapted
- CHANGED (performance): the remote RSS feeds are imported by an hourly cron (`SyncRemoteFeedsCron`) and no longer during a visitor request. A feed that fails is logged and does not stop the others. The "sync" button of the back end still works
- CHANGED (performance): `parseItems()` loads the categories, the files and the likes counters of all the items with a few queries (about 30 queries less for a list of 8 items), the target page is found once, the series of the JSON-LD is built once per category. The duration is no longer computed and saved during a page view: it is computed when a track is saved, and `DurationMigration` computes the missing ones
- FIXED: the episodes imported from a remote feed had no alias, a list linking to a reader page failed (500) for a remote category. The import generates a unique alias, and an item without alias is linked with its id
- FIXED: the pagination of the list was copied from the news module and used properties that do not exist on this module: "skip the first items" was ignored in the page count and the overall limit was wrong when combined with pages. It now uses the Contao `PaginationFactory` (the `page_n<id>` parameter is unchanged, an unknown page is a 404), only the items of the current page are loaded, and the template uses the `@Contao/component/_pagination.html.twig` component (the `pagination` variable of the template is now an object, no longer an HTML string)
- CHANGED: the player no longer waits for the state request (up to 5 s on a slow server): it is usable immediately and the listening sessions are applied when they arrive, without overriding the progress kept in the browser or the track the visitor already started. The token request is shared
- FIXED: the like counters of the cached pages could be stale (also in the schema.org `interactionStatistic`): the list and the reader are now tagged with `wem.audiotracks.likes.<trackId>`, and the tag is invalidated when a like is added or removed (front end or back end)
- FIXED: SQL values interpolated into the statements: the list search (keywords from the visitor, put in a `REGEXP` — a backslash or a regex character such as `(` broke the query) is now quoted and searched as text, the `pid` and `tags` filters are cast / quoted, and `syncData()` of the back end uses bound parameters. It also did not remove the tags when all of them were deleted from a track
- ADDED: rate limit on the endpoints of the player (likes, sessions, state): 120 requests per minute and per visitor by default, `429` + `Retry-After` beyond, configurable with `audio_tracks.rate_limit` (0 = no limit)
- ADDED: `audio_tracks.identifier: hmac` stores the visitors of the likes / sessions as a one-way keyed hash (HMAC-SHA256) instead of the reversible encryption. `contao:migrate` converts the visitors already stored (raw or encrypted); the opposite is not possible. The default stays `encryption`
- ADDED: Media Session API: the title, the episode and the cover are shown on the lock screen / notifications, and the headphone buttons and media keys control the player (play, pause, previous, next, seek, position). Ignored by browsers without the API
- ADDED: keyboard shortcuts of the player: Space / K play-pause, J / L ±10 s, ← / → ±5 s, ↑ / ↓ volume ±5 %, M mute, 0–9 jump to 0–90 %, Shift+N / Shift+P next / previous. They are ignored while typing in a field, with Ctrl / Alt / Meta, and Space keeps pressing the focused button
- FIXED: several modules on the same page: each one loaded the script (the player was set up several times, with duplicated listeners and several audio objects) and rendered its own player bar. There is now one player: the first bar is kept, the tracks of all the modules form one queue (a track shown twice is queued once), and all the rows of a track are updated (playing, started, listened)
- ADDED: accessibility of the player: the Play button says "Play" or "Pause" according to its state (the "Play / pause" label was replaced by two labels), the Like and Mute buttons expose `aria-pressed`, the seek bar reads "1:20 of 57:56" and the volume bar "50 %" (`aria-valuetext`), the episode that starts is announced to screen readers (`aria-live` region, not at each resume), and the controls have a visible keyboard focus ring (PressF5 theme)
- FIXED: the time of the player could display "00:60" (rounded seconds)
- ADDED: SEO of the reader: canonical URL of the episode (the alias URL, whether the page was reached by id, alias or with a query string; the schema.org `url` uses it too), Open Graph tags (`music.song` with image, audio, duration, release date, locale, site name) and a Twitter card. The meta description is limited to 300 characters
- FIXED: the reader erased the robots tag of the page with an empty value when the episode had no robots setting
- CHANGED: the remote import downloads the feed with a timeout (10 s without data, 30 s in total), a size limit (10 MB) and conditional requests (`ETag` / `Last-Modified`: an unchanged feed is not imported again, the "sync" button forces it). A failure (network, HTTP error, invalid feed) changes nothing and is reported clearly instead of being ignored. New columns on the categories: `pictureRemoteUrl`, `rssRemoteEtag`, `rssRemoteModified`
- ADDED: remote import: the url of the cover of the show (used by the episodes without cover and by the schema.org series), the authors of the episodes (`author`, `dc:creator`, `itunes:author`), the tags (`category`, `itunes:keywords`, also added to the tags of the show and to the pivot table), the owner in the formats "email (name)", "name <email>", "email" or "name"
- FIXED: a feed without `copyright` / `language` / `link` / `podcast:type` made the import fail (NOT NULL column). The email and the url of the authors of a track are no longer mandatory in the back end (imported authors have none)
- CHANGED: `RssFeed::generate()` no longer uses the back-end `Message` class (it was called from a service, the cron or a command could not use it): it returns `true` when the feed was written, `false` when there is nothing to generate, and throws `FeedWithoutTracksException` when the category has no published track. The back end displays the messages. The "RSS saved" confirmation is no longer shown when nothing was generated (feed not enabled, or no track)
- ADDED: PHPUnit tests (`tests/`): `ClientIdentifier`, the helpers of `SchemaOrgBuilder` and the migrations (against a throwaway MySQL database)
- CHANGED: `ecs.php` was written for an old version of ECS and did not run anymore, it now uses the Contao coding standard; the code was reformatted accordingly, Rector (code quality, dead code, type declarations) was applied
- FIXED: `MP3File::getDuration()` used an undefined variable, wrong PHPDoc types in `RssFeed`
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