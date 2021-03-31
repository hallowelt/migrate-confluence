<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;


use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class Info implements \HalloWelt\MigrateConfluence\Converter\IProcessable
{

    /**
     * @inheritDoc
     * <ac:structured-macro ac:name="info" ac:schema-version="1" ac:macro-id="448329ba-06ad-4845-b3bf-2fd9a75c0d51">
     *	<ac:parameter ac:name="title">/api/Device/devices</ac:parameter>
     *	<ac:rich-text-body>
     *		<p class="title">...</p>
     *		<p>...</p>
     *	</ac:rich-text-body>
     * </ac:structured-macro>
     */
    public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath): void
    {
        $oTitleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item(0);

        $oNewContainer = $dom->createElement( 'div' );
        $oNewContainer->setAttribute( 'class', 'ac-info' );

        $match->parentNode->insertBefore( $oNewContainer, $match );

        if( $oTitleParam instanceof DOMElement ) {
            $oNewContainer->appendChild(
                $dom->createElement('h3', $oTitleParam->nodeValue )
            );
        }

        $oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
        //Move all content out of <ac::rich-text-body>
        while ( $oRTBody->childNodes->length > 0 ) {
            $oChild = $oRTBody->childNodes->item(0);
            $oNewContainer->appendChild( $oChild );
        }
    }
}