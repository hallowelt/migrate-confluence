<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Converter\IProcessable;

class Link implements IProcessable
{
	/**
	 * {@inheritDoc}
	 */
    public function process( ?ConfluenceConverter $sender, DOMNode $match, DOMDocument $dom, DOMXPath $xpath ): void
    {
        $attachmentEl = $xpath->query( './ri:attachment', $match )->item(0);
        $pageEl = $xpath->query( './ri:page', $match )->item(0);
        $userEl = $xpath->query( './ri:user', $match )->item(0);

        $linkParts = array();
        $isMediaLink = false;
        $isUserLink = false;
        if( $attachmentEl instanceof DOMElement ) {
            $linkParts[] = $attachmentEl->getAttribute( 'ri:filename' );
            $isMediaLink = true;
        }
        elseif( $pageEl instanceof DOMElement ) {
            $linkParts[] = $pageEl->getAttribute( 'ri:content-title' );
        }
        elseif( $userEl instanceof DOMElement ) {
            $userKey = $userEl->getAttribute( 'ri:userkey' );
            if( !empty( $userKey ) ) {
                $linkParts[] = 'User:'.$userKey;
            }
            else {
                $linkParts[] = 'NULL';
            }
            $isUserLink = true;
        }
        else { //"<ac:link />"
            $linkParts[] = 'NULL';
        }

        //Let's see if there is a description Text
        $linkBody = $xpath->query( './ac:link-body', $match )->item(0); //HTML Content
        if( $linkBody instanceof DOMElement === false ) {
            $linkBody = $xpath->query( './ac:plain-text-link-body', $match )->item(0); //CDATA Content
        }
        if( $linkBody instanceof DOMElement ) {
            $linkParts[] = $linkBody->nodeValue;
        }

        //$this->notify( 'processLink', array( $match, $dom, $xpath, &$linkParts ) );

        $replacement = '[[Category:Broken_link]]';
        if( !empty( $linkParts ) ) {
            if( $isMediaLink ) {
                $replacement = $this->makeMediaLink( $linkParts );
            }
            else {
                $replacement = '[['.implode( '|', $linkParts ).']]';
            }
        }

        if( $isUserLink ) {
            $replacement .= '[[Category:Broken_user_link]]';
        }

        $match->parentNode->replaceChild(
            $dom->createTextNode( $replacement ),
            $match
        );
    }

    public function makeMediaLink( $params )
	{
        /*
        * The converter only knows the context of the current page that
        * is being converted
        * So unfortunately we don't know the source in this context so we
        * need to delegate this to the main migration script that has
        * all the information from the original XML
        */
        $params = array_map( 'trim', $params );
        //$this->notify('makeMediaLink', array( &$params ) );
        return '[[Media:'.implode( '|', $params ).']]';
    }

    public function postProcess( $params )
	{

    }
}