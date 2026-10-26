<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DivMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TableMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TdMacro;

class HtmlTagMacroBaseTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\HtmlTagMacroBase::process
	 * @return void
	 */
	public function testUnnamedParameterIsKeptAsDataAttribute(): void {
		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="x"><ac:structured-macro ac:name="div"><ac:parameter ac:name="">odd</ac:parameter>'
			. '<ac:parameter ac:name="class">c</ac:parameter><ac:rich-text-body>t</ac:rich-text-body>'
			. '</ac:structured-macro></xml>'
		);
		( new DivMacro() )->process( $dom );

		$this->assertSame(
			'<xml xmlns:ac="x"><div class="c" data-params="{&quot;&quot;:&quot;odd&quot;}">t</div></xml>',
			$dom->saveXML( $dom->documentElement )
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\HtmlTagMacroBase::process
	 * @return void
	 */
	public function testLegacyMacroAndPlainTextBody(): void {
		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="x"><ac:macro ac:name="div"><ac:plain-text-body><![CDATA[a <b>]]></ac:plain-text-body>'
			. '</ac:macro></xml>'
		);
		( new DivMacro() )->process( $dom );

		$this->assertSame(
			'<xml xmlns:ac="x"><div>a &lt;b&gt;</div></xml>',
			$dom->saveXML( $dom->documentElement )
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\LegacyTableMacroBase::mapParams
	 * @dataProvider provideLegacyAttributes
	 * @param string $macro
	 * @param string $processor
	 * @param string $params
	 * @param string $expected
	 * @return void
	 */
	public function testLegacyAttributesBecomeStyle(
		string $macro, string $processor, string $params, string $expected
	): void {
		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="x"><ac:structured-macro ac:name="' . $macro . '">' . $params
			. '<ac:rich-text-body>t</ac:rich-text-body></ac:structured-macro></xml>'
		);
		( new $processor() )->process( $dom );

		$this->assertSame( "<xml xmlns:ac=\"x\">$expected</xml>", $dom->saveXML( $dom->documentElement ) );
	}

	/**
	 * @return array
	 */
	public function provideLegacyAttributes(): array {
		$p = static fn ( $n, $v ) => "<ac:parameter ac:name=\"$n\">$v</ac:parameter>";
		return [
			'td' => [
				'td', TdMacro::class,
				$p( 'align', 'right' ) . $p( 'valign', 'top' ) . $p( 'bgcolor', '#ff0000' ) . $p( 'nowrap', '' )
					. $p( 'style', 'color:red' ) . $p( 'colspan', '2' ),
				'<td style="color:red; text-align: right; vertical-align: top; background-color: #ff0000; '
					. 'white-space: nowrap;" colspan="2">t</td>'
			],
			'table' => [
				'table', TableMacro::class,
				$p( 'align', 'center' ) . $p( 'border', '1' ) . $p( 'cellspacing', '2' ) . $p( 'cellpadding', '3' ),
				'<table cellpadding="3" style="margin-left: auto; margin-right: auto; border-width: 1px; '
					. 'border-style: solid; border-collapse: separate; border-spacing: 2px;">t</table>'
			],
			'unsafe-value-is-kept' => [
				'td', TdMacro::class,
				$p( 'bgcolor', 'red; x: y' ),
				'<td bgcolor="red; x: y">t</td>'
			],
		];
	}
}
