<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getCoverPictureForContentId
 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getAttachmentWikiTitleByFilestoreId
 */
class CoverPictureTest extends TestCase {

	public function testHistoricalVersionResolvesAttachmentOfOriginalPage(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		$db->addContentProperty( 10, 'coverPicture', 'x', [ 'stringValue' => '{\"id\":\"abc\",\"position\":39.5}' ] );
		$db->addContentProperty( 11, 'FILESTORE_ID', 'x', [ 'stringValue' => 'abc' ] );
		$db->addContentProperty( 12, 'FILESTORE_ID', 'x', [ 'stringValue' => 'other' ] );
		// Version 2 is a historical row of the original page 1
		$db->addPage(
			2, 1, 'P', 'p', 'P', 'current', '', '', '2', 1, -1, [], [], [], [ 'contentProperties' => [ 10 ] ]
		);
		$db->addAttachment(
			5, 1, 'a.png', 'png', 1, 'current', '1', '', '', -1, '', [], [], [ 'contentProperties' => [ 11 ] ]
		);
		$db->addAttachment(
			6, 1, 'b.png', 'png', 1, 'current', '1', '', '', -1, '', [], [], [ 'contentProperties' => [ 12 ] ]
		);
		$db->addPageAttachment( 5, 1, 'a.png', 'File:P-a.png' );
		$db->addPageAttachment( 6, 1, 'b.png', 'File:P-b.png' );

		$cover = $db->getCoverPictureForContentId( 2 );
		$this->assertSame( [ 'id' => 'abc', 'position' => 39.5, 'containerId' => 1 ], $cover );
		$this->assertSame( 'File:P-a.png', $db->getAttachmentWikiTitleByFilestoreId( 'abc', 1 ) );
		$this->assertNull( $db->getAttachmentWikiTitleByFilestoreId( 'abc', 99 ) );
	}

	public function testIgnoresUnparsableCover(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		$db->addContentProperty( 10, 'coverPicture', 'x', [ 'stringValue' => 'garbage' ] );
		$db->addPage(
			1, 1, 'P', 'p', 'P', 'current', '', '', '1', -1, -1, [], [], [], [ 'contentProperties' => [ 10 ] ]
		);

		$this->assertNull( $db->getCoverPictureForContentId( 1 ) );
	}
}
