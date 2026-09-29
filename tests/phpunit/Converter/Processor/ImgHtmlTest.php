<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ImgHtml;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ImgHtmlTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ImgHtml::process
	 * @return void
	 */
	public function testRawImgExternalIsReplacedByExternalImageTemplate() {
		$this->doTest(
			'ExternalImage/image-raw-img-external-input.xml',
			'ExternalImage/image-raw-img-external-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ImgHtml::process
	 * @return void
	 */
	public function testRawImgExternalWithoutSizeIsNotReplacedByExternalImageTemplate() {
		$this->doTest(
			'ExternalImage/image-raw-img-external-no-attributes-input.xml',
			'ExternalImage/image-raw-img-external-no-attributes-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ImgHtml::process
	 * @return void
	 */
	public function testRawImgInLinkIsReplacedByExternalImageTemplateWithLink() {
		$this->doTest(
			'ExternalImage/image-raw-img-in-link-input.xml',
			'ExternalImage/image-raw-img-in-link-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ImgHtml::process
	 * @return void
	 */
	public function testRawImgInLinkWithhoutSizeIsNotReplacedByExternalImageTemplateWithLink() {
		$this->doTest(
			'ExternalImage/image-raw-img-in-link-no-attributes-input.xml',
			'ExternalImage/image-raw-img-in-link-no-attributes-output.xml'
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

		$processor = new ImgHtml(
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
