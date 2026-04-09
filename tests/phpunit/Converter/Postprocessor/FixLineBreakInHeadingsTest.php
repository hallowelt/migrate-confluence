<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use PHPUnit\Framework\TestCase;

class FixLineBreakInHeadingsTest extends TestCase {

	/**
	 * @var array
	 */
	private $originalHeadings = [
		'==<br />Heading starting with br-tag ==',
		'== Heading with br-tag<br />in the middle ==',
		'== Heading ending with br-tag<br />==',
		'== Heading without br-tag =='
	];

	/**
	 * @var array
	 */
	private $expectedHeadings = [
		'== Heading starting with br-tag ==',
		'== Heading with br-tag in the middle ==',
		'== Heading ending with br-tag ==',
		'== Heading without br-tag =='
	];

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\FixLineBreakInHeadings::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$preprocessor = new FixLineBreakInHeadings();
		for ( $i = 0; $i < count( $this->originalHeadings ); $i++ ) {
			$actualHeading = $preprocessor->postprocess( $this->originalHeadings[$i] );
			$this->assertEquals( $this->expectedHeadings[$i], $actualHeading );
		}
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\FixLineBreakInHeadings::postprocess
	 * @return void
	 */
	public function testMultilineHeading() {
		$preprocessor = new FixLineBreakInHeadings();

		// <br /> followed by a real newline pushes the closing === onto its own line.
		$input = "=== Lorem ipsum dolor sit amet consecutur:<br />\n<br />\n===";
		$expected = '=== Lorem ipsum dolor sit amet consecutur: ===';
		$this->assertEquals( $expected, $preprocessor->postprocess( $input ) );

		// Single trailing <br /> before closing tag on next line.
		$input2 = "== Short heading<br />\n==";
		$expected2 = '== Short heading ==';
		$this->assertEquals( $expected2, $preprocessor->postprocess( $input2 ) );

		// Surrounding text is not affected.
		$before = "Some text before\n";
		$after = "\nSome text after";
		$this->assertEquals(
			$before . $expected . $after,
			$preprocessor->postprocess( $before . $input . $after )
		);

		// Closing tag with leading whitespace (space before ====).
		$input3 = "==== Es gibt zwei wesentliche '''Bereichs-Arten''':<br />\n<br />\n ====";
		$expected3 = "==== Es gibt zwei wesentliche '''Bereichs-Arten''': ====";
		$this->assertEquals( $expected3, $preprocessor->postprocess( $input3 ) );

		// Closing tag preceded by bold-close markup (''' ==).
		$input4 = "== '''Konfiguration Ihrer E-Mail:<br />\n''' ==";
		$expected4 = "== '''Konfiguration Ihrer E-Mail: ''' ==";
		$this->assertEquals( $expected4, $preprocessor->postprocess( $input4 ) );
	}
}
