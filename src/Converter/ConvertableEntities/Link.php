<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Converter\IProcessable;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class Link implements IProcessable {
	/**
	 *
	 * @var ConversionDataLookup
	 */
	private $dataLookup = null;

	/**
	 *
	 * @var integer
	 */
	private $currentSpaceId = -1;

	/**
	 *
	 * @var string
	 */
	private $rawPageTitle = '';

	/**
	 *
	 * @param ConversionDataLookup $spaceIdPrefixMap
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct( $dataLookup, $currentSpaceId, $rawPageTitle ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
	}

	/**
	 * {@inheritDoc}
	 */
	public function process( ?ConfluenceConverter $sender, DOMNode $match, DOMDocument $dom, DOMXPath $xpath ): void {
		$attachmentEl = $xpath->query( './ri:attachment', $match )->item( 0 );
		$pageEl = $xpath->query( './ri:page', $match )->item( 0 );
		$userEl = $xpath->query( './ri:user', $match )->item( 0 );

		$linkParts = [];
		$isMediaLink = false;
		$isUserLink = false;
		$isBrokenPageLink = false;
		$isBrokenMediaLink = false;
		if ( $attachmentEl instanceof DOMElement ) {
			$riFilename = $attachmentEl->getAttribute( 'ri:filename' );
			$nestedPageEl = $xpath->query( './ri:page', $attachmentEl )->item( 0 );
			$rawPageTitle = $this->rawPageTitle;
			$spaceId = $this->currentSpaceId;
			if ( $nestedPageEl instanceof DOMElement ) {
				$rawPageTitle = $nestedPageEl->getAttribute( 'ri:content-title' );
				$spaceKey = $nestedPageEl->getAttribute( 'ri:space-key' );
				if ( !empty( $spaceKey ) ) {
					$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
				}
			}
			$rawPageTitle = basename( $rawPageTitle );
			$confluenceFileKey = "$spaceId---$rawPageTitle---$riFilename";
			$targetFilename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );
			if ( !empty( $targetFilename ) ) {
				$linkParts[] = $targetFilename;
			} else {
				$linkParts[] = $riFilename;
				$isBrokenMediaLink = true;
			}
			$isMediaLink = true;
		} elseif ( $pageEl instanceof DOMElement ) {
			$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
			$spaceId = $this->currentSpaceId;
			if ( !empty( $spaceKey ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
			}
			$rawPageTitle = $pageEl->getAttribute( 'ri:content-title' );
			$confluencePageKey = "$spaceId---$rawPageTitle";
			$targetTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( $confluencePageKey );
			if ( empty( $targetTitle ) ) {
				// If not in migation data, save some info for manual post migration work
				$linkParts[] = "Confluence---$confluencePageKey";
				$isBrokenPageLink = true;
			} else {
				$linkParts[] = $targetTitle;
			}
		} elseif ( $userEl instanceof DOMElement ) {
			$userKey = $userEl->getAttribute( 'ri:userkey' );
			if ( !empty( $userKey ) ) {
				$linkParts[] = 'User:' . $userKey;
			} else {
				$linkParts[] = 'NULL';
			}
			$isUserLink = true;
		} else { // "<ac:link />"
			$linkParts[] = 'NULL';
		}

		// Let's see if there is a description Text
		$linkBody = $xpath->query( './ac:link-body', $match )->item( 0 ); // HTML Content
		if ( $linkBody instanceof DOMElement === false ) {
			$linkBody = $xpath->query( './ac:plain-text-link-body', $match )->item( 0 ); // CDATA Content
		}
		if ( $linkBody instanceof DOMElement ) {
			$linkParts[] = $linkBody->nodeValue;
		}
		$linkParts = array_map( 'trim', $linkParts );

		$replacement = '[[Category:Broken_link]]';
		if ( !empty( $linkParts ) ) {
			if ( $isMediaLink ) {
				$replacement = $this->makeMediaLink( $linkParts );
			} else {
				$replacement = '[[' . implode( '|', $linkParts ) . ']]';
			}
		}

		if ( $isUserLink ) {
			$replacement .= '[[Category:Broken_user_link]]';
		}

		if ( $isBrokenPageLink ) {
			$replacement .= '[[Category:Broken_page_link]]';
		}

		if ( $isBrokenMediaLink ) {
			$replacement .= '[[Category:Broken_attachment_link]]';
		}

		$match->parentNode->replaceChild(
			$dom->createTextNode( $replacement ),
			$match
		);
	}

	public function makeMediaLink( $params ) {
		/*
		* The converter only knows the context of the current page that
		* is being converted
		* So unfortunately we don't know the source in this context so we
		* need to delegate this to the main migration script that has
		* all the information from the original XML
		*/
		$params = array_map( 'trim', $params );
		return '[[Media:' . implode( '|', $params ) . ']]';
	}
}
