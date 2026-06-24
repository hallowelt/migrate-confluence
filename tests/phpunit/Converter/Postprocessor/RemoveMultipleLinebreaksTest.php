<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\RemoveMultipleLinebreaks;
use PHPUnit\Framework\TestCase;

class RemoveMultipleLinebreaksTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\RemoveMultipleLinebreaks::postprocess
	 * @return void
	 */
	public function testLimitsConsecutiveBlocksToThree(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';

		$input = file_get_contents( $dir . '/remove-multiple-linebreaks-input.txt' );
		$expected = file_get_contents( $dir . '/remove-multiple-linebreaks-expected.txt' );

		$postprocessor = new RemoveMultipleLinebreaks();

		$this->assertEquals( $expected, $postprocessor->postprocess( $input ) );
	}
}
