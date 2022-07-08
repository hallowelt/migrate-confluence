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
				'0---SomePage---SomeImage2.png' => 'SomePage_SomeImage2.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[],
			[]
		);

		/** SpaceId GENERAL */
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-1-general.xml', 0, 'SomePage' );
		$this->doTest( 'image-attachment-input-2.xml', 'image-attachment-output-2.xml', 0, 'SomePage' );
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-1-general.xml', 0, 'SomePage', true );

		/** Random SpaceId */
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-1.xml', 23, 'SomePage' );
		$this->doTest( 'image-attachment-input-2.xml', 'image-attachment-output-2.xml', 23, 'SomePage' );
		$this->doTest( 'image-attachment-input-1.xml', 'image-attachment-output-3.xml', 23, 'SomePage', true );
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @param bool $extNSFileRepo
	 * @return void
	 */
	private function doTest( $input, $output, $spaceId, $rawPageTitle, $extNSFileRepo = false ): void {
		$input = file_get_contents( "$this->dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Image( $this->dataLookup, $spaceId, $rawPageTitle, $extNSFileRepo );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
