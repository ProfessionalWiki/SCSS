# MediaWiki SCSS extension

[![Latest Stable Version](https://poser.pugx.org/mediawiki/scss/version.png)](https://packagist.org/packages/mediawiki/scss)
[![License](https://poser.pugx.org/mediawiki/scss/license)](https://packagist.org/packages/mediawiki/scss)

The MediaWiki SCSS extension provides a ResourceLoader module capable of compiling SCSS.

## Requirements

- [PHP][php] 5.6 or later
- [MediaWiki][mediawiki] 1.27 or later
- [Composer][composer]


## Installation

1. In the MediaWiki installation directory, add `"mediawiki/scss":"~1.0"` to the
   `require` section in the file `composer.local.json`.
   
2. Still in the MediaWiki installation directory, from a command line run<br>
   `composer update "mediawiki/scss"`.
   
3. Load the extension by adding the following line to `LocalSettings.php`:

	```php
	wfLoadExtension( 'Scss' );
	``` 
4. __Done:__ Navigate to _Special:Version_ page on your wiki to verify that the
   extension is successfully installed.

## Use

An SCSS module is defined much like any other style module. See the manual for
[$wgResourceModules](https://www.mediawiki.org/wiki/Manual:$wgResourceModules).
It should also be possible to add the module definition to the `extension.json`
of a MediaWiki extension. See
[Developing_with_ResourceLoader](https://www.mediawiki.org/wiki/ResourceLoader/Developing_with_ResourceLoader)

There are some additional keys, that may be used:
* `class`:
	This is mandatory. It selects the class to be used for the module. For
 	SCSS the value has to be `'SCSS\\ResourceLoaderSCSSModule'`
* `styles`:
	Not really an additional key, but it has extended semantics. This key
	contains the list of style files of the module. Each file can optionally be
	given a position to influence the order in which the files are compiled.
	Allowed values for the position are
	1. `beforeFunctions`
	2. `functions`
	3. `afterFunctions`
    4. `beforeVariables`
    5. `variables`
    6. `afterVariables`
    7. `beforeMain`
    8. `main`
    9. `afterMain`

	If no position is given, `main` will be assumed.

    All files of one module will be compiled together, i.e. variables, mixins
    etc. will be shared between them.
 
* `variables`:
	An array of variables and values to override the SCSS variables in the
	style files. This allows to change values (e.g. colors, fonts, margins)
	without having to modify the actual style files.
* `cacheTriggers`:
	Compiling SCSS is expensive, so sompiling results are cached. This option
	lists files that when changed will trigger a flushing of the cache and
	re-compiling the style files.
	
	All files on this list will be checked for each web request. To minimizs the
	load on the file system and the time to build the page it is not advisable
	to just add all style files to this list. 
 
Here is an example definition:
```php
$wgResourceModules[ 'ext.MyExtension.styles' ] = [

	'class' => 'SCSS\\ResourceLoaderSCSSModule',
	'localBasePath' => $localBasePath,
	'remoteBasePath' => $remoteBasePath,
	'position' => 'top',

	'styles' => [
		'modules/ext.MyExtension.foo.scss' => 'main',
		'modules/ext.MyExtension.bar.scss'
	],
	'variables' => [
		'red' => '#ff0000',
		'green' => '#00ff00',
		'blue' => '#0000ff',
	],
	'cacheTriggers' => [
		'LocalSettings.php',
		'composer.lock',
	],
];
```

The extension uses the [leafo/scssphp](https://github.com/leafo/scssphp)
compiler, which has some limitations. See the
[issue list](https://github.com/leafo/scssphp/issues).


### Cache type

`$egScssCacheType` can be set to request a specific cache type to be used for
the compiled styles. To disable caching of SCSS styles completely (e.g. during
development), set `$egScssCacheType = CACHE_NONE;`. This should obviously never
be done on a production site. 


## License

You can use the SCSS extension under the [GNU General Public License,
version 3][license] (or any later version).

[php]: https://php.net
[mediawiki]: https://www.mediawiki.org/wiki/MediaWiki 
[composer]: https://getcomposer.org/
[license]: https://www.gnu.org/copyleft/gpl.html
