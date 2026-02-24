<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class MarkdownMacro implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$structuredMacros = $dom->getElementsByTagName( 'structured-macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			if ( $structuredMacro->getAttribute( 'ac:name' ) === 'markdown' ) {
				$macros[] = $structuredMacro;
			}
		}

		foreach ( $macros as $macro ) {
			$markdownContent = '';
			$plainTextBodies = $macro->getElementsByTagName( 'plain-text-body' );
			foreach ( $plainTextBodies as $plainTextBody ) {
				$markdownContent = $plainTextBody->nodeValue;
				break;
			}

			if ( $markdownContent === '' ) {
				continue;
			}

			$html = $this->convertMarkdownToHtml( $markdownContent );
			if ( $html === '' ) {
				continue;
			}

			$wrapper = $dom->createElement( 'div' );
			$wrapper->setAttribute( 'class', 'ac-markdown' );

			$htmlDoc = new DOMDocument();
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			@$htmlDoc->loadHTML( '<html><body>' . $html . '</body></html>' );
			$bodyNode = $htmlDoc->getElementsByTagName( 'body' )->item( 0 );
			foreach ( $bodyNode->childNodes as $child ) {
				$wrapper->appendChild( $dom->importNode( $child, true ) );
			}

			$macro->parentNode->replaceChild( $wrapper, $macro );
		}
	}

	/**
	 * @param string $markdown
	 * @return string
	 */
	private function convertMarkdownToHtml( string $markdown ): string {
		$tmpFile = tempnam( sys_get_temp_dir(), 'ac_markdown_' );
		file_put_contents( $tmpFile, $markdown );

		$command = 'pandoc -f markdown -t html ' . escapeshellarg( $tmpFile );
		$result = [];
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		exec( $command, $result, $returnCode );

		unlink( $tmpFile );

		if ( $returnCode !== 0 ) {
			return '';
		}

		return implode( "\n", $result );
	}
}
