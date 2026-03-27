<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

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
	 * @dataProvider provideBlockChars
	 * @param string $char
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testBlockCharInCellContent( string $char, string $input, string $expected ) {
		$postprocessor = new FixMultilineTable();
		$actualOutput = $postprocessor->postprocess( $input );

		$this->assertEquals( $expected, $actualOutput );
	}

	/**
	 * @return array
	 */
	public static function provideBlockChars(): array {
		return [
			'unordered list (*)' => [
				'*',
				"{| class=\"wikitable\"\n|-\n| * Item A\n* Item B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n* Item A\n* Item B\n|}",
			],
			'ordered list (#)' => [
				'#',
				"{| class=\"wikitable\"\n|-\n| # Item A\n# Item B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n# Item A\n# Item B\n|}",
			],
			'indent (:)' => [
				':',
				"{| class=\"wikitable\"\n|-\n| : Indented A\n: Indented B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n: Indented A\n: Indented B\n|}",
			],
			'definition term (;)' => [
				';',
				"{| class=\"wikitable\"\n|-\n| ; Term A\n; Term B\n|}",
				"{| class=\"wikitable\"\n|-\n|\n; Term A\n; Term B\n|}",
			],
			'unordered list (*) in header' => [
				'*',
				"{| class=\"wikitable\"\n|-\n! * Item A\n* Item B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n* Item A\n* Item B\n|}",
			],
			'ordered list (#) in header' => [
				'#',
				"{| class=\"wikitable\"\n|-\n! # Item A\n# Item B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n# Item A\n# Item B\n|}",
			],
			'indent (:) in header' => [
				':',
				"{| class=\"wikitable\"\n|-\n! : Indented A\n: Indented B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n: Indented A\n: Indented B\n|}",
			],
			'definition term (;) in header' => [
				';',
				"{| class=\"wikitable\"\n|-\n! ; Term A\n; Term B\n|}",
				"{| class=\"wikitable\"\n|-\n!\n; Term A\n; Term B\n|}",
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
