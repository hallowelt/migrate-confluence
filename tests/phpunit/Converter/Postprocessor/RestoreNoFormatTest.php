<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreNoFormat;
use PHPUnit\Framework\TestCase;

class RestoreNoFormatTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreNoFormat::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$dir = dirname( dirname( __DIR__ ) );

		$input = file_get_contents( "$dir/data/noformat-input.wikitext" );
		$expectedOutput = file_get_contents( "$dir/data/noformat-output.wikitext" );

		$preprocessor = new RestoreNoFormat();
		$actualOutput = $preprocessor->postprocess( $input );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
