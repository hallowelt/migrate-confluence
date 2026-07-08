<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Image;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ImageTest extends ProcessorTestCase {
	/**
	 * @var DBConversionDataLookup
	 */
	private DBConversionDataLookup $dataLookup;

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Image::preprocess
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname(  __DIR__, 2 ) . '/data';

		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		/** SpaceId GENERAL */
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-1-general.xml', 0, 'SomePage' );
		$this->doTest( 'image-attachment-input-2.xml', 'image-attachment-output-2.xml', 0, 'SomePage' );

		/** Random SpaceId */
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-1.xml', 23, 'SomePage' );
		$this->doTest( 'image-attachment-input-2.xml', 'image-attachment-output-2.xml', 23, 'SomePage' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Image::preprocess
	 * @return void
	 */
	public function testUrlImageInExternalLink() {
		$this->dir = dirname(  __DIR__, 2 ) . '/data';

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$this->doTestWith(
			$dataLookup,
			'image-url-external-link-input.xml',
			'image-url-external-link-output.xml',
			42,
			'SomePage'
		);
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @param string $spaceId
	 * @param string $rawPageTitle
	 * @return void
	 */
	private function doTest( $input, $output, $spaceId, $rawPageTitle ): void {
		$this->doTestWith( $this->dataLookup, $input, $output, $spaceId, $rawPageTitle );
	}

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param string $input
	 * @param string $output
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return void
	 */
	private function doTestWith( DBConversionDataLookup $dataLookup, $input, $output, $spaceId, $rawPageTitle ): void {
		$input = file_get_contents( "$this->dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Image( $dataLookup, $spaceId, $rawPageTitle, new MigrationConfig( [] ) );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
