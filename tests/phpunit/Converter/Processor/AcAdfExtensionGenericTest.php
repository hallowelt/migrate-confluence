<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AcAdfExtensionGeneric;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class AcAdfExtensionGenericTest extends ProcessorTestCase {

	private const FALLBACK_CAT = '[[Category:Migration/Adf_extension_fallback]]';

	/**
	 * @param string $body
	 * @return DOMDocument
	 */
	private function load( string $body ): DOMDocument {
		$dom = new DOMDocument();
		$dom->loadXML( '<xml xmlns:ac="some">' . $body . '</xml>' );
		return $dom;
	}

	/**
	 * @param DOMDocument $dom
	 * @return PlaceholderManager
	 */
	private function runProcessor( DOMDocument $dom ): PlaceholderManager {
		$manager = new PlaceholderManager();
		( new AcAdfExtensionGeneric( $manager ) )->process( $dom );
		return $manager;
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\AcAdfExtensionGeneric::process
	 */
	public function testFallbackIsUnwrapped(): void {
		$dom = $this->load(
			'<p>before</p>'
			. '<ac:adf-extension>'
			. '<ac:adf-node type="panel"><ac:adf-content><p>Note panel</p></ac:adf-content></ac:adf-node>'
			. '<ac:adf-fallback><div class="panel"><p>Note panel</p></div></ac:adf-fallback>'
			. '</ac:adf-extension><p>after</p>'
		);
		$this->runProcessor( $dom );

		$this->assertSame(
			'<xml xmlns:ac="some"><p>before</p><div class="panel"><p>Note panel</p></div>'
				. self::FALLBACK_CAT . '<p>after</p></xml>',
			$dom->saveXML( $dom->documentElement )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\AcAdfExtensionGeneric::process
	 */
	public function testWithoutFallbackBecomesPlaceholderDiv(): void {
		$dom = $this->load(
			'<ac:adf-extension><ac:adf-node type="panel"><ac:adf-content>x</ac:adf-content></ac:adf-node>'
			. '</ac:adf-extension>'
		);
		$manager = $this->runProcessor( $dom );

		$div = $dom->documentElement->firstChild;
		$this->assertSame( 'div', $div->nodeName );
		$this->assertSame( 'ac-ext-panel', $div->getAttribute( 'class' ) );
		$this->assertStringEndsWith( '[[Category:Broken_adf_extension]]', $div->textContent );

		$restored = $manager->replacePlaceholders( $div->textContent );
		$this->assertStringStartsWith( '<!--<ac:adf-extension', $restored );
		$this->assertStringContainsString( 'type="panel"', $restored );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\AcAdfExtensionGeneric::process
	 */
	public function testWithoutNodeTypeIsUnknown(): void {
		$dom = $this->load( '<ac:adf-extension/>' );
		$this->runProcessor( $dom );

		$this->assertSame( 'ac-ext-unknown', $dom->documentElement->firstChild->getAttribute( 'class' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\AcAdfExtensionGeneric::process
	 */
	public function testNestedExtensionsAreAllReplaced(): void {
		$dom = $this->load(
			'<ac:adf-extension><ac:adf-fallback>'
			. '<ac:adf-extension><ac:adf-fallback>inner</ac:adf-fallback></ac:adf-extension>'
			. '</ac:adf-fallback></ac:adf-extension>'
		);
		$this->runProcessor( $dom );

		$this->assertSame(
			'<xml xmlns:ac="some">inner' . self::FALLBACK_CAT . self::FALLBACK_CAT . '</xml>',
			$dom->saveXML( $dom->documentElement )
		);
	}
}
