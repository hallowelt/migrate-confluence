<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\CreateDefaultWikisConfig;
use PHPUnit\Framework\TestCase;

class CreateDefaultWikisConfigTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Extractor\\Preprocessor\\CreateDefaultWikisConfig::execute
	 */
	public function testCreatesDefaultWikiConfigForEverySpaceWhenConfigIsEmpty(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$workspaceDB->addSpace( 1, 'FIRST', 'First space', 'FIRST', '', '', -1, -1 );
		$workspaceDB->addSpace( 2, 'SECOND', 'Second space', 'Custom', '', 'Root', -1, -1 );

		$processor = new CreateDefaultWikisConfig(
			$workspaceDB,
			$this->createDBLog( $workspaceDB ),
			$this->createWriter( $workspaceDB )
		);
		$processor->execute();

		$this->assertSame( 'wiki', $workspaceDB->getWikisConfigWikiNameForSpaceKey( 'FIRST' ) );
		$this->assertSame( 'FIRST', $workspaceDB->getWikisConfigNamespaceForSpaceKey( 'FIRST' ) );
		$this->assertSame( 'wiki', $workspaceDB->getWikisConfigWikiNameForSpaceKey( 'SECOND' ) );
		$this->assertSame( 'Custom', $workspaceDB->getWikisConfigNamespaceForSpaceKey( 'SECOND' ) );
		$this->assertSame( 'Root', $workspaceDB->getWikisConfigRootPageForSpaceKey( 'SECOND' ) );
	}

	/**
	 * @covers \\HalloWelt\\MigrateConfluence\\Extractor\\Preprocessor\\CreateDefaultWikisConfig::execute
	 */
	public function testDoesNotReplaceExistingWikiConfig(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$workspaceDB->addSpace( 1, 'FIRST', 'First space', 'FIRST', '', '', -1, -1 );
		$workspaceDB->addWikisConfig( 'FIRST', 'configured', 'Namespace', 'Root' );

		$processor = new CreateDefaultWikisConfig(
			$workspaceDB,
			$this->createDBLog( $workspaceDB ),
			$this->createWriter( $workspaceDB )
		);
		$processor->execute();

		$this->assertSame( 'configured', $workspaceDB->getWikisConfigWikiNameForSpaceKey( 'FIRST' ) );
		$this->assertSame( 'Namespace', $workspaceDB->getWikisConfigNamespaceForSpaceKey( 'FIRST' ) );
		$this->assertSame( 'Root', $workspaceDB->getWikisConfigRootPageForSpaceKey( 'FIRST' ) );
	}
}
