<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\FilenameResolver;

use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class FilenameResolverTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\FilenameResolver::resolve()
	 */
	public function testResolve() {
		// Test default
		$filenameResolver = new FilenameResolver(
			$this->getConversionDataLookupDefault(),
			new MigrationConfig( [] )
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
			new MigrationConfig(
				[
					'ext-ns-file-repo-compat' => true
				]
			)
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
	 * @return DBConversionDataLookup
	 */
	private function getConversionDataLookupDefault(): DBConversionDataLookup {
		$dataLookup = new DBConversionDataLookup(
			( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat()
		);
		return $dataLookup;
	}

	/**
	 * @return DBConversionDataLookup
	 */
	private function getConversionDataLookupExtNsFileRepoCompat(): DBConversionDataLookup {
		$dataLookup = new DBConversionDataLookup(
			( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat()
		);
		return $dataLookup;
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
