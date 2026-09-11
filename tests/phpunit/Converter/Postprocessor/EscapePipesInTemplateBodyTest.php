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
	 * @param string $inputPath
	 * @param string $expectedPath
	 */
	public function testPostprocess( string $inputPath, string $expectedPath ): void {
		$input = file_get_contents( $inputPath );
		$expected = file_get_contents( $expectedPath );
		$this->assertIsString( $input );
		$this->assertIsString( $expected );
		$this->assertEquals( $expected, $this->postprocessor->postprocess( $input ) );
	}

	/**
	 * @return array
	 */
	public static function provideTestCases(): array {
		$fixtureDir = __DIR__ . '/EscapePipesInTemplateBody';
		return [
			'temlate with body attrib and with linebreak has escaped table (1)' => [
				"$fixtureDir/temlate_with_body_with_linebreak-input.wikitext",
				"$fixtureDir/temlate_with_body_with_linebreak-output.wikitext",
			],
			'temlate with body attrib and without linebreak has escaped table (2)' => [
				"$fixtureDir/temlate_with_body_without_linebreak-input.wikitext",
				"$fixtureDir/temlate_with_body_without_linebreak-output.wikitext",
			],
			'temlate without body attrib and with linebreak has escaped table (3)' => [
				"$fixtureDir/temlate_without_body_with_linebreak-input.wikitext",
				"$fixtureDir/temlate_without_body_with_linebreak-output.wikitext",
			],
			'temlate without body attrib and without linebreak has escaped table (4)' => [
				"$fixtureDir/temlate_without_body_without_linebreak-input.wikitext",
				"$fixtureDir/temlate_without_body_without_linebreak-output.wikitext",
			],
			'template without table stays unchanged (5)' => [
				"$fixtureDir/template_without_table-input.wikitext",
				"$fixtureDir/template_without_table-output.wikitext",
			],
			'table outside template stays unchanged (6)' => [
				"$fixtureDir/wikitable_outside_template-input.wikitext",
				"$fixtureDir/wikitable_outside_template-output.wikitext",
			],
		];
	}
}
