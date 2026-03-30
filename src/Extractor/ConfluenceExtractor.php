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
		$this->buckets->loadFromWorkspace( $this->workspace );
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
			} elseif ( $class === 'BlogPost' ) {
				$this->extractBlogMetaData( $objectDom );
			} elseif ( $class === 'Attachment' ) {
				$this->extractAttachmentMetaData( $objectDom );
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
		$bodyContentsToPagesMap = $this->buckets->getBucketData(
			'global-body-content-id-to-page-id-map'
		);
		$bodyContentsToSpaceDescriptionMap = $this->buckets->getBucketData(
			'global-body-content-id-to-space-description-id-map'
		);
		$bodyContentsToCommentsMap = $this->buckets->getBucketData(
			'global-body-content-id-to-comment-id-map'
		);
		$xmlHelper = new XMLHelper( $dom );

		$bodyContents = $xmlHelper->getObjectNodes( 'BodyContent' );
		foreach ( $bodyContents as $bodyContent ) {
			$id = $xmlHelper->getIDNodeValue( $bodyContent );
			if (
				!isset( $bodyContentsToPagesMap[ $id ] )
				&& !isset( $bodyContentsToSpaceDescriptionMap[ $id ] )
				&& !isset( $bodyContentsToCommentsMap[ $id ] )
			) {
				continue;
			}
			$bodyContentHTML = $this->getBodyContentHTML( $xmlHelper, $bodyContent );
			$targetFileName = $this->workspace->saveRawContent( $id, $bodyContentHTML );
			if ( isset( $bodyContentsToCommentsMap[ $id ] ) ) {
				// Comment body contents are only saved to workspace for conversion;
				// they do not become page revisions themselves.
				continue;
			}
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
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function extractAttachmentMetaData( DOMDocument $dom ): void {
		$labellingMap = $this->customBuckets->getBucketData( 'extract-labelling-id-to-label-id-map' );
		$labelMap = $this->customBuckets->getBucketData( 'extract-label-id-to-name-map' );

		$xmlHelper = new XMLHelper( $dom );

		$attachmentObjs = $xmlHelper->getObjectNodes( 'Attachment' );
		if ( count( $attachmentObjs ) < 1 ) {
			return;
		}
		$attachment = $attachmentObjs->item( 0 );
		if ( $attachment instanceof DOMElement === false ) {
			return;
		}

		$attachmentId = $xmlHelper->getIDNodeValue( $attachment );
		if ( $attachmentId < 0 ) {
			return;
		}

		$labelNames = [];
		$labellingEls = $xmlHelper->getElementsFromCollection( 'labellings', $attachment );
		foreach ( $labellingEls as $labellingEl ) {
			$labellingId = $xmlHelper->getIDNodeValue( $labellingEl );
			if ( !isset( $labellingMap[$labellingId] ) ) {
				continue;
			}
			$labelId = $labellingMap[$labellingId];
			if ( isset( $labelMap[$labelId] ) ) {
				$labelNames[] = $labelMap[$labelId];
			}
		}

		if ( !empty( $labelNames ) ) {
			$this->addAttachmentMetaData( $attachmentId, [ 'labels' => $labelNames ] );
		}
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

			$this->addTitleMetaData( $id, $meta );
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function extractBlogMetaData( DOMDocument $dom ) {
		$labellingMap = $this->customBuckets->getBucketData( 'extract-labelling-id-to-label-id-map' );
		$labelMap = $this->customBuckets->getBucketData( 'extract-label-id-to-name-map' );

		$xmlHelper = new XMLHelper( $dom );

		$blogObjs = $xmlHelper->getObjectNodes( 'BlogPost' );
		if ( count( $blogObjs ) < 1 ) {
			return;
		}

		foreach ( $blogObjs as $blog ) {
			if ( $blog instanceof DOMElement === false ) {
				continue;
			}
			$id = $xmlHelper->getIDNodeValue( $blog );

			// Currently we only extract "Categories"
			$categories = [];
			$labellingEls = $xmlHelper->getElementsFromCollection( 'labellings', $blog );
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

			$this->addBlogTitleMetaData( $id, $meta );
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
	 * @param array $meta
	 */
	protected function addTitleMetaData( $titleText, $meta = [] ) {
		$this->buckets->addData( 'global-title-metadata', $titleText, $meta, false );
	}

	/**
	 *
	 * @param string $titleText
	 * @param array $meta
	 */
	protected function addBlogTitleMetaData( $titleText, $meta = [] ) {
		$this->buckets->addData( 'global-blog-title-metadata', $titleText, $meta, false );
	}

	/**
	 *
	 * @param int $attachmentId
	 * @param array $meta
	 */
	protected function addAttachmentMetaData( $attachmentId, $meta = [] ) {
		$this->buckets->addData( 'global-attachment-metadata', $attachmentId, $meta, false );
	}
}
