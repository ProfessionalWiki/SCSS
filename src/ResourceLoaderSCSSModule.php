<?php
/**
 * File containing the ResourceLoaderSCSSModule class
 *
 * @copyright 2018, Stephan Gambke
 * @license   GNU General Public License, version 3 (or any later version)
 *
 * This file is part of the MediaWiki extension SCSS.
 * The SCSS extension is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * The SCSS extension is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup SCSS
 */

namespace SCSS;

use ResourceLoaderContext;


/**
 * ResourceLoader module based on local JavaScript/SCSS files.
 *
 * It recognizes the following additional fields in $wgResourceModules:
 * * styles: array of SCSS file names (with or without extension .scss)
 * * variables: array of key value pairs representing SCSS variables, that will
 *              be added to the SCSS script after all files imports, i.e. that
 *              may override any variable set in style files
 * * paths: array of paths to search for style files; all these paths together
 *              represent one virtual file base and will be searched for a style
 *              file; this means it is not possible to include two SCSS files
 *              with the same name even if in different paths
 *
 * @ingroup SCSS
 */
class ResourceLoaderSCSSModule extends \ResourceLoaderFileModule {

	protected $cache = null;

	protected $variables = [];
	protected $paths = [];
	protected $externalStyles = [];
	protected $cacheTriggers = [];

	protected $styleText = null;

	/**
	 * ResourceLoaderSCSSModule constructor.
	 *
	 *
	 * @param mixed[] $options
	 * @param null $localBasePath
	 * @param null $remoteBasePath
	 */
	public function __construct( $options = [], $localBasePath = null, $remoteBasePath = null ) {

		parent::__construct( $options, $localBasePath, $remoteBasePath );

		$this->applyOptions( $options );
	}

	/**
	 * @param mixed[] $options
	 */
	protected function applyOptions( $options ) {

		$mapConfigToLocalVar = [
			'variables' => 'variables',
			'paths' => 'paths',
			'external styles' => 'externalStyles',
			'cache triggers' => 'cacheTriggers',
		];

		foreach ( $mapConfigToLocalVar as $config => $local ) {
			if ( isset( $options[ $config ] ) ) {
				$this->$local = $options[ $config ];
			}
		}
	}

	/**
	 * Get the compiled Bootstrap styles
	 *
	 * @param ResourceLoaderContext $context
	 *
	 * @return array
	 */
	public function getStyles( ResourceLoaderContext $context ) {

		if ( $this->styleText === null ) {

			$this->retrieveStylesFromCache( $context );

			if ( $this->styleText === null ) {
				$this->compileStyles( $context );
			}
		}

		return [ 'all' => $this->styleText ];
	}

	/**
	 * @param ResourceLoaderContext $context
	 */
	protected function retrieveStylesFromCache( ResourceLoaderContext $context ) {

		// Try for cache hit
		$cacheResult = $this->getCache()->get( $this->getCacheKey( $context ) );

		if ( is_array( $cacheResult ) ) {

			if ( $this->isCacheOutdated( $cacheResult[ 'storetime' ] ) ) {
				wfDebug( __METHOD__ . " ext.bootstrap: Cache miss: Cache outdated.\n" );
			} else {
				$this->styleText = $cacheResult[ 'styles' ];
				wfDebug( __METHOD__ . " ext.bootstrap: Cache hit: Got styles from cache.\n" );
			}

		} else {
			wfDebug( __METHOD__ . " ext.bootstrap: Cache miss: Styles not found in cache.\n" );
		}
	}

	/**
	 * @return \BagOStuff|null
	 */
	protected function getCache() {

		if ( $this->cache === null ) {
			$this->cache = wfGetCache( CACHE_ANYTHING );
		}

		return $this->cache;
	}

	/**
	 * @since  1.0
	 *
	 * @param \BagOStuff $cache
	 */
	public function setCache( \BagOStuff $cache ) {
		$this->cache = $cache;
	}

	/**
	 * @param ResourceLoaderContext $context
	 *
	 * @return string
	 */
	protected function getCacheKey( ResourceLoaderContext $context ) {
		return wfMemcKey( 'ext', 'bootstrap', $context->getHash() );
	}

	/**
	 * @param int $cacheStoreTime
	 *
	 * @return bool
	 */
	protected function isCacheOutdated( $cacheStoreTime ) {

		foreach ( $this->cacheTriggers as $triggerFile ) {

			if ( $triggerFile !== null && $cacheStoreTime < filemtime( $triggerFile ) ) {
				return true;
			}

		}

		return false;
	}

	/**
	 * @param ResourceLoaderContext $context
	 */
	protected function compileStyles( ResourceLoaderContext $context ) {

		$scss = new \Leafo\ScssPhp\Compiler();
		$path = $this->getLocalPath( '' );
		$scss->setImportPaths( $path );

		// Allows inlining of arbitrary files regardless of extension, .css in particular
		$scss->addImportPath( function ( $path ) {

			if ( !file_exists( $path ) ) {
				return null;
			}

			return $path;
		} );

		// FIXME: What to do???
		// $remotePath = $this->getRemotePath( '' );

		try {

			$imports = '';

			foreach ( $this->styles as $style ) {
				$imports .= '@import "' . $style . '";';
			}

			foreach ( $this->externalStyles as $stylefile => $remotePath ) {

				if ( is_readable( $stylefile ) ) {
					$imports .= "@import \"" . $stylefile . "\";\n";
				} else {
					throw new \MWException( "External style file $stylefile is not readable." );
				}

			}

			$scss->setVariables( $this->variables );

			$this->styleText = $scss->compile( $imports );

			$this->updateCache( $context );

		} catch ( \Exception $e ) {

			$this->purgeCache( $context );
			wfDebug( $e->getMessage() );
			$this->styleText = '/* SCSS compile error: ' . $e->getMessage() . '*/';
		}

	}

	/**
	 * @param ResourceLoaderContext $context
	 */
	protected function updateCache( ResourceLoaderContext $context ) {

		$this->getCache()->set(
			$this->getCacheKey( $context ),
			[ 'styles' => $this->styleText, 'storetime' => time() ]
		);
	}

	/**
	 * @param ResourceLoaderContext $context
	 */
	protected function purgeCache( ResourceLoaderContext $context ) {
		$this->getCache()->delete( $this->getCacheKey( $context ) );
	}

	/**
	 * @see ResourceLoaderFileModule::supportsURLLoading
	 *
	 * @since  1.0
	 */
	public function supportsURLLoading() {
		return false;
	}

}
