<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Attachments;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class AttachmentsTest extends TestCase {

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
	 * @param string $sourceBasePath Directory containing entities.xml (NOT the file path itself)
	 * @return void
	 */
	private function runProcessor( string $xmlFile, string $sourceBasePath ): void {
		$processor = new Attachments(
			new AnalyzeDirectDataWriter( $this->workspaceDB ),
			$this->migrationConfig,
			$sourceBasePath
		);
		$processor->setOutput( $this->makeOutput() );

		$xmlReader = new XMLReader();
		$xmlReader->open( $xmlFile );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'Attachment' ) {
				$read = $xmlReader->next();
				continue;
			}
			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}
			$read = $xmlReader->next();
		}
		$xmlReader->close();
	}

	/**
	 * Regression test: ConfluenceAnalyzer previously passed getRealPath() (the full path to
	 * entities.xml itself) as sourceBasePath instead of getPath() (the containing directory).
	 * This caused attachment_reference values like:
	 *   /path/to/Space/entities.xml/attachments/...
	 * instead of the correct:
	 *   /path/to/Space/attachments/...
	 *
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments::doExecute
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer::analyze
	 */
	public function testAttachmentReferenceDoesNotContainEntitiesXml(): void {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		// Simulate what ConfluenceAnalyzer does after the fix: pass the directory, not the file.
		$sourceBasePath = '/some/input/Space';

		$this->runProcessor( __DIR__ . '/attachment.xml', $sourceBasePath );

		$attachments = $this->workspaceDB->getAttachments();
		$this->assertCount( 1, $attachments );

		$attachmentReference = $attachments[0]['attachment_reference'];

		$this->assertStringNotContainsString(
			'entities.xml',
			$attachmentReference,
			'attachment_reference must not contain "entities.xml" — sourceBasePath must be the ' .
			'directory, not the file path (getRealPath vs getPath bug)'
		);

		$this->assertSame(
			'/some/input/Space/attachments/208864646/208864647/1',
			$attachmentReference
		);
	}
}
