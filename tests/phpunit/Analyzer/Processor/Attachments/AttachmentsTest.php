<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Attachments;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class AttachmentsTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new Attachments(
			new AnalyzeDirectDataWriter( $this->workspaceDB ),
			new MigrationConfig( [ 'include-history' => true ] ),
			__DIR__
		);

		$this->executeProcessorForClass( $processor, __DIR__ . '/attachment.xml', 'Attachment' );

		$attachments = $this->workspaceDB->getAttachments();
		$this->assertCount( 1, $attachments, 'Expected exactly one attachment row.' );

		$attachment = $attachments[0];
		$this->assertSame( 40, $attachment['attachment_id'], 'Unexpected attachment_id value.' );
		$this->assertSame( 10, $attachment['space_id'], 'Unexpected space_id value.' );
		$this->assertSame( 'report.pdf', $attachment['filename'], 'Unexpected filename value.' );
		$this->assertSame( 'pdf', $attachment['file_extension'], 'Unexpected file_extension value.' );
		$this->assertSame( 30, $attachment['container_id'], 'Unexpected container_id value.' );
		$this->assertSame( 'current', $attachment['content_status'], 'Unexpected content_status value.' );
		$this->assertSame( '2', $attachment['version'], 'Unexpected version value.' );
		$this->assertSame(
			date( 'YmdHis', strtotime( '2026-07-08 15:16:17.000' ) ),
			$attachment['revision_timestamp'],
			'Unexpected revision_timestamp value.'
		);
		$this->assertSame( 'userkey2', $attachment['last_modifier'], 'Unexpected last_modifier value.' );
		$this->assertSame( -1, $attachment['original_version_id'], 'Unexpected original_version_id value.' );
		$this->assertSame( __DIR__ . '/attachments/30/40/2', $attachment['attachment_reference'],
			'Unexpected attachment_reference value.'
		);
		$this->assertSame( '["39"]', $attachment['historical_ids'], 'Unexpected historical_ids value.' );

		$properties = json_decode( $attachment['properties'], true );
		$this->assertSame( 'report.pdf', $properties['fileName'], 'Unexpected properties.fileName value.' );
		$this->assertSame( '30', $properties['containerContent'], 'Unexpected properties.containerContent value.' );
		$this->assertSame( '2', $properties['attachmentVersion'], 'Unexpected properties.attachmentVersion value.' );

		$collection = json_decode( $attachment['collection'], true );
		$this->assertSame( [ '39' ], $collection['historicalVersions'],
			'Unexpected collection.historicalVersions value.'
		);
	}
}
