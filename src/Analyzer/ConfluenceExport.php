<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DOMDocument;
use DOMElement;
use Exception;
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
		$this->addAdditionalFiles();

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

			try {
				$targetTitle = $titleBuilder->buildTitle( $pageNode );
			} catch ( Exception $ex ) {
				$id = $this->helper->getIDNodeValue( $pageNode );
				$this->buckets->addData( 'title-invalids', $id, $ex->getMessage() );
				continue;
			}

			$revisionTimestamp = $this->buildRevisionTimestamp( $pageNode );
			$bodyContentIds = $this->getBodyContentIds( $pageNode );
			$version = $this->helper->getPropertyValue( 'version', $pageNode );

			$this->addTitleRevision( $targetTitle, implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp" );

			$attachmentRefs = $this->helper->getElementsFromCollection( 'attachments', $pageNode );
			foreach ( $attachmentRefs as $attachmentRef ) {
				$attachmentId = $this->helper->getIDNodeValue( $attachmentRef );
				$attachment = $this->helper->getObjectNodeById( $attachmentId, 'Attachment' );
				$attachmentTargetFilename = $this->makeAttachmentTargetFilename( $attachment, $targetTitle );
				$attachmentReference = $this->makeAttachmentReference( $attachment );
				$this->addTitleAttachment( $targetTitle, $attachmentTargetFilename );
				$this->addFile( $attachmentTargetFilename, $attachmentReference );
			}
		}
	}

	/**
	 *
	 * @param DOMElement $attachment
	 * @param string $containerTitle
	 * @return string
	 */
	private function makeAttachmentTargetFilename( $attachment, $containerTitle ) {
		$fileName = $this->helper->getPropertyValue( 'title', $attachment );

		$spaceIdPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$filenameBuilder = new FilenameBuilder( $spaceIdPrefixMap, $this->helper );
		$targetName = $filenameBuilder->buildFilename( $attachment, $containerTitle );

		/*
		 * Some attachments do not have a file extension available. We try
		 * to find an extension by looking a the content type, but
		 * sometimes even this won't help... ("octet-stream")
		 */
		$file = new SplFileInfo( $targetName );
		if( $this->hasNoExplicitFileExtension( $file ) ){
			$contentType = $this->helper->getPropertyValue( 'contentType', $attachment );
			if( $contentType === 'application/gliffy+json' ) {
				$targetName .= '.json';
			}
			elseif ( $contentType === 'application/gliffy+xml' ) {
				$targetName .= '.xml';
			}
			else {
				error_log(
					"Could not find file extension for $fileName as "
						. "{$attachment->getNodePath()}; "
						. "contentType: $contentType"
				);
			}
		}

		return $targetName;
	}

	/**
	 *
	 * @param DOMElement $attachment
	 * @return string
	 */
	private function makeAttachmentReference( $attachment ) {
		$basePath = $this->currentFile->getPath() . '/attachments/';
		$attachmentId = $this->helper->getIDNodeValue( $attachment );
		$containerId = $this->helper->getPropertyValue( 'content', $attachment );
		if ( empty( $containerId ) ) {
			$containerId = $this->helper->getPropertyValue( 'containerContent', $attachment );
		}
		$attachmentVersion = $this->helper->getPropertyValue( 'attachmentVersion', $attachment );
		if( empty( $attachmentVersion ) ) {
			$attachmentVersion = $this->helper->getPropertyValue( 'version', $attachment );
		}

		/**
		 * Sometimes there is no explicit version set in the "attachment" object. In such cases
		 * there we always fetch the highest number from the respective directory
		 */
		if( empty( $attachmentVersion ) ) {
			$attachmentVersion = '__LATEST__';
		}

		$path = $basePath . "/". $containerId .'/'.$attachmentId.'/'.$attachmentVersion;
		return $path;
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

	private function addAdditionalFiles() {
		$attachments = $this->helper->getObjectNodes( 'Attachment' );
		foreach( $attachments as $attachment ) {
			if( $attachment instanceof DOMElement === false ) {
				continue;
			}
			$originalVersionID = $this->helper->getPropertyValue( 'originalVersion', $attachment);

			// Skip legacy versions
			if( $originalVersionID !== null ) {
				continue;
			}

			$sourceContentID = $this->helper->getPropertyValue( 'sourceContent', $attachment);
			if( !empty( $sourceContentID ) ) {
				// This has already been added as a page attachment
				continue;
			}

			$path = $this->makeAttachmentReference( $attachment );
			$targetName = $this->makeAttachmentTargetFilename( $attachment, '' );
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