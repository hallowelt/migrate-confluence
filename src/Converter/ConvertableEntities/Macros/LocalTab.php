<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;


use DOMDocument;
use DOMNode;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Converter\IProcessable;

class LocalTab implements IProcessable
{

    /**
     * {@inheritDoc}
     *
     *   <ac::macro ac:name="localtabgroup">
     *   <ac::rich-text-body>
     *   <ac::macro ac:name="localtab">
     *   <ac::parameter ac:name="title">...</acparameter>
     *   <ac::rich-text-body>...</acrich-text-body>
     *   </ac:macro>
     *   </ac:rich-text-body>
     *   </ac:macro>
     */
    public function process( ?ConfluenceConverter $sender, DOMNode $match, DOMDocument $dom, DOMXPath $xpath): void
    {
        if( $sMacroName === 'localtabgroup' ) {
            //Append the "<headertabs />" tag
            $match->parentNode->appendChild(
                $dom->createTextNode('<headertabs />')
            );
        }
        elseif ( $sMacroName === 'localtab' ) {
            $oTitleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item(0);
            //Prepend the heading
            $match->parentNode->insertBefore(
                $dom->createElement('h1', $oTitleParam->nodeValue ),
                $match
            );
        }

        $oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
        //Move all content out of <ac:rich-text-body>
        while ( $oRTBody->childNodes->length > 0 ) {
            $oChild = $oRTBody->childNodes->item(0);
            $match->parentNode->insertBefore( $oChild, $match );
        }
    }
}