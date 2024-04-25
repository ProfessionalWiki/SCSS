<?php
/**
 * File containing the ResourceLoaderSCSSModule class
 *
 * @copyright 2018 - 2019, Stephan Gambke
 * @license   GPL-3.0-or-later
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

use BagOStuff;
use CSSJanus;
use Exception;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;
use MediaWiki\ResourceLoader\FilePath;
use ObjectCache;
use ScssPhp\ScssPhp\Compiler;

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
class ResourceLoaderSCSSModule extends FileModule {

	/**
	 * @var string[]
	 */
	private array $styleModulePositions = [
		'beforeFunctions', 'functions', 'afterFunctions',
		'beforeVariables', 'variables', 'afterVariables',
		'beforeMain', 'main', 'afterMain',
	];

	private ?BagOStuff $cache = null;
	private ?string $cacheKey = null;

	/**
	 * @var array<string, string>
	 */
	protected array $variables = [];

	/**
	 * @var string[]
	 */
	protected array $paths = [];

	/**
	 * @var string[]
	 */
	protected array $cacheTriggers = [];

	protected ?string $styleText = null;

	/**
	 * ResourceLoaderSCSSModule constructor.
	 *
	 * @param mixed[] $options
	 * @param string|null $localBasePath
	 * @param string|null $remoteBasePath
	 */
	public function __construct( array $options = [], ?string $localBasePath = null, ?string $remoteBasePath = null ) {
		parent::__construct( $options, $localBasePath, $remoteBasePath );

		$this->applyOptions( $options );
	}

	/**
	 * @param mixed[] $options
	 */
	protected function applyOptions( array $options ): void {
		$this->variables = $options['variables'] ?? [];
		$this->paths = $options['paths'] ?? [];
		$this->cacheTriggers = $options['cacheTriggers'] ?? [];
	}

	/**
	 * Get the compiled SCSS styles
	 *
	 * @return string[]
	 */
	public function getStyles( Context $context ): array {
		if ( $this->styleText === null ) {
			$this->retrieveStylesFromCache( $context );
		}

		if ( $this->styleText === null ) {
			$this->compileStyles( $context );
		}

		return [ 'all' => $this->styleText ?? '' ];
	}

	protected function retrieveStylesFromCache( Context $context ): void {
		// Try for cache hit
		$cacheKey = $this->getCacheKey( $context );
		$cacheResult = $this->getCache()->get( $cacheKey );

		if ( is_array( $cacheResult ) ) {
			if ( $this->isCacheOutdated( (int)$cacheResult[ 'storetime' ] ) ) {
				wfDebug( "SCSS: Cache miss for {$this->getName()}: Cache outdated.\n", 'private' );
			} else {
				$this->styleText = (string)$cacheResult[ 'styles' ];
				wfDebug( "SCSS: Cache hit for {$this->getName()}: Got styles from cache.\n", 'private' );
			}
		} else {
			wfDebug( "SCSS: Cache miss for {$this->getName()}: Styles not found in cache.\n", 'private' );
		}
	}

	protected function getCache(): BagOStuff {
		if ( $this->cache === null ) {
			$this->cache = ObjectCache::getInstance( $this->getCacheType() );
		}

		return $this->cache;
	}

	private function getCacheType(): int {
		return array_key_exists( 'egScssCacheType', $GLOBALS ) ? (int)$GLOBALS[ 'egScssCacheType' ] : -1;
	}

	/**
	 * @since  1.0
	 *
	 * @param BagOStuff $cache
	 */
	public function setCache( BagOStuff $cache ): void {
		$this->cache = $cache;
	}

	protected function getCacheKey( Context $context ): string {
		if ( $this->cacheKey === null ) {
			$styles = serialize( $this->styles );

			$vars = $this->variables;
			ksort( $vars );
			$vars = serialize( $vars );

			// have to hash the module config, else it may become too long
			$configHash = md5( $styles . $vars );

			$this->cacheKey = \ObjectCache::getLocalClusterInstance()->makeKey(
				'ext',
				'scss',
				$configHash,
				$context->getDirection()
			);
		}

		return $this->cacheKey;
	}

	protected function isCacheOutdated( int $cacheStoreTime ): bool {
		foreach ( $this->cacheTriggers as $triggerFile ) {
			if ( $triggerFile !== null && $cacheStoreTime < filemtime( $triggerFile ) ) {
				return true;
			}

		}

		return false;
	}

	protected function compileStyles( Context $context ): void {
		$scss = new Compiler();
		$scss->setImportPaths( $this->getLocalPath( '' ) );

		// Allows inlining of arbitrary files regardless of extension, .css in particular
		$scss->addImportPath(
			static function ( string|callable $path ) {
				if ( is_string( $path ) && file_exists( $path ) ) {
					return $path;
				}
				return null;
			}

		);

		try {
			$imports = $this->getStyleFilesList();

			foreach ( $imports as $key => $import ) {
				$path = str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $import );
				$imports[ $key ] = '@import "' . $path . '";';
			}

			$scss->addVariables( $this->variables );

			$style = $scss->compileString( implode( $imports ) )->getCss();

			if ( $this->getFlip( $context ) ) {
				$style = CSSJanus::transform( $style, true, false );
			}

			$this->styleText = $style;

			$this->updateCache( $context );
		} catch ( Exception $e ) {

			$this->purgeCache( $context );
			wfDebug( $e->getMessage() );
			$this->styleText = '/* SCSS compile error: ' . $e->getMessage() . '*/';
		}
	}

	protected function updateCache( Context $context ): void {
		$this->getCache()->set(
			$this->getCacheKey( $context ),
			[ 'styles' => $this->styleText, 'storetime' => time() ]
		);
	}

	protected function purgeCache( Context $context ): void {
		$this->getCache()->delete( $this->getCacheKey( $context ) );
	}

	public function supportsURLLoading(): bool {
		return false;
	}

	/**
	 * @return string[]
	 */
	protected function getStyleFilesList(): array {
		$styles = $this->collateStyleFilesByPosition();
		$imports = [];

		foreach ( $this->styleModulePositions as $position ) {
			if ( isset( $styles[ $position ] ) ) {
				$imports = array_merge( $imports, $styles[ $position ] );
			}
		}

		return $imports;
	}

	/**
	 * @return string[][]
	 */
	private function collateStyleFilesByPosition(): array {
		$collatedFiles = [];
		foreach ( $this->styles as $key => $value ) {
			if ( is_int( $key ) ) {
				// File name as the value
				if ( !isset( $collatedFiles['main'] ) ) {
					$collatedFiles['main'] = [];
				}
				$collatedFiles['main'][] = $value instanceof FilePath ? $value->getPath() : $value;
			} elseif ( is_array( $value ) ) {
				// File name as the key, options array as the value
				$optionValue = $value['position'] ?? 'main';
				if ( !isset( $collatedFiles[$optionValue] ) ) {
					$collatedFiles[$optionValue] = [];
				}
				$collatedFiles[$optionValue][] = $key;
			}
		}
		return $collatedFiles;
	}

}
