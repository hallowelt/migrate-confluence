<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;
use XMLReader;

class ConfluenceExtractor extends ExtractorBase {

	/**
	 * @var DataBuckets
	 */
	private $customBuckets = null;

	/**
	 * @var array
	 */
	private $categories = [];

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );
		$this->customBuckets = new DataBuckets( [
			'extract-labelling-id-to-label-id-map',
			'extract-label-id-to-name-map',
		] );
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doExtract( SplFileInfo $file ): bool {
		$this->customBuckets->loadFromWorkspace( $this->workspace );

		if ( isset( $this->config['config']['categories'] ) ) {
			$this->categories = $this->config['config']['categories'];
		}

		$xmlReader = new XMLReader();

		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$objectXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $objectXML );

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'BodyContent' ) {
				$this->extractBodyContents( $objectDom );
			} elseif ( $class === "Labelling" ) {
				$this->buildLabellingMap( $objectDom );
			} elseif ( $class === "Label" ) {
				$this->buildLabelMap( $objectDom );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$objectXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $objectXML );

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'Page' ) {
				$this->extractPageMetaData( $objectDom );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$this->customBuckets->saveToWorkspace( $this->workspace );

		return true;
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function extractBodyContents( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );

		$bodyContents = $xmlHelper->getObjectNodes( 'BodyContent' );
		foreach ( $bodyContents as $bodyContent ) {
			$id = $xmlHelper->getIDNodeValue( $bodyContent );
			$bodyContentHTML = $this->getBodyContentHTML( $xmlHelper, $bodyContent );
			$targetFileName = $this->workspace->saveRawContent( $id, $bodyContentHTML );
			$this->addRevisionContent( $id, $targetFileName );
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildLabellingMap( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );

		$labellingObjs = $xmlHelper->getObjectNodes( 'Labelling' );
		if ( count( $labellingObjs ) < 1 ) {
			return;
		}
		$labelling = $labellingObjs->item( 0 );
		if ( $labelling instanceof DOMElement === false ) {
			return;
		}

		$id = $xmlHelper->getIDNodeValue( $labelling );

		$labelProp = $xmlHelper->getPropertyNode( 'label', $labelling );
		$labelId = $xmlHelper->getIDNodeValue( $labelProp );
		$labelMap = $this->customBuckets->getBucketData( 'extract-label-id-to-name-map' );
		if ( isset( $labelMap[$labelId] ) ) {
			$categories[] = $labelMap[$labelId];
		}

		$this->customBuckets->addData( 'extract-labelling-id-to-label-id-map', $id, $labelId, false, true );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildLabelMap( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );

		$labelObjs = $xmlHelper->getObjectNodes( 'Label' );
		if ( count( $labelObjs ) < 1 ) {
			return;
		}
		$label = $labelObjs->item( 0 );
		if ( $label instanceof DOMElement === false ) {
			return;
		}

		$labelNamespace = $xmlHelper->getPropertyValue( 'namespace', $label );
		// There may be `my` or `team` also
		if ( $labelNamespace !== 'global' ) {
			return;
		}

		$id = $xmlHelper->getIDNodeValue( $label );
		$name = $xmlHelper->getPropertyValue( 'name', $label );

		$this->customBuckets->addData( 'extract-label-id-to-name-map', $id, $name, false, true );
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $bodyContent
	 * @return void
	 */
	private function getBodyContentHTML( XMLHelper $xmlHelper, DOMElement $bodyContent ) {
		$rawValue = $xmlHelper->getPropertyValue( 'body', $bodyContent );
		// For a strange reason the CDATA blocks are not closed properly...
		$fixedValue = str_replace( ']] >', ']]>', $rawValue );
		return '<html><body>' . $fixedValue . '</body></html>';
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function extractPageMetaData( DOMDocument $dom ) {
		$labellingMap = $this->customBuckets->getBucketData( 'extract-labelling-id-to-label-id-map' );
		$labelMap = $this->customBuckets->getBucketData( 'extract-label-id-to-name-map' );

		$xmlHelper = new XMLHelper( $dom );

		$pageObjs = $xmlHelper->getObjectNodes( 'Page' );
		if ( count( $pageObjs ) < 1 ) {
			return;
		}

		foreach ( $pageObjs as $page ) {
			if ( $page instanceof DOMElement === false ) {
				continue;
			}
			$id = $xmlHelper->getIDNodeValue( $page );

			// Currently we only extract "Categories"
			$categories = [];
			$labellingEls = $xmlHelper->getElementsFromCollection( 'labellings', $page );
			foreach ( $labellingEls as $labellingEl ) {
				$labellingId = $xmlHelper->getIDNodeValue( $labellingEl );
				if ( !isset( $labellingMap[$labellingId] ) ) {
					continue;
				}
				$labelId = $labellingMap[$labellingId];
				if ( isset( $labelMap[$labelId] ) ) {
					$categories[] = $labelMap[$labelId];
				}
			}

			$categories = array_merge( $categories, $this->categories );

			$meta = [
				'categories' => $categories
			];

			$this->buckets->addData( 'global-title-metadata', $id, $meta, false );
		}
	}

	/**
	 *
	 * @param string $revisionReference
	 * @param string $contentReference
	 */
	protected function addRevisionContent( $revisionReference, $contentReference = 'n/a' ) {
		$this->buckets->addData( 'global-revision-contents', $revisionReference, $contentReference );
	}

	/**
	 *
	 * @param string $titleText
	 * @param string $meta
	 */
	protected function addTitleMetaData( $titleText, $meta = [] ) {
		$this->buckets->addData( 'global-title-metadata', $titleText, $meta, false );
	}
}
