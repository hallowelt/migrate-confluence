<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Preprocessor\CDATAClosingFixer;
use PHPUnit\Framework\TestCase;

class CDATAClosingFixerTest extends TestCase {


	private $input = <<<HERE
	<html><body><p>Lorem ipsum dolor sit amet <ac:link><ri:page ri:content-title="Somepage" ri:space-key="SOMESPACE" /><ac:plain-text-link-body><![CDATA[JSON]]></ac:plain-text-link-body></ac:link>.</p><p>Lorem ipsum</p><ul><li>Item 1</li><li>Item 2</li></ul><p>&nbsp;</p><ac:structured-macro ac:name="code"><ac:parameter ac:name="title">Example</ac:parameter><ac:parameter ac:name="language">js</ac:parameter><ac:plain-text-body><![CDATA[[
		{
		  object_class : "ABC:def:ghi",
		  name : "Name1",
		  xNumber : "X1",
		  sequenceNumber : 100,
		  scope :
		  [
		  ]
		}
	  ]] ]></ac:plain-text-body></ac:structured-macro><p>&nbsp;</p><p>&nbsp;</p></body></html>
HERE;

	private $expectedOutput = <<<HERE
	<html><body><p>Lorem ipsum dolor sit amet <ac:link><ri:page ri:content-title="Somepage" ri:space-key="SOMESPACE" /><ac:plain-text-link-body><![CDATA[JSON]]></ac:plain-text-link-body></ac:link>.</p><p>Lorem ipsum</p><ul><li>Item 1</li><li>Item 2</li></ul><p>&nbsp;</p><ac:structured-macro ac:name="code"><ac:parameter ac:name="title">Example</ac:parameter><ac:parameter ac:name="language">js</ac:parameter><ac:plain-text-body><![CDATA[[
		{
		  object_class : "ABC:def:ghi",
		  name : "Name1",
		  xNumber : "X1",
		  sequenceNumber : 100,
		  scope :
		  [
		  ]
		}
	  ]] ]> [[Category:Broken_CDATA]] ]]></ac:plain-text-body></ac:structured-macro><p>&nbsp;</p><p>&nbsp;</p></body></html>
HERE;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\CDATAClosingFixer::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$preprocessor = new CDATAClosingFixer();
		$actualOutput = $preprocessor->preprocess( $this->input );
		$this->assertEquals( $this->expectedOutput, $actualOutput );
	}
}