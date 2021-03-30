<?php


namespace HalloWelt\MigrateConfluence\Tests\Utility\FilenameBuilder;


use DOMDocument;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use PHPUnit\Framework\TestCase;

class FilenameBuilderTest extends TestCase
{
	public function testFilenameBuild()
	{
		/*$dom = new DOMDocument();
		$dom->load( __DIR__ . '/entities_test.xml' );
		$helper = new XMLHelper( $dom );

		$spacePrefixToIdMap = [
			32973 => 'TestNS'
		];

		$titleBuilder = new TitleBuilder( $spacePrefixToIdMap, $helper );
		$pageNodes = $helper->getObjectNodes( "Page" );

		$actualTitles = [];
		foreach( $pageNodes as $pageNode ) {
			$fullTitle = $titleBuilder->buildTitle( $pageNode );

			$originalVersionID = $helper->getPropertyValue( 'originalVersion', $pageNode );
			if( $originalVersionID !== null ) {
				continue;
			}

			$attachmentRefs = $helper->getElementsFromCollection( 'attachments', $pageNode );
			foreach ( $attachmentRefs as $attachmentRef ) {
				$attachmentId = $helper->getIDNodeValue( $attachmentRef );
				$attachment = $helper->getObjectNodeById( $attachmentId, 'Attachment' );
				$attachmentTargetFilename = $this->makeAttachmentTargetFilename( $attachment, $fullTitle );
				$attachmentReference = $this->makeAttachmentReference( $attachment );
				$this->addTitleAttachment( $fullTitle, $attachmentTargetFilename );
			}

			$actualTitles[] = $fullTitle;
		}

		$expectedTitles = [
			"TestNS:Dokumentation",
			"TestNS:Dokumentation/Roadmap",
			"TestNS:Dokumentation/Roadmap/Detailed_planning"
		];

		$this->assertEquals( $expectedTitles, $actualTitles );*/
		$this->assertTrue(true);
	}
}