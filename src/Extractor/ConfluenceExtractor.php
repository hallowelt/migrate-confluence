<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;

class ConfluenceExtractor extends ExtractorBase {

	/**
	 *
	 * @var DOMDocument
	 */
	private $dom = null;

	/**
	 * @var XMLHelper
	 */
	private $helper = null;

	/**
	 * @var array
	 */
	private $categories = [];


	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doExtract( SplFileInfo $file ): bool {
		$this->dom = new DOMDocument();
		$this->dom->load( $file->getPathname() );
		$this->helper = new XMLHelper( $this->dom );


		if ( isset( $this->config['config']['categories'] ) ) {
			$this->categories = $this->config['config']['categories'];
		}

		$this->extractBodyContents();
		$this->extractPageMetaData();

		return true;
	}

	private function extractBodyContents() {
		$bodyContents = $this->helper->getObjectNodes( 'BodyContent' );
		foreach ( $bodyContents as $bodyContent ) {
			$id = $this->helper->getIDNodeValue( $bodyContent );
			$bodyContentHTML = $this->getBodyContentHTML( $bodyContent );
			$targetFileName = $this->workspace->saveRawContent( $id, $bodyContentHTML );
			$this->addRevisionContent( $id, $targetFileName );
		}
	}

	/**
	 *
	 * @param DOMElement $bodyContent
	 * @return string
	 */
	private function getBodyContentHTML( DOMElement $bodyContent ) {
		$rawValue = $this->helper->getPropertyValue( 'body', $bodyContent );
		// For a strange reason the CDATA blocks are not closed properly...
		$fixedValue = str_replace( ']] >', ']]>', $rawValue );
		return '<html><body>' . $fixedValue . '</body></html>';
	}

	private function extractPageMetaData() {
		$labels = $this->helper->getObjectNodes( 'Label' );
		$labelMap = [];
		foreach ( $labels as $label ) {
			$id = $this->helper->getIDNodeValue( $label );
			$labelValue = $this->helper->getPropertyValue( 'name', $label );
			$labelNamespace = $this->helper->getPropertyValue( 'namespace', $label );

			// There may be `my` or `team` also
			if ( $labelNamespace !== 'global' ) {
				continue;
			}

			$labelMap[$id] = $labelValue;
		}

		$pages = $this->helper->getObjectNodes( 'Page' );
		foreach ( $pages as $page ) {
			$id = $this->helper->getIDNodeValue( $page );

			// Currently we only extract "Categories"
			$categories = [];
			$labellingEls = $this->helper->getElementsFromCollection( 'labellings', $page );
			foreach ( $labellingEls as $labellingEl ) {
				$labellingId = $this->helper->getIDNodeValue( $labellingEl );
				$labelling = $this->helper->getObjectNodeById( $labellingId, 'Labelling' );
				$labelProp = $this->helper->getPropertyNode( 'label', $labelling );
				$labelId = $this->helper->getIDNodeValue( $labelProp );
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
}
