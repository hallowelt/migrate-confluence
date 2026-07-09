<?php
// phpcs:ignoreFile

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use PHPUnit\Framework\TestCase;

class FixMultilineTableTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = $this->getInput();

		$preprocessor = new FixMultilineTable();
		$actualOutput = $preprocessor->postprocess( $input );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @dataProvider provideBlockCharsForSimpleTable
	 * @param string $char
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testBlockCharInSimpleTable( string $char, string $input, string $expected ) {
		$postprocessor = new FixMultilineTable();
		$actualOutput = $postprocessor->postprocess( $input );

		$this->assertEquals( $expected, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @dataProvider provideBlockCharsForSimpleTable
	 * @param string $char
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testBlockCharInTableWithAttributes( string $char, string $input, string $expected ) {
		$postprocessor = new FixMultilineTable();
		$actualOutput = $postprocessor->postprocess( $input );

		$this->assertEquals( $expected, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @dataProvider provideSimpleNestedTable
	 * @param string $char
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testSimpleNestedTable( string $char, string $input, string $expected ) {
		$postprocessor = new FixMultilineTable();
		$actualOutput = $postprocessor->postprocess( $input );

		$this->assertEquals( $expected, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @dataProvider provideNestedTableWithBlockChars
	 * @param string $char
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testNestedTableWithBlockChars( string $char, string $input, string $expected ) {
		$postprocessor = new FixMultilineTable();
		$actualOutput = $postprocessor->postprocess( $input );

		$this->assertEquals( $expected, $actualOutput );
	}

	/**
	 * @return array
	 */
	public static function provideBlockCharsForSimpleTable(): array {
		return [
			'unordered list (*) in cell' => [
				'*',
				"{| class=\"wikitable\"\n|-\n| * Item A\n* Item B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n* Item A\n* Item B\n|}",
			],
			'unordered list (*) in header' => [
				'*',
				"{| class=\"wikitable\"\n|-\n! * Item A\n* Item B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n* Item A\n* Item B\n|}",
			],
			'ordered list (#) in cell' => [
				'#',
				"{| class=\"wikitable\"\n|-\n| # Item A\n# Item B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n# Item A\n# Item B\n|}",
			],
			'ordered list (#) in header' => [
				'#',
				"{| class=\"wikitable\"\n|-\n! # Item A\n# Item B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n# Item A\n# Item B\n|}",
			],
			'indent (:) in cell' => [
				':',
				"{| class=\"wikitable\"\n|-\n| : Indented A\n: Indented B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n: Indented A\n: Indented B\n|}",
			],
			'indent (:) in header' => [
				':',
				"{| class=\"wikitable\"\n|-\n! : Indented A\n: Indented B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n: Indented A\n: Indented B\n|}",
			],
			'definition term (;) in cell' => [
				';',
				"{| class=\"wikitable\"\n|-\n| ; Term A\n; Term B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n; Term A\n; Term B\n|}",
			],
			'definition term (;) in header' => [
				';',
				"{| class=\"wikitable\"\n|-\n! ; Term A\n; Term B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n; Term A\n; Term B\n|}",
			],
			'headline (=) in cell' => [
				'=',
				"{| class=\"wikitable\"\n|-\n| === Headline 3 ===\n|}",
				"{| class=\"wikitable\"\n|-\n|\n=== Headline 3 ===\n|}",
			],
			'headline (=) in header' => [
				'=',
				"{| class=\"wikitable\"\n|-\n! === Headline 3 ===\n|}",
				"{| class=\"wikitable\"\n|-\n!\n=== Headline 3 ===\n|}",
			],
			'text after cell' => [
				'=',
				"{| class=\"wikitable\"\n|-\n| Some text|}",
				"{| class=\"wikitable\"\n|-\n| Some text|}",
			],
			'text after header' => [
				'=',
				"{| class=\"wikitable\"\n|-\n! Some text|}",
				"{| class=\"wikitable\"\n|-\n! Some text|}",
			]
		];
	}

	/**
	 * @return array
	 */
	public static function provideBlockCharsForTableWithAttributes(): array {
		return [
			'unordered list (*) in cell' => [
				'*',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| * Item A\n* Item B\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"|\n* Item A\n* Item B\n|}",
			],
			'unordered list (*) in header' => [
				'*',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| * Item A\n* Item B\n|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"|\n* Item A\n* Item B\n|}",
			],
			'ordered list (#) in cell' => [
				'#',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| # Item A\n# Item B\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"|\n# Item A\n# Item B\n|}",
			],
			'ordered list (#) in header' => [
				'#',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| # Item A\n# Item B\n|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"|\n# Item A\n# Item B\n|}",
			],
			'indent (:) in cell' => [
				':',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| : Indented A\n: Indented B\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"|\n: Indented A\n: Indented B\n|}",
			],
			'indent (:) in header' => [
				':',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| : Indented A\n: Indented B\n|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"|\n: Indented A\n: Indented B\n|}",
			],
			'definition term (;) in cell' => [
				';',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| ; Term A\n; Term B\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"|\n; Term A\n; Term B\n|}",
			],
			'definition term (;) in header' => [
				';',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| ; Term A\n; Term B\n|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"|\n; Term A\n; Term B\n|}",
			],
			'headline (=) in cell' => [
				'=',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| === Headline 3 ===\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"|\n=== Headline 3 ===\n|}",
			],
			'headline (=) in header' => [
				'=',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| === Headline 3 ===\n|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"|\n=== Headline 3 ===\n|}",
			],
			'text after cell' => [
				'=',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| Some text|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| Some text|}",
			],
			'text after header' => [
				'=',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| Some text|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: left;\"| Some text|}",
			],
			'style attribute starting in new line (pandoc block-level cell)' => [
				'|',
				"{| class=\"wikitable\"\n|-\n|\nstyle=\"text-align: left;\"| Some text\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: left;\"| Some text\n|}",
			],
		];
	}

	/**
	 * @return array
	 */
	private function provideSimpleNestedTable(): array {
		return [
			'nested table in bare header' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n! {| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n!\n{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in bare cell' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n| {| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n|\n{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in header' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n! Some text {| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n! Some text\n{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in cell' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n| Some text {| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n| Some text\n{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in bare header with attributes' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n! {| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n!\n{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in bare cell with attributes' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n|  style=\"text-align: center;\" | {| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n|  style=\"text-align: center;\" |\n{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in header with attributes' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: center;\" | Some text " .
					"{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n! style=\"text-align: center;\" | Some text\n" .
					"{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
			'nested table in cell with attributes' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: center;\" | Some text " .
					"{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n| style=\"text-align: center;\" | Some text\n" .
					"{| class=\"wikitable2\"\n|-\n| cell\n|}\n|}",
			],
		];
	}

	/**
	 * @return array
	 */
	private function provideNestedTableWithBlockChars(): array {
		return [
			'nested table in bare header and ordered list (#) in header' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n! {| class=\"wikitable2\"\n|-\n! # Item A\n# Item B\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n!\n{| class=\"wikitable2\"\n|-\n!\n# Item A\n# Item B\n|}\n|}",
			],
			'nested table in bare header and ordered list (#) in cell' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n! {| class=\"wikitable2\"\n|-\n| # Item A\n# Item B\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n!\n{| class=\"wikitable2\"\n|-\n|\n# Item A\n# Item B\n|}\n|}",
			],
			'nested table in bare cell and ordered list (#) in header' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n| {| class=\"wikitable2\"\n|-\n! # Item A\n# Item B\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n|\n{| class=\"wikitable2\"\n|-\n!\n# Item A\n# Item B\n|}\n|}",
			],
			'nested table in bare cell and ordered list (#) in cell' => [
				'{|',
				"{| class=\"wikitable\"\n|-\n| {| class=\"wikitable2\"\n|-\n| # Item A\n# Item B\n|}\n|}",
				"{| class=\"wikitable\"\n|-\n|\n{| class=\"wikitable2\"\n|-\n|\n# Item A\n# Item B\n|}\n|}",
			],
		];
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/fix-multiline-table-input.wikitext' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/fix-multiline-table-output.wikitext' );
	}
}
