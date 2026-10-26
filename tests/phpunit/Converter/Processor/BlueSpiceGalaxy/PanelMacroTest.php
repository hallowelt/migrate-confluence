<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\BlueSpiceGalaxy;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class PanelMacroTest extends ProcessorTestCase {

	private function convert(
		string $params,
		string $body = '<ac:rich-text-body><p>Body</p></ac:rich-text-body>'
	): string {
		$xml = '<html xmlns:ac="some" xmlns:ri="thing"><ac:structured-macro ac:name="panel">'
			. $params . $body . '</ac:structured-macro></html>';
		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$placeholderManager = new PlaceholderManager();
		( new PanelMacro( $placeholderManager ) )->process( $dom );

		return $placeholderManager->replacePlaceholders( $dom->saveXML( $dom->documentElement ) );
	}

	private static function param( string $name, string $value ): string {
		return '<ac:parameter ac:name="' . $name . '">' . $value . '</ac:parameter>';
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::doProcessMacro
	 */
	public function testRichBodyContentIsKeptBetweenCardTags(): void {
		$actual = $this->convert(
			self::param( 'title', 'My title' ),
			'<ac:rich-text-body><p>First</p><ul><li>Second</li></ul></ac:rich-text-body>'
		);

		$this->assertStringContainsString( '<div class="ext-standardtags-card">', $actual );
		$this->assertStringContainsString( '<div class="ext-standardtags-card-title">My title</div>', $actual );
		$this->assertStringContainsString(
			'<div class="ext-standardtags-card-body">' . "\n" . '<p>First</p><ul><li>Second</li></ul>' . "\n"
				. '</div></div>',
			$actual
		);
		$this->assertStringNotContainsString( 'ac:structured-macro', $actual );
		$this->assertStringNotContainsString( 'ac:rich-text-body', $actual );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::doProcessMacro
	 */
	public function testMissingRichBodyProducesEmptyCard(): void {
		$actual = $this->convert( self::param( 'title', 'T' ), '' );

		$this->assertStringContainsString(
			'<div class="ext-standardtags-card-body">' . "\n" . "\n" . '</div></div>',
			$actual
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::doProcessMacro
	 */
	public function testMissingPanelIconTextOmitsIcon(): void {
		$actual = $this->convert( self::param( 'title', 'T' ) );

		$this->assertStringNotContainsString( 'ext-standardtags-card-icon', $actual );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::getIcon
	 */
	public function testPanelIconTextShortNameIsMappedToEmoji(): void {
		$actual = $this->convert( self::param( 'panelIconText', ':smile:' ) );

		$this->assertStringContainsString(
			'<div class="ext-standardtags-card-icon bi" aria-hidden="true">😄</div>',
			$actual
		);
		$this->assertStringNotContainsString( ':smile:', $actual );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::getIcon
	 */
	public function testPanelIconTextEmojiIsKeptUnchanged(): void {
		$actual = $this->convert( self::param( 'panelIconText', '🚀' ) );

		$this->assertStringContainsString( 'aria-hidden="true">🚀</div>', $actual );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::getIcon
	 */
	public function testPanelIconTextUnknownShortNameIsKeptUnchanged(): void {
		$actual = $this->convert( self::param( 'panelIconText', ':no_such_emoji_xyz:' ) );

		$this->assertStringContainsString( 'aria-hidden="true">:no_such_emoji_xyz:</div>', $actual );
	}

	public static function provideBgColors(): array {
		return [
			'named color is mapped to Confluence set' => [ 'blue', '#E7F9FF' ],
			'another named color' => [ 'grey', '#F2F3F9' ],
			'hex value is kept' => [ '#aabbcc', '#aabbcc' ],
			'rgba value is kept' => [ 'rgba(1, 2, 3, 0.5)', 'rgba(1, 2, 3, 0.5)' ],
			'unmapped CSS name is kept' => [ 'red', 'red' ],
		];
	}

	/**
	 * @dataProvider provideBgColors
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::getBgColor
	 */
	public function testBgColor( string $input, string $expected ): void {
		$actual = $this->convert( self::param( 'bgColor', $input ) );

		$this->assertStringContainsString(
			'<div class="ext-standardtags-card" style="--st-card-accent: ' . $expected . '">',
			$actual
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\PanelMacro::doProcessMacro
	 */
	public function testMissingBgColorOmitsStyle(): void {
		$actual = $this->convert( self::param( 'title', 'T' ) );

		$this->assertStringNotContainsString( 'style=', $actual );
	}
}
