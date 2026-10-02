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
