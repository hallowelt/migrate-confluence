<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;

class ConfluenceExport extends AnalyzerBase {

	/**
	 *
	 * @var DOMDocument
	 */
	private $dom = null;


	/**
	 * @var DataBuckets
	 */
	private $customBuckets = null;

	/**
	 * @var XMLHelper
	 */
	private $helper = null;

	/**
	 *
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );
		$this->customBuckets = new DataBuckets( [
			'space-id-to-prefix-map'
		] );
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		if( $file->getFilename() !== 'entities.xml' ) {
			return true;
		}

		$this->customBuckets->loadFromWorkspace( $this->workspace );
		$result = parent::analyze( $file );
		$this->customBuckets->saveToWorkspace( $this->workspace );
		return $result;
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doAnalyze( SplFileInfo $file ): bool {

		$this->dom = new DOMDocument();
		$this->dom->load( $file->getPathname() );
		$this->helper = new XMLHelper( $this->dom );

		$this->makeSpacesMap();
		$this->makePagenamesMap();
		$this->makeAttachmentsMap();

		return true;
	}

	private function makeSpacesMap() {
		$spaces = $this->helper->getObjectNodes( 'Space' );
		foreach( $spaces as $space ) {
			$spaceId = $this->helper->getIDNodeValue( $space );
			$spaceKey = $this->helper->getPropertyValue( 'key', $space );

			//Confluence's GENERAL equals MediaWiki's NS_MAIN, thus having no prefix
			if ( $spaceKey === 'GENERAL' ) {
				$spaceKey = '';
			}
			$this->customBuckets->addData( 'space-id-to-prefix-map', $spaceId, $spaceKey, false );
		}
	}

	private function makePagenamesMap() {
		$pageNodes = $this->helper->getObjectNodes( "Page" );
		$spaceIdPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$titleBuilder = new TitleBuilder( $spaceIdPrefixMap, $this->helper );
		foreach( $pageNodes as $pageNode ) {
			if( $pageNode instanceof DOMElement === false ) {
				continue;
			}

			$status = $this->helper->getPropertyValue('contentStatus', $pageNode );
			if( $status !== 'current' ) {
				continue;
			}

			$spaceId = $this->helper->getPropertyValue( 'space', $pageNode );
			if( $spaceId === null ) {
				continue;
			}

			$originalVersionID = $this->helper->getPropertyValue( 'originalVersion', $pageNode );
			if( $originalVersionID !== null ) {
				continue;
			}

			$targetTitle = $titleBuilder->buildTitle( $pageNode );
			$revisionTimestamp = $this->buildRevisionTimestamp( $pageNode );
			$bodyContentIds = $this->getBodyContentIds( $pageNode );
			#$version = $this->helper->getPropertyValue( 'version', $pageNode );
			#$position = $this->helper->getPropertyValue( 'position', $pageNode );

			$this->addTitleRevision( $targetTitle, implode( '/', $bodyContentIds ) . '@' . $revisionTimestamp );

			$attachments = $this->helper->getElementsFromCollection( 'attachments', $pageNode );
			foreach ( $attachments as $attachment ) {
				$attachmentId = $this->helper->getIDNodeValue( $attachment );
				$this->addTitleAttachment( $targetTitle, $attachmentId );
			}
		}
	}

	/**
	 *
	 * @param DOMElement $pageNode
	 * @return string
	 */
	private function buildRevisionTimestamp( $pageNode ) {
		$lastModificationDate = $this->helper->getPropertyValue( 'lastModificationDate', $pageNode );
		$time = strtotime( $lastModificationDate );
		$mwTimestamp = date( 'YmdHis', $time );
		return $mwTimestamp;
	}

	/**
	 *
	 * @param DOMElement $pageNode
	 * @return array
	 */
	private function getBodyContentIds( $pageNode ) {
		$ids = [];
		$bodyContentEl = $this->helper->getElementsFromCollection( 'bodyContents', $pageNode );

		foreach( $bodyContentEl as $bodyContentElement ) {
			$ids[] = $this->helper->getIDNodeValue( $bodyContentElement );
		}
		return $ids;
	}

	private function makeAttachmentsMap() {
		$attachments = $this->helper->getObjectNodes( 'Attachment' );
		foreach( $attachments as $attachment ) {
			if( $attachment instanceof DOMElement === false ) {
				continue;
			}
			$originalVersionID = $this->helper->getPropertyValue( 'originalVersion', $attachment);
			if( $originalVersionID !== null ) {
				continue;
			}

			$attachmentId = $this->helper->getIDNodeValue( $attachment );
			$pageId = $this->helper->getPropertyValue( 'content', $attachment );

			$fileName = $this->helper->getPropertyValue( 'title', $attachment );
			$container = $this->helper->getPropertyValue( 'containerContent', $attachment );
			$attachmentVersion = $this->helper->getPropertyValue( 'version', $attachment );

			$spaceIdPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
			$filenameBuilder = new FilenameBuilder( $spaceIdPrefixMap, $this->helper );
			$targetName = $filenameBuilder->buildFilename( $attachment );

			/*
			 * Some attachments do not have a file extension available. We try
			 * to find an extension by looking a the content type, but
			 * sometimes even this won't help... ("octet-stream")
			 */
			$file = new SplFileInfo( $targetName );
			if( !$this->hasNoExplicitFileExtension( $file ) ){
				$contentType = $this->helper->getPropertyValue( 'contentType', $attachment );
				if( $contentType === 'application/gliffy+json' ) {
					$targetName .= '.json';
				}
				elseif ( $contentType === 'application/gliffy+xml' ) {
					$targetName .= '.xml';
				}
				else {
					echo(
						"Could not find file extension for $fileName as "
							. "{$attachment->getNodePath()}; "
							. "contentType: $contentType"
					);
				}
			}

			$path = "/".$container . $pageId.'/'.$attachmentId.'/'.$attachmentVersion;
			$this->addFile( $targetName, $path );
		}
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return boolean
	 */
	private function hasNoExplicitFileExtension( $file ) {
		if ( $file->getExtension() === '' ) {
			return true;
		}
		// Evil hack for Names like "02.1 Some-Workflow File"
		if ( strlen($file->getExtension()) > 10 ) {

		}
		return false;
	}
}