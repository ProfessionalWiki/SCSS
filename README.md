# MediaWiki SCSS library

[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/ProfessionalWiki/SCSS/ci.yml?branch=master)](https://github.com/ProfessionalWiki/SCSS/actions?query=workflow%3ACI)
[![Latest Stable Version](https://poser.pugx.org/mediawiki/scss/version.png)](https://packagist.org/packages/mediawiki/scss)
[![License](https://poser.pugx.org/mediawiki/scss/license)](https://packagist.org/packages/mediawiki/scss)

The MediaWiki SCSS library provides a ResourceLoader module capable of compiling [SCSS].

## Requirements

- [PHP] 8.0 or later
- [MediaWiki] 1.39 or later
- [Composer]

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
	style files. This allows changing values (e.g. colors, fonts, margins)
	without having to modify the actual style files.
* `cacheTriggers`:
	Compiling SCSS is expensive, so compiled results are cached. This option
	lists files that when changed will trigger a flushing of the cache and
	re-compiling the style files.

	All files on this list will be checked for each web request. To minimizes the
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

The extension uses the [scssphp/scssphp](https://github.com/scssphp/scssphp)
compiler, which has some limitations. See the
[issue list](https://github.com/scssphp/scssphp/issues).


### Cache type

`$egScssCacheType` can be set to request a specific cache type to be used for
the compiled styles. To disable caching of SCSS styles completely (e.g. during
development), set `$egScssCacheType = CACHE_NONE;`. This should obviously never
be done on a production site.

## Professional Support

The SCSS extension is maintained by [Professional.Wiki](https://professional.wiki).
You can [contract us][contact-form] to help you with installation or customization of SCSS.
We also do development work.

## Running the tests

The tests can only be run when the library is loaded within MediaWiki.
You can add it via `composer.local.json` (probably using `dev-master`).

Inside your MediaWiki root directory

    php tests/phpunit/phpunit.php -c vendor/mediawiki/scss/phpunit.xml.dist

## License

You can use the SCSS extension under the [GNU General Public License,
version 3][license] (or any later version).

[PHP]: https://php.net
[MediaWiki]: https://www.mediawiki.org/wiki/MediaWiki
[Composer]: https://getcomposer.org/
[license]: https://www.gnu.org/copyleft/gpl.html
[SCSS]: https://en.wikipedia.org/wiki/Sass_(stylesheet_language)
[contact-form]: https://professional.wiki/en/contact

## Release notes

### Version 4.0.0

Released on 2024-04-25

* Raised minimum MediaWiki version from 1.35 to 1.39
* Raised minimum PHP version from 7.4.3 to 8.0
* Raised minimum `scssphp` version from 1.10.2 to 1.12.1
* Modernized coding standards

### Version 3.0.1

Released on 2022-07-25

* Added preliminary compatibility with MediaWiki 1.39

### Version 3.0.0

Released on 2022-03-30

* Raised minimum MediaWiki version from 1.27 to 1.35
* Raised minimum PHP version from 5.6 to 7.4.3
* Raised minimum `scssphp` version from 1.5.2 to 1.10.2

### Version 2.0.8

Released on 2022-07-25

* Added preliminary compatibility with MediaWiki 1.39

### Version 2.0.7

Released on 2021-05-18

* Increased minimum version of `scssphp`

### Version 2.0.6

Released on 2021-05-15

* Temporarily limit the version of `scssphp` below 1.5 to avoid breaking changes

### Version 2.0.5

Released on 2020-12-27

* Added compatibility with MediaWiki 1.36

### Version 2.0.4

Released on 2020-09-07

* Switched to new version of `scssphp`

### Version 2.0.3

Released on 2020-09-07

* Temporarily fixed the version of `scssphp` to 1.1.1 to avoid 1.2 bug

### Version 2.0

Released on 2020-04-19

* Turned MediaWiki extension into a standard PHP/Composer library
* Switched to new version of `scssphp`

### Version 1.0

Initial release
