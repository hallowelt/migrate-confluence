<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Image;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Image::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$this->dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[
				'42---SomePage---SomeImage.png' => 'ABC_SomePage_SomeImage.png',
				'42---SomePage---SomeImage1.png' => 'ABC_SomePage_SomeImage1.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[
				'0------SampleImage.png' => 'DEVOPS_SomeImage.png'
			],
			[]
		);

		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-1.xml' );
		$this->doTest( 'image-attachment-input-2.xml', 'image-attachment-output-2.xml' );
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-3.xml', true );
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @param bool $extNSFileRepo
	 * @return void
	 */
	private function doTest( $input, $output, $extNSFileRepo = false ): void {
		$input = file_get_contents( "$this->dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Image( $this->dataLookup, 23, 'SomePage', $extNSFileRepo );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
