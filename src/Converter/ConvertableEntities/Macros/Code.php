<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;


use DOMElement;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class Code implements \HalloWelt\MigrateConfluence\Converter\IProcessable
{
    /**
     * @inheritDoc
     */
    public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath): void
    {
        $titleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item(0);
        $oLanguageParam = $xpath->query( './ac:parameter[@ac:name="language"]', $match )->item(0);
        $sLanguage = '';
        if($oLanguageParam instanceof DOMElement) {
            $sLanguage = $oLanguageParam->nodeValue;
        }

        $oPlainTextBody = $xpath->query( './ac:plain-text-body', $match )->item(0);
        $sContent = $oPlainTextBody->nodeValue;

        $syntaxhighlight = $dom->createElement( 'syntaxhighlight', $sContent );
        if( $sLanguage !== '' ) {
            $syntaxhighlight->setAttribute( 'lang', $sLanguage );
        }
        if( empty( $sContent ) ) {
            //error_log("CODE: '$sLanguage': $sContent in {$file->getPathname()}");
            //$this->logMarkup( $dom->documentElement );
        }
        if( $titleParam instanceof DOMElement ) {
            $match->parentNode->insertBefore(
                $dom->createElement( 'h6', $titleParam->nodeValue ),
                $match
            );
        }

        $match->parentNode->insertBefore( $syntaxhighlight, $match );
    }
}