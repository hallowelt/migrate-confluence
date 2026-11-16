<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use PHPUnit\Framework\TestCase;

class AttachmentFileExtensionUpdateTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::updateAttachmentFileExtension
	 * @covers \HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter::updateAttachmentFileExtension
	 */
	public function testUpdatesAttachmentFileExtension(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		$db->addAttachment(
			601, 1000, 'file', 'unknown', 600, 'current', '1', '', '', -1, '/data/file', [], [], []
		);

		$writer = new ExtractorDirectDataWriter( $db );
		$this->assertTrue( $writer->updateAttachmentFileExtension( 601, 'png' ) );
		$this->assertSame( 'png', $db->getAttachments()[0]['file_extension'] );
	}
}
