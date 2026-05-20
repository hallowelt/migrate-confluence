<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ContentProperties;

use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ContentPropertyTest extends TestCase {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @return Output */
	private function makeOutput(): Output {
		return new class extends Output {
			public function doWrite( string $message, bool $newline ): void {
			}
		};
	}

	/**
	 * @param string $xmlFile
	 * @return void
	 */
	private function runProcessor( string $xmlFile ): void {
		$xmlReader = new XMLReader();
		$xmlReader->open( $xmlFile );

		$processor = new ContentProperty( $this->workspaceDB );
		$processor->setOutput( $this->makeOutput() );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'ContentProperty' ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperties::execute
	 */
	public function testInlineCommentPropertyIsDetected() {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$this->runProcessor(
			__DIR__ . '/content_property_inline_comment.xml'
		);

		$contentProperties = $this->workspaceDB->getContentProperties();
		$contentProperty = $contentProperties[0];

		$properties = json_decode( $contentProperty['properties'], true );

		$this->assertSame( "inline-comment", $properties['name'] );
		$this->assertSame( 500, (int)$properties['content'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperties::execute
	 */
	public function testInlineMarkerRefPropertyIsDetected() {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$this->runProcessor(
			__DIR__ . '/content_property_inline_marker_ref.xml'
		);

		$contentProperties = $this->workspaceDB->getContentProperties();
		$contentProperty = $contentProperties[0];

		$properties = json_decode( $contentProperty['properties'], true );

		$this->assertSame( "inline-marker-ref", $properties['name'] );
		$this->assertSame( 501, (int)$properties['content'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperties::execute
	 */
	public function testPageCommentPropertyIsNotDetectedAsInline() {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$this->runProcessor(
			__DIR__ . '/content_property_page_comment.xml'
		);

		$contentProperties = $this->workspaceDB->getContentProperties();

		$this->assertSame( [], $contentProperties );
	}
}
