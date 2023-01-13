<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\NestedHeadings;
use PHPUnit\Framework\TestCase;

class NestedHeadingsTest extends TestCase {

	/**
	 * @var array
	 */
	private $original = <<<TEXT
Lorem ipsum.

*==<br />Heading starting with br-tag ==
**== Heading  ==
*** == Heading==
** ==Heading==

dolor
TEXT;

	/**
	 * @var array
	 */
	private $expected = <<<TEXT
Lorem ipsum.

==<br />Heading starting with br-tag ==
== Heading  ==
== Heading==
==Heading==

dolor
TEXT;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\FixLineBreakInHeadings::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$preprocessor = new NestedHeadings();
		$actual = $preprocessor->postprocess( $this->original );
		$this->assertEquals( $this->expected, $actual );
	}
}
