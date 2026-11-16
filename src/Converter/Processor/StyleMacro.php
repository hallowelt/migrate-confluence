<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Turns the CSS body into a `{{Style}}` template call. The call is kept away from Pandoc by a placeholder.
 * The CSS is masked for template syntax: `|` becomes `{{!}}`, `=` becomes `{{=}}`, and brace pairs are
 * separated by a space (e.g. at the end of nested `@media` rules), which CSS ignores.
 */
class StyleMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'style';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'Widgets' ];

	/**
	 * @param PlaceholderManager $placeholderManager
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpace
	 */
	public function __construct(
		private PlaceholderManager $placeholderManager,
		private IConverterDataWriter $writer,
		private int $currentSpace
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function replacement( DOMElement $macro, array $params ): DOMNode {
		$holder = $macro->ownerDocument->createElement( 'span' );
		$this->appendBody( $macro, $holder );

		$wikitext = '{{Style|css=' . $this->mask( trim( $holder->textContent ) ) . "\n";
		foreach ( [ 'media', 'import' ] as $name ) {
			if ( ( $params[$name] ?? '' ) !== '' ) {
				$wikitext .= "|$name=" . $this->mask( trim( $params[$name] ) ) . "\n";
				unset( $params[$name] );
			}
		}
		// Other parameters are not known for this macro; keep them visible in the template call
		foreach ( $params as $name => $value ) {
			$wikitext .= '|' . $this->mask( (string)$name ) . '=' . $this->mask( $value ) . "\n";
		}
		$wikitext .= '}}';

		$this->writer->registerDefaultPage( $this->currentSpace, 'Style' );
		$this->writer->registerDefaultPage( $this->currentSpace, 'Style', 'Widget' );

		return $macro->ownerDocument->createTextNode( $this->placeholderManager->getPlaceholder( $wikitext ) );
	}

	/**
	 * @param string $css
	 * @return string
	 */
	private function mask( string $css ): string {
		$css = preg_replace( '/([{}])(?=\1)/', '$1 ', $css );
		return str_replace( [ '|', '=' ], [ '{{!}}', '{{=}}' ], $css );
	}
}
