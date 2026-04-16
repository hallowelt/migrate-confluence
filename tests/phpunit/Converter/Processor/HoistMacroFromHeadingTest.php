<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\HoistMacroFromHeading;
use PHPUnit\Framework\TestCase;

class HoistMacroFromHeadingTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\HoistMacroFromHeading::process
	 * @dataProvider provideHoistCases
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testProcess( string $input, string $expected ): void {
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new HoistMacroFromHeading();
		$processor->process( $dom );

		$this->assertEquals( $expected, $dom->saveXML( $dom->documentElement ) );
	}

	/**
	 * @return array
	 */
	public static function provideHoistCases(): array {
		$ns = 'xmlns:ac="ac_ns" xmlns:ri="ri_ns"';

		return [
			'macro-only heading is removed' => [
				"<root $ns><h1><ac:structured-macro ac:name=\"detailssummary\"/></h1></root>",
				"<root $ns><ac:structured-macro ac:name=\"detailssummary\"/></root>",
			],
			'macro hoisted, heading with text kept' => [
				"<root $ns><h2>Title <ac:structured-macro ac:name=\"panel\"/></h2></root>",
				"<root $ns><h2>Title </h2><ac:structured-macro ac:name=\"panel\"/></root>",
			],
			'multiple macros in heading hoisted in order' => [
				"<root $ns><h3><ac:structured-macro ac:name=\"a\"/><ac:structured-macro ac:name=\"b\"/>" .
				"</h3><p>after</p></root>",
				"<root $ns><ac:structured-macro ac:name=\"a\"/><ac:structured-macro ac:name=\"b\"/><p>after</p></root>",
			],
			'macro after last child appended to parent' => [
				"<root $ns><h1><ac:structured-macro ac:name=\"details\"/></h1></root>",
				"<root $ns><ac:structured-macro ac:name=\"details\"/></root>",
			],
			'no macro in heading unchanged' => [
				"<root $ns><h1>Plain heading</h1></root>",
				"<root $ns><h1>Plain heading</h1></root>",
			],
			'macro placed before following sibling' => [
				"<root $ns><h1><ac:structured-macro ac:name=\"x\"/></h1><p>next</p></root>",
				"<root $ns><ac:structured-macro ac:name=\"x\"/><p>next</p></root>",
			],
		];
	}
}
