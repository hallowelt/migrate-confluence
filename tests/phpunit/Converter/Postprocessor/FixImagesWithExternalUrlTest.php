<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixImagesWithExternalUrl;
use PHPUnit\Framework\TestCase;

class FixImagesWithExternalUrlTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\FixImagesWithExternalUrl::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$input = $this->getInput();
		$expected = $this->getExpectedOutput();

		$postprocessor = new FixImagesWithExternalUrl();
		$actual = $postprocessor->postprocess( $input );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @return string
	 */
	private function getInput(): string {
		$content = file_get_contents(
			dirname( dirname( __DIR__ ) ) . '/data/fix-images-with-external-url-input.wikitext'
		);
		return $content;
	}

	/**
	 * @return string
	 */
	private function getExpectedOutput(): string {
		$content = file_get_contents(
			dirname( dirname( __DIR__ ) ) . '/data/fix-images-with-external-url-output.wikitext'
		);
		return $content;
	}

}
