# Hooks for package `contao-audiotracks`

This file list all available hooks in this package.

## List

| name | description |
--- | ---
| `WEMAUDIOTRACKSLISTFILTERS` | Called when generating filters in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. Returns an array with the filters configuration.
| `WEMAUDIOTRACKSLISTCONFIG` | Called when generating list configuration in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. Returns an array with the list configuration.
| `WEMAUDIOTRACKSLISTOPTIONS` | Called when generating list options in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. Returns an array with the options configuration.
| `WEMAUDIOTRACKSPARSEITEM` | Called when parsing an item in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. Returns the template.


## Details

### WEMAUDIOTRACKSLISTFILTERS

This hook is called when generating filters in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. 

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$filters | `array` | Array of filters
$caller | `\WEM\AudioTracksBundle\Controller\Frontend\ListController` | The calling object

**Code**:
```php
public function buildFilters(
	array $filters, 
	\WEM\AudioTracksBundle\Controller\Frontend\ListController $caller
): array
{
	// alter filters configuration here
	return $filters;
}
```

### WEMAUDIOTRACKSLISTCONFIG

This hook is called when generating list configuration in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. 

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$config | `array` | Array of list configuration
$caller | `\WEM\AudioTracksBundle\Controller\Frontend\ListController` | The calling object

**Code**:
```php
public function buildListConfiguration(
	array $config, 
	\WEM\AudioTracksBundle\Controller\Frontend\ListController $caller
): array
{
	// alter list configuration here
	return $config;
}
```


### WEMAUDIOTRACKSLISTOPTIONS

This hook is called when generating list options in the `wem_audiotracks_list` / `wem_audiotracks_reader` modules. 

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$options | `array` | Array of list options
$caller | `\WEM\AudioTracksBundle\Controller\Frontend\ListController` | The calling object

**Code**:
```php
public function buildListConfiguration(
	array $options, 
	\WEM\AudioTracksBundle\Controller\Frontend\ListController $caller
): array
{
	// alter list options here
	return $options;
}
```

### WEMAUDIOTRACKSPARSEITEM

This hook is called when parsing an item. Since the Twig migration, it receives the data array given to the item template (`audiotracks/item.html.twig`) instead of a `FrontendTemplate`.

**Return value** : `array`

**Arguments**:
Name | Type | Description
--- | --- | ---
$data | `array` | The item template data (DB row + `date`, `picture`, `audio`, `duration`, `nbLikes`, `canDownload`, `jumpTo`...). Nothing that depends on the visitor (liked, listening session) is in it, so the pages can be cached
$item | `\WEM\AudioTracksBundle\Model\AudioTrack` | The item
$caller | `\WEM\AudioTracksBundle\Controller\Frontend\ModuleController` | The calling object

**Code**:
```php
public function parseItem(
	array $data,
	\WEM\AudioTracksBundle\Model\AudioTrack $item,
	\WEM\AudioTracksBundle\Controller\Frontend\ModuleController $caller
): array
{
	// alter data here
	return $data;
}
```
