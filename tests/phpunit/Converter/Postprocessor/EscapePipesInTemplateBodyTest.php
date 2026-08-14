<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\EscapePipesInTemplateBody;
use PHPUnit\Framework\TestCase;

/**
 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\EscapePipesInTemplateBody
 */
class EscapePipesInTemplateBodyTest extends TestCase {

	/** @var EscapePipesInTemplateBody */
	private $postprocessor;

	protected function setUp(): void {
		$this->postprocessor = new EscapePipesInTemplateBody();
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\EscapePipesInTemplateBody::postprocess
	 * @dataProvider provideTestCases
	 * @param string $input
	 * @param string $expected
	 */
	public function testPostprocess( string $input, string $expected ): void {
		$this->assertEquals( $expected, $this->postprocessor->postprocess( $input ) );
	}

	/**
	 * @return array
	 */
	public static function provideTestCases(): array {
		// Note: at postprocessor runtime ###BREAK### markers are still present.
		$br = "###BREAK###";
		// Table-open syntax as it appears in the raw (unconverted) input.
		$tbl = "{| class=\"wikitable\"\n";
		// Table-open syntax as it must appear once escaped for use inside a
		// template parameter: `{|` becomes `{{(!}}` (see EscapePipesInTemplateBody).
		$tblEsc = "{{(!}} class=\"wikitable\"\n";
		return [
			'wikitable in body is escaped' => [
				"{{Info{$br}\n|body = {$br}\n{$tbl}|-\n! Head !! Head\n|-\n| Cell || Cell\n|}}}",
				"{{Info{$br}\n|body = {$br}\n{$tblEsc}{{!}}-\n! Head !! Head\n"
				. "{{!}}-\n{{!}} Cell {{!}}{{!}} Cell\n{{!}}}}}",
			],
			'table open {| is escaped' => [
				"{{Info{$br}\n|body = {$br}\n{$tbl}|-\n| A\n|}}}",
				"{{Info{$br}\n|body = {$br}\n{$tblEsc}{{!}}-\n{{!}} A\n{{!}}}}}",
			],
			'caption line |+ is escaped' => [
				"{{Info{$br}\n|body = {$br}\n{$tbl}|+ Caption\n|-\n| A\n|}}}",
				"{{Info{$br}\n|body = {$br}\n{$tblEsc}{{!}}+ Caption\n{{!}}-\n{{!}} A\n{{!}}}}}",
			],
			'no wikitable in body — unchanged' => [
				"{{Info{$br}\n|body = {$br}\nJust some text.\n}}",
				"{{Info{$br}\n|body = {$br}\nJust some text.\n}}",
			],
			'template without body param — unchanged' => [
				"{{SomeTemplate{$br}\n|param = value{$br}\n}}",
				"{{SomeTemplate{$br}\n|param = value{$br}\n}}",
			],
			'wikitable outside template — unchanged' => [
				"{| class=\"wikitable\"\n|-\n| A || B\n|}",
				"{| class=\"wikitable\"\n|-\n| A || B\n|}",
			],
			'nested templates in body are handled' => [
				"{{Info{$br}\n|body = {$br}\n{$tbl}|-\n| {{Bold|text}} || B\n|}}}",
				"{{Info{$br}\n|body = {$br}\n{$tblEsc}{{!}}-\n{{!}} {{Bold|text}} {{!}}{{!}} B\n{{!}}}}}",
			],
		];
	}
}
