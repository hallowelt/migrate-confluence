<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro;
use HalloWelt\MigrateConfluence\Tests\Database\InterwikiDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class InterwikiIncludeMacroTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro::process
	 * @return void
	 */
	public function testResolvesInterwikiIncludeTargetsUsingCurrentPageVersions(): void {
		$xml = '<xml xmlns:ac="some" xmlns:ri="thing">'
			. '<div><ac:structured-macro ac:name="include"><ac:parameter ac:name=""><ac:link>'
			. '<ri:page ri:content-title="Page 1" ri:space-key="SPC1" />'
			. '</ac:link></ac:parameter></ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="include"><ac:parameter ac:name=""><ac:link>'
			. '<ri:page ri:content-title="Page 26" ri:space-key="SPC2" />'
			. '</ac:link></ac:parameter></ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="include"><ac:parameter ac:name=""><ac:link>'
			. '<ri:page ri:content-title="Page 76" ri:space-key="SPC4" />'
			. '</ac:link></ac:parameter></ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="include"><ac:parameter ac:name=""><ac:link>'
			. '<ri:page ri:content-title="Not Existing" ri:space-key="SPC3" />'
			. '</ac:link></ac:parameter></ac:structured-macro></div>'
			. '</xml>';

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new IncludeMacro( $dataLookup, 1 );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( '{{:Space_1:Main_page}}', $actualOutput );
		$this->assertStringContainsString( '{{:wiki-space_2:Main_page}}', $actualOutput );
		$this->assertStringContainsString( '{{:wiki-space_4:Space_4}}', $actualOutput );
		$this->assertStringContainsString( '{{:}}[[Category:Broken_macro/Include]]', $actualOutput );
	}
}
