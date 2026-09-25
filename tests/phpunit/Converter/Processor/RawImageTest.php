<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\RawImage;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class RawImageTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RawImage::process
	 * @return void
	 */
	public function testRawImgExternalIsReplacedByPlainUrlImageTemplate() {
		$this->doTest(
			'PlainUrlImage/image-raw-img-external-input.xml',
			'PlainUrlImage/image-raw-img-external-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RawImage::process
	 * @return void
	 */
	public function testRawImgExternalWithoutSizeIsNotReplacedByPlainUrlImageTemplate() {
		$this->doTest(
			'PlainUrlImage/image-raw-img-external-sizeless-input.xml',
			'PlainUrlImage/image-raw-img-external-sizeless-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RawImage::process
	 * @return void
	 */
	public function testRawImgInLinkIsReplacedByPlainUrlImageTemplateWithLink() {
		$this->doTest(
			'PlainUrlImage/image-raw-img-in-link-input.xml',
			'PlainUrlImage/image-raw-img-in-link-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RawImage::process
	 * @return void
	 */
	public function testRawImgInLinkWithhoutSizeIsNotReplacedByPlainUrlImageTemplateWithLink() {
		$this->doTest(
			'PlainUrlImage/image-raw-img-in-link-sizeless-input.xml',
			'PlainUrlImage/image-raw-img-in-link-sizeless-output.xml'
		);
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( $input, $output ): void {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		$inputContent = file_get_contents( "$this->dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $inputContent );

		$processor = new RawImage(
			$this->createConverterDataWriter(),
			$dataLookup,
			42,
			'SomePage',
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
