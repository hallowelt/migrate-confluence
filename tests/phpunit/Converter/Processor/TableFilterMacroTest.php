<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro;

class TableFilterMacroTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = file_get_contents( "$this->dir/table-filter-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TableFilterMacro( $this->createConverterDataWriter(), 1 );
		$processor->process( $dom );

		$expectedDom = new DOMDocument();
		$expectedDom->load( "$this->dir/table-filter-macro-output.xml" );
		$this->assertDomXmlEquals( $expectedDom, $dom );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro::process
	 * @dataProvider provideOptionCases
	 * @param string $parameters
	 * @param bool $expectDatagrid
	 * @param string|null $expectedStyle
	 * @return void
	 */
	public function testOptions( string $parameters, bool $expectDatagrid, ?string $expectedStyle ) {
		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace"><ac:structured-macro ac:name="table-filter">'
			. $parameters
			. '<ac:rich-text-body><table><tbody>'
			. '<tr><th>A</th><th style="color: red;">B</th></tr><tr><td>1</td><td>2</td></tr>'
			. '</tbody></table></ac:rich-text-body></ac:structured-macro></xml>'
		);

		( new TableFilterMacro( $this->createConverterDataWriter(), 1 ) )->process( $dom );

		$table = $dom->getElementsByTagName( 'table' )->item( 0 );
		$this->assertSame( $expectDatagrid, $table->getAttribute( 'class' ) === 'datagrid' );
		$this->assertTrue( $table->hasAttribute( 'data-filterparams' ) );
		$styles = [];
		foreach ( $table->getElementsByTagName( '*' ) as $cell ) {
			if ( $cell->nodeName === 'th' || $cell->nodeName === 'td' ) {
				$styles[] = $cell->getAttribute( 'style' );
			}
		}
		if ( $expectedStyle === null ) {
			$this->assertSame( [ '', 'color: red;', '', '' ], $styles );
			return;
		}
		$this->assertSame(
			[ $expectedStyle, "color: red; $expectedStyle", $expectedStyle, $expectedStyle ],
			$styles
		);
	}

	/**
	 * @return array
	 */
	public static function provideOptionCases(): array {
		$param = static fn ( string $name, string $value ) => "<ac:parameter ac:name=\"$name\">$value</ac:parameter>";
		return [
			'disabled' => [ $param( 'disabled', 'true' ), false, null ],
			'not disabled' => [ $param( 'disabled', 'false' ), true, null ],
			'cell-width' => [ $param( 'cell-width', '250' ), true, 'min-width: 250px;' ],
			'cell-width with unit' => [ $param( 'cell-width', '120px' ), true, 'min-width: 120px;' ],
			'cell-width not numeric' => [ $param( 'cell-width', ',' ), true, null ],
		];
	}

}
