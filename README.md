# Audiotracks for Contao CMS

This bundle allows you to display audio files and let users play them directly in their browser. Like the News system, they are sorted by feeds, so you can have several lists, merged or not, on your website.

In the category, you can add tags, to categorize the category items. Filters can be added to the list to help the navigation.

For each item, you can specify a title, a date, a description, some tags, pictures and publishing settings.

In the list module, you can select the categories you want to display, the filters to used, pagination and templates settings, like most of Contao list modules.

Let us know if we should add anything in this bundle!

## Install
You should install this bundle from Composer with the command below

`composer require webexmachina/contao-audiotracks`

Or use the Contao Manager to do the same thing if you do not have a command-line access.

## Privacy

Likes and listening sessions are linked to a visitor, but the IP address is never stored in clear: it is encrypted with the `wem.encryption_util` service of [webexmachina/contao-utils](https://github.com/Web-Ex-Machina/contao-utils). The encryption key is `wem_contao_encryption.encryption_key` (`kernel.secret` by default), **if you change it, the visitors already stored are not recognized anymore** (their likes / sessions are not lost, but they cannot be matched to their owner).

By default the IP address is encrypted, which is reversible with the key. If you want that nobody, even with the key, can get the IP back, switch to a one-way keyed hash (HMAC-SHA256, same key). Likes and sessions keep working, a visitor is still recognized. Run `contao:migrate` afterwards, it converts the visitors already stored. **This cannot be reverted** (a hash cannot be reversed): going back to `encryption` leaves the converted visitors unrecognized.

```yaml
# config/config.yaml
audio_tracks:
    identifier: hmac   # encryption (default) | hmac
```

A daily cron can limit the retention: it deletes the listening sessions not updated for the configured number of months, and detaches the older likes from the visitor (the counters do not change). **It is disabled by default**, so nothing is deleted until you choose a value (12 is a common one):

```yaml
# config/config.yaml
audio_tracks:
    retention_months: 12   # 0 = keep forever (default)
```

After an update from a previous version, run `contao:migrate`: it encrypts the IP addresses already stored, removes the duplicated likes / sessions, converts the `date` column, renames the module templates and creates the unique keys.

## RSS feeds of the local categories

A feed is generated when a category or a track is saved in the back end. It is also generated again **every hour** by the Contao cron (`GenerateFeedsCron`): without it, a track published or unpublished by its start / stop date, or deleted, would not be taken into account until the next save. A feed that did not change is not rewritten (the dates of the feed are the ones of its last change, not "now").

- **Back end**: the "Generate all RSS feeds" button of the list of the categories.
- **Command line**: `php vendor/bin/contao-console wem:audiotracks:generate-feeds` (exit code 1 if a feed failed).

The feeds contain absolute URLs. During a request they use the address of the request, but the cron (if the system cron calls `contao:cron`) and the command have no request. The address is then the domain of the root pages if they all have the same one, else **you have to give it**:

```yaml
# config/config.yaml
audio_tracks:
    base_url: https://www.example.org/   # a "%env(AUDIOTRACKS_BASE_URL)%" placeholder is accepted
```

If it is needed and missing, the generation fails with a message saying so, nothing wrong is written. When this setting is given, it is also used for the requests (the address of the feeds does not depend on the domain used to open the back end).

## Rate limit

The endpoints used by the player (likes, listening sessions and state of the visitor) accept 120 requests per minute and per visitor, the player sends about 6 per minute while it plays. Beyond that, they answer with a `429` and a `Retry-After` header. The limit can be changed, `0` disables it:

```yaml
# config/config.yaml
audio_tracks:
    rate_limit: 120   # requests per minute and per visitor, 0 = no limit
```

## Cache and remote feeds

- The pages of the list and of the reader are the same for every visitor, so Contao's page cache (or a reverse proxy) can serve them. What depends on the visitor (the likes he made, where he stopped listening) is loaded by the player after the page is displayed, from `GET /_wem_audiotracks/state?ids=…`, a private response that is never cached. The like counters are rendered in the page and refreshed by the player.
- The remote RSS feeds are imported every hour by the Contao cron (`SyncRemoteFeedsCron`). If you do not use the poor man's cron, make sure the Contao cron is called by the system cron (`contao:cron`). The "sync" button in the category list imports a feed immediately.
- The remote import is careful with the remote server: the feed is downloaded with a timeout (10 s without data, 30 s in total) and a size limit (10 MB), and an `ETag` / `Last-Modified` request is sent so an unchanged feed is not downloaded nor imported again. If the download fails or the content is not a feed, nothing is changed (the error is logged by the cron and displayed by the "sync" button, which always downloads the feed).
- What is imported: the show (title, description, language, owner, categories, the url of its cover), and the episodes (title, date, season / episode, duration, audio, the url of the cover, authors from `author` / `dc:creator` / `itunes:author`, tags from the `category` and `itunes:keywords` elements). Images are not downloaded, the remote url is used; an episode without cover shows the cover of the show. Authors and tags are only replaced when the feed gives some, so the ones you entered are kept otherwise. The tags are also added to the tags of the show.
- After an update, run `contao:migrate` (or the schema update): three columns were added to the categories.

## Development

```bash
composer install
vendor/bin/phpunit          # the migration tests need a MySQL server, see AUDIOTRACKS_TEST_DSN in tests/Migration/DatabaseTestCase.php
vendor/bin/ecs check
vendor/bin/rector process --dry-run
```
