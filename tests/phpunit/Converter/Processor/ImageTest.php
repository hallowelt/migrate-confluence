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
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
			],
			[
				'0---SomePage---SomeImage2.png' => 'SomePage_SomeImage2.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS:SomePage_SomeImage2.png'
			],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[]
		);

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
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$dataLookup = new ConversionDataLookup( [], [], [], [], [], [], [], [], [] );
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
	 * @param ConversionDataLookup $dataLookup
	 * @param string $input
	 * @param string $output
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return void
	 */
	private function doTestWith( ConversionDataLookup $dataLookup, $input, $output, $spaceId, $rawPageTitle ): void {
		$input = file_get_contents( "$this->dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Image( $dataLookup, $spaceId, $rawPageTitle );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
