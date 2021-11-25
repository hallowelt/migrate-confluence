<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class Code implements \HalloWelt\MigrateConfluence\Converter\IProcessable {
	/**
	 * @inheritDoc
	 */
	public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath ): void {
		$titleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item( 0 );
		if ( $titleParam instanceof DOMElement ) {
			$match->parentNode->insertBefore(
				$dom->createElement( 'h6', $titleParam->nodeValue ),
				$match
			);
		}

		$oPlainTextBody = $xpath->query( './ac:plain-text-body', $match )->item( 0 );
		$sContent = '[[Category:Broken_macro/code/empty]]';
		if ( $oPlainTextBody instanceof DOMElement ) {
			$sContent = $oPlainTextBody->nodeValue;
		}

		$oLanguageParam = $xpath->query( './ac:parameter[@ac:name="language"]', $match )->item( 0 );
		$sLanguage = '';
		if ( $oLanguageParam instanceof DOMElement ) {
			$sLanguage = $oLanguageParam->nodeValue;
		}

		$syntaxhighlight = $dom->createElement( 'syntaxhighlight' );
		$code = $dom->createTextNode( $sContent );
		$syntaxhighlight->appendChild( $code );
		if ( $sLanguage !== '' ) {
			$syntaxhighlight->setAttribute( 'lang', $sLanguage );
		}

		$match->parentNode->insertBefore( $syntaxhighlight, $match );
		$match->parentNode->removeChild( $match );
	}
}
