<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTableAttributes;
use PHPUnit\Framework\TestCase;

class RestoreTableAttributesTest extends TestCase {

	private $input = <<<HERE
Lorem
{|
| <span data="ABC" class="XYZ">###PRESERVEDTABLEATTRIBUTES###</span>
|
|-
| Some
| [[DEF]]
|-
| Table
|
|}
[[Ipsum]]
{|
|width="50%"| <span data="ABC" class="XYZ">###PRESERVEDTABLEATTRIBUTES###</span>
|width="50%"|
|-
| Some
| [[DEF]]
|-
| Table
|
|}
{{sit amet}}
{|
! Table head 1
! Table head 2
|-
|width="50%"| <span data="ABC" class="XYZ">###PRESERVEDTABLEATTRIBUTES###</span>
|width="50%"|
|-
| Some
| [[DEF]]
|-
| Table
|
|}
HERE;

	private $expectedOutput = <<<HERE
Lorem
{| data="ABC" class="XYZ"
| Some
| [[DEF]]
|-
| Table
|
|}
[[Ipsum]]
{| data="ABC" class="XYZ"
| Some
| [[DEF]]
|-
| Table
|
|}
{{sit amet}}
{| data="ABC" class="XYZ"
! Table head 1
! Table head 2
|-
| Some
| [[DEF]]
|-
| Table
|
|}
HERE;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\RestoreTableAttributes::postprocess
	 * @return void
	 */
	public function testPreprocess() {
		$preprocessor = new RestoreTableAttributes();
		$actualOutput = $preprocessor->postprocess( $this->input );
		$this->assertEquals( $this->expectedOutput, $actualOutput );
	}
}
