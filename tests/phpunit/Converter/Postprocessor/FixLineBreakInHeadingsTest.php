<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use PHPUnit\Framework\TestCase;

class FixLineBreakInHeadingsTest extends TestCase {

	/**
	 * @var array
	 */
	private $originalHeadings = [
		'==<br />Heading starting with br-tag ==',
		'== Heading with br-tag<br />in the middle ==',
		'== Heading ending with br-tag<br />==',
		'== Heading without br-tag =='
	];

	/**
	 * @var array
	 */
	private $expectedHeadings = [
		'== Heading starting with br-tag ==',
		'== Heading with br-tag in the middle ==',
		'== Heading ending with br-tag ==',
		'== Heading without br-tag =='
	];

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\FixLineBreakInHeadings::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$preprocessor = new FixLineBreakInHeadings();
		for ( $i = 0; $i < count( $this->originalHeadings ); $i++ ) {
			$actualHeading = $preprocessor->postprocess( $this->originalHeadings[$i] );
			$this->assertEquals( $this->expectedHeadings[$i], $actualHeading );
		}
	}
}