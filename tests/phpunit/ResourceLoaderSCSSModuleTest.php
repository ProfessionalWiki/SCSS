<?php
/**
 * File holding the ResourceLoaderSCSSModuleTest class
 *
 * @copyright (C) 2018 - 2019, Stephan Gambke
 * @license       GPL-3.0-or-later
 *
 * This file is part of the MediaWiki extension Bootstrap.
 * The Bootstrap extension is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Bootstrap extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup Bootstrap
 */

namespace SCSS\Tests;

use HashBagOStuff;
use MediaWiki\ResourceLoader\Context;
use PHPUnit\Framework\TestCase;
use SCSS\ResourceLoaderSCSSModule;

/**
 * @covers \SCSS\ResourceLoaderSCSSModule
 * @uses \SCSS\ResourceLoaderSCSSModule
 *
 * @ingroup Test
 * @ingroup SCSS
 *
 * @group extensions-scss
 * @group mediawiki-databaseless
 *
 * @since 1.0
 */
class ResourceLoaderSCSSModuleTest extends TestCase {

	public function testCanConstruct(): void {
		$this->assertInstanceOf(
			'\SCSS\ResourceLoaderSCSSModule',
			new ResourceLoaderSCSSModule()
		);
	}

	public function testGetStyles(): void {
		$resourceLoaderContext = $this->getMockBuilder( Context::class )
			->disableOriginalConstructor()
			->getMock();

		$instance = new ResourceLoaderSCSSModule;
		$instance->setCache( new HashBagOStuff );

		$this->assertArrayHasKey( 'all', $instance->getStyles( $resourceLoaderContext ) );
	}

	public function testGetStylesFromPresetCache(): void {
		$resourceLoaderContext = $this->getMockBuilder( Context::class )
			->disableOriginalConstructor()
			->getMock();

		$resourceLoaderContext->method( 'getDirection' )
			->willReturn( 'ltr' );

		$cache = new HashBagOStuff;

		$str = serialize( [] );
		$configHash = md5( $str . $str );

		$cache->set(
			\ObjectCache::getLocalClusterInstance()->makeKey(
				'ext',
				'scss',
				$configHash,
				$resourceLoaderContext->getDirection()
			),
			[
				'storetime' => time(),
				'styles'    => 'foo'
			]
		);

		$instance = new ResourceLoaderSCSSModule;
		$instance->setCache( $cache );

		$styles = $instance->getStyles( $resourceLoaderContext );

		$this->assertArrayHasKey( 'all', $styles );
		$this->assertEquals( 'foo', $styles['all'] );
	}

	public function testGetStylesTryCatchExceptionIsThrownByScssParser(): void {
		$resourceLoaderContext = $this->getMockBuilder( Context::class )
			->disableOriginalConstructor()
			->getMock();

		$options = [
			'styles' => [ 'Foo"' ]
		];

		$instance = new ResourceLoaderSCSSModule( $options );
		$instance->setCache( new HashBagOStuff );

		$result = $instance->getStyles( $resourceLoaderContext );
		$this->assertStringContainsString( 'SCSS compile error', $result['all'] );
	}

	public function testSupportsURLLoading(): void {
		$instance = new ResourceLoaderSCSSModule();
		$this->assertFalse( $instance->supportsURLLoading() );
	}
}
