# Hooks for package `contao-audiotracks`

This file list all available hooks in this package.

## List

| name | description |
--- | ---
| `WEMAUDIOTRACKSLISTFILTERS` | Called when generating filters in the `AudioTrackList` module. Returns an array with the filters configuration.
| `WEMAUDIOTRACKSLISTCONFIG` | Called when generating list configuration in the `AudioTrackList` module. Returns an array with the list configuration.
| `WEMAUDIOTRACKSLISTOPTIONS` | Called when generating list options in the `AudioTrackList` module. Returns an array with the options configuration.
| `WEMAUDIOTRACKSPARSEITEM` | Called when parsing an item in the `AudioTrackList` module. Returns the template.


## Details

### WEMAUDIOTRACKSLISTFILTERS

This hook is called when generating filters in the `AudioTrackList` module. 

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$filters | `array` | Array of filters
$caller | `\WEM\AudioTracksBundle\Module\AudioTracksList` | The calling object

**Code**:
```php
public function buildFilters(
	array $filters, 
	\WEM\AudioTracksBundle\Module\AudioTracksList $caller
): array
{
	// alter filters configuration here
	return $filters;
}
```

### WEMAUDIOTRACKSLISTCONFIG

This hook is called when generating list configuration in the `AudioTrackList` module. 

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$config | `array` | Array of list configuration
$caller | `\WEM\AudioTracksBundle\Module\AudioTracksList` | The calling object

**Code**:
```php
public function buildListConfiguration(
	array $config, 
	\WEM\AudioTracksBundle\Module\AudioTracksList $caller
): array
{
	// alter list configuration here
	return $config;
}
```


### WEMAUDIOTRACKSLISTOPTIONS

This hook is called when generating list options in the `AudioTrackList` module. 

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$options | `array` | Array of list options
$caller | `\WEM\AudioTracksBundle\Module\AudioTracksList` | The calling object

**Code**:
```php
public function buildListConfiguration(
	array $options, 
	\WEM\AudioTracksBundle\Module\AudioTracksList $caller
): array
{
	// alter list options here
	return $options;
}
```

### WEMAUDIOTRACKSPARSEITEM

This hook is called when parsing an item in the `AudioTrackList` module. 

**Return value** : `\Contao\FrontendTemplate`

**Arguments**:
Name | Type | Description
--- | --- | ---
$template | `\Contao\FrontendTemplate` | The template
$caller | `\WEM\AudioTracksBundle\Module\AudioTracksList` | The calling object

**Code**:
```php
public function parseItem(
	\Contao\FrontendTemplate $template, 
	\WEM\AudioTracksBundle\Module\AudioTracksList $caller
): \Contao\FrontendTemplate
{
	// alter template here
	return $template;
}
```

