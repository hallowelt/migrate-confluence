<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\IPostprocessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class RestoreExcerptIncludeMacro implements IPostprocessor {

	public function __construct( private readonly DBConversionDataLookup $dataLookup ) {
	}

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$replace = function ( array $matches ): string {
			$attributes = [
				'showpanel' => $matches[1] ?? '',
				'excerpt' => $matches[3] ?? '',
				'page' => $matches[2] ?? '',
			];

			// Add missing excerpt name fallback
			if ( empty( $attributes['excerpt'] ) ) {
				$attributes['excerpt'] = $this->createExcerptNameFallback( $attributes['page'] );
			}

			$attrString = '';
			foreach ( $attributes as $name => $value ) {
				if ( $value !== '' ) {
					$attrString .= sprintf( ' %s="%s"', $name, $value );
				}
			}

			return "<excerpt-include$attrString/>";
		};

		return preg_replace_callback(
			'/#####EXCERPTINCLUDE\|(.*?)\|(.*?)\|(.*?)#####/',
			$replace,
			$wikiText
		);
	}

	/**
	 * If no fallback can be found, keep it empty
	 */
	private function createExcerptNameFallback( string $wikiTitle ): string {
		$page = $this->dataLookup->getPageByWikiTitle( $wikiTitle );
		if ( !$page ) {
			return "";
		}

		$bodyContent = $this->dataLookup->getConfluencePageBodyContent( $page['body_content_ids'] );
		if ( !$bodyContent ) {
			return "";
		}

		$excerptFallbackName = $this->findFirstExcerptName( $bodyContent );

		if ( !$excerptFallbackName ) {
			// If no given, use default fallback from ExcerptMacro.php
			return ExcerptMacro::EXCERPT_NAME_FALLBACK_PREFIX . "0";
		}

		return $excerptFallbackName;
	}

	private function findFirstExcerptName( string $content ): ?string {
		$content = str_replace( '&nbsp;', '&#160;', $content );

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<root xmlns:ac="http://www.atlassian.com/schema/confluence/4/ac/" ' .
			'xmlns:ri="http://www.atlassian.com/schema/confluence/4/ri/">' .
			$content .
			'</root>';

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$ok = $doc->loadXML( $xml );
		libxml_clear_errors();
		if ( !$ok ) {
			return null;
		}

		$xpath = new DOMXPath( $doc );
		$xpath->registerNamespace( 'ac', 'http://www.atlassian.com/schema/confluence/4/ac/' );

		// First <ac:parameter ac:name="name"> that is a direct child of an excerpt macro
		$node = $xpath->query(
			'(//ac:structured-macro[@ac:name="excerpt"]/ac:parameter[@ac:name="name"])[1]'
		)->item( 0 );

		return $node?->textContent ?? null;
	}

}
