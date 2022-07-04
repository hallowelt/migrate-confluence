<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreCode;
use PHPUnit\Framework\TestCase;

class RestoreCodeTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreCode::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$dir = dirname( dirname( __DIR__ ) );

		$input = file_get_contents( "$dir/data/code-input.wikitext" );
		$expectedOutput = file_get_contents( "$dir/data/code-output.wikitext" );

		$preprocessor = new RestoreCode();
		$actualOutput = $preprocessor->postprocess( $input );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
