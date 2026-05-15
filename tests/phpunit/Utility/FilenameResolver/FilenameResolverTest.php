<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\FilenameResolver;

use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use PHPUnit\Framework\TestCase;

class FilenameResolverTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\FilenameResolver::resolve()
	 */
	public function testResolve() {
		// Test default
		$filenameResolver = new FilenameResolver(
			$this->getConversionDataLookupDefault(),
			[]
		);

		// Image exists in the conversion data lookup
		$expected = $this->getExpectedResult( 'DEVOPS_SomePage-SomeImage2.png', false );
		$actual = $filenameResolver->resolve( 23, 'SomePage', 'SomeImage2.png' );
		$this->assertEquals( $expected, $actual );

		// Image does not exist in the conversion data lookup
		$expected = $this->getExpectedResult( 'ABC_SomePage-SomeImage2.png', true );
		$actual = $filenameResolver->resolve( 42, 'SomePage', 'SomeImage2.png' );
		$this->assertEquals( $expected, $actual );

		// Image exists in the conversion data lookup but we have no page context
		$expected = $this->getExpectedResult( 'ABC_SomeImage2.png', true );
		$actual = $filenameResolver->resolve( 42, '', 'SomeImage2.png' );
		$this->assertEquals( $expected, $actual );

		// Test with ext-ns-file-repo-compat
		$filenameResolver = new FilenameResolver(
			$this->getConversionDataLookupExtNsFileRepoCompat(),
			[
				'ext-ns-file-repo-compat' => true
			]
		);

		// Image exists in the conversion data lookup
		$expected = $this->getExpectedResult( 'DEVOPS:SomePage-SomeImage2.png', false );
		$actual = $filenameResolver->resolve( 23, 'SomePage', 'SomeImage2.png' );
		$this->assertEquals( $expected, $actual );

		// Image does not exist in the conversion data lookup
		$expected = $this->getExpectedResult( 'ABC:SomePage-SomeImage2.png', true );
		$actual = $filenameResolver->resolve( 42, 'SomePage', 'SomeImage2.png' );
		$this->assertEquals( $expected, $actual );

		// Image exists in the conversion data lookup but we have no page context
		$expected = $this->getExpectedResult( 'ABC:SomeImage2.png', true );
		$actual = $filenameResolver->resolve( 42, '', 'SomeImage2.png' );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @return ConversionDataLookup
	 */
	private function getConversionDataLookupDefault(): ConversionDataLookup {
		return new ConversionDataLookup(
			[
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[
				'42---SomePage' => 'ABC:SomePage',
				'23---SomePage' => 'DEVOPS:SomePage'
			],
			[
				'0---SomePage---SomeImage2.png' => 'SomePage-SomeImage2.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage-SomeImage2.png'
			],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[],
			[]
		);
	}

	/**
	 * @return ConversionDataLookup
	 */
	private function getConversionDataLookupExtNsFileRepoCompat(): ConversionDataLookup {
		return new ConversionDataLookup(
			[
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[
				'42---SomePage' => 'ABC:SomePage',
				'23---SomePage' => 'DEVOPS:SomePage'
			],
			[
				'0---SomePage---SomeImage2.png' => 'SomePage-SomeImage2.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS:SomePage-SomeImage2.png'
			],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[],
			[]
		);
	}

	/**
	 * @param string $targetFilename
	 * @param bool $isBroken
	 * @return array
	 */
	private function getExpectedResult( string $targetFilename, bool $isBroken ): array {
		return [ 'title' => $targetFilename, 'isBroken' => $isBroken ];
	}
}
