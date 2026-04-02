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
		return [
			'wikitable in body is escaped' => [
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n|-\n! Head !! Head\n|-\n| Cell || Cell\n|}}}",
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n{{!}}-\n! Head !! Head\n{{!}}-\n{{!}} Cell {{!}}{{!}} Cell\n{{!}}}}}",
			],
			'table open {| is not escaped' => [
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n|-\n| A\n|}}}",
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n{{!}}-\n{{!}} A\n{{!}}}}}",
			],
			'caption line |+ is escaped' => [
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n|+ Caption\n|-\n| A\n|}}}",
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n{{!}}+ Caption\n{{!}}-\n{{!}} A\n{{!}}}}}",
			],
			'no wikitable in body — unchanged' => [
				"{{Info###BREAK###\n|body = ###BREAK###\nJust some text.\n}}",
				"{{Info###BREAK###\n|body = ###BREAK###\nJust some text.\n}}",
			],
			'template without body param — unchanged' => [
				"{{SomeTemplate###BREAK###\n|param = value###BREAK###\n}}",
				"{{SomeTemplate###BREAK###\n|param = value###BREAK###\n}}",
			],
			'wikitable outside template — unchanged' => [
				"{| class=\"wikitable\"\n|-\n| A || B\n|}",
				"{| class=\"wikitable\"\n|-\n| A || B\n|}",
			],
			'nested templates in body are handled' => [
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n|-\n| {{Bold|text}} || B\n|}}}",
				"{{Info###BREAK###\n|body = ###BREAK###\n{| class=\"wikitable\"\n{{!}}-\n{{!}} {{Bold|text}} {{!}}{{!}} B\n{{!}}}}}",
			],
		];
	}
}
