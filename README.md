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

A daily cron can limit the retention: it deletes the listening sessions not updated for the configured number of months, and detaches the older likes from the visitor (the counters do not change). **It is disabled by default**, so nothing is deleted until you choose a value (12 is a common one):

```yaml
# config/config.yaml
audio_tracks:
    retention_months: 12   # 0 = keep forever (default)
```

After an update from a previous version, run `contao:migrate`: it encrypts the IP addresses already stored, removes the duplicated likes / sessions, converts the `date` column, renames the module templates and creates the unique keys.

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

## Development

```bash
composer install
vendor/bin/phpunit          # the migration tests need a MySQL server, see AUDIOTRACKS_TEST_DSN in tests/Migration/DatabaseTestCase.php
vendor/bin/ecs check
vendor/bin/rector process --dry-run
```
