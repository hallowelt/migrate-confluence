<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixImagesWithExternalUrl;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTemplate;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\NestedHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreCode;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestorePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreStructuredMacroTasksReport;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTimeTag;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\CDATAClosingFixer;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentLink;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertInfoMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertInlineCommentMarkerMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertNoteMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertPlaceholderMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertStatusMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertTaskListMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertTipMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertWarningMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsSummaryMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\Emoticon;
use HalloWelt\MigrateConfluence\Converter\Processor\ExpandMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\Image;
use HalloWelt\MigrateConfluence\Converter\Processor\MacroAlign;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveCode;
use HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveStructuredMacroTasksReport;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveTimeTag;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroAttachments;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroChildren;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroColumn;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroContenByLabel;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroDrawio;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroExcerptInclude;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroGliffy;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroInclude;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroJira;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroNoFormat;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroPageTree;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroPanel;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroRecentlyUpdated;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroSection;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroToc;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroViewFile;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLink;
use HalloWelt\MigrateConfluence\Converter\Processor\Widget;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

class ConfluenceConverter extends PandocHTML implements IOutputAwareInterface {

	/** @var bool */
	protected $bodyContentFile = null;

	/** @var DataBuckets */
	private $dataBuckets = null;

	/** @var DataBuckets */
	private $customBuckets = null;

	/** @var ConversionDataLookup */
	private $dataLookup = null;

	/** @var ConversionDataWriter */
	private $conversionDataWriter = null;

	/** @var SplFileInfo */
	private $rawFile = null;

	/** @var string */
	private $wikiText = '';

	/** @var string */
	private $currentPageTitle = '';

	/** @var string */
	private $currentMainPage = '';

	/** @var int */
	private $currentSpace = 0;

	/** @var SplFileInfo */
	private $preprocessedFile = null;

	/** @var Output */
	private $output = null;

	/** @var bool */
	private $nsFileRepoCompat = false;

	/** @var string */
	private $mainpage = 'Main Page';

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 */
	public function __construct( $config, Workspace $workspace ) {
		parent::__construct( $config, $workspace );

		$this->dataBuckets = new DataBuckets( [
			'page-id-to-title-map',
			'pages-titles-map',
			'title-attachments',
			'body-contents-to-pages-map',
			'page-id-to-space-id',
			'space-id-to-prefix-map',
			'space-key-to-prefix-map',
			'filenames-to-filetitles-map',
			'title-metadata',
			'attachment-orig-filename-target-filename-map',
			'files',
			'userkey-to-username-map',
			'space-description-id-to-body-id-map',
			'gliffy-map',
			'attachment-confluence-file-key-to-target-filename-map'
		] );

		$this->dataBuckets->loadFromWorkspace( $this->workspace );

		$this->customBuckets = new DataBuckets( [
			'title-uploads',
			'title-uploads-fail'
		] );
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 * @inheritDoc
	 */
	protected function doConvert( SplFileInfo $file ): string {
		$this->output->writeln( $file->getPathname() );
		$this->dataLookup = ConversionDataLookup::newFromBuckets( $this->dataBuckets );
		$this->conversionDataWriter = ConversionDataWriter::newFromBuckets( $this->dataBuckets );
		$this->rawFile = $file;

		if ( isset( $this->config['config']['ext-ns-file-repo-compat'] )
			&& $this->config['config']['ext-ns-file-repo-compat'] === true
			) {
				$this->nsFileRepoCompat = true;
		}

		if ( isset( $this->config['config']['mainpage'] ) ) {
			$this->mainpage = $this->config['config']['mainpage'];
		}

		$bodyContentId = $this->getBodyContentIdFromFilename();
		$pageId = $this->getPageIdFromBodyContentId( $bodyContentId );
		if ( $pageId === -1 ) {
			$pageId = $this->getSpaceDescriptionIDFromBodyContentId( $bodyContentId );
		}
		if ( $pageId === -1 ) {
			return '<-- No context page id found -->';
		}
		$this->currentSpace = $this->getSpaceIdFromPageId( $pageId );

		$pagesIdsToTitlesMap = $this->dataBuckets->getBucketData( 'page-id-to-title-map' );
		if ( isset( $pagesIdsToTitlesMap[$pageId] ) ) {
			$this->currentPageTitle = $pagesIdsToTitlesMap[$pageId];
		} else {
			$this->currentPageTitle = 'not_current_revision_' . $pageId;
		}

		$dom = $this->preprocessFile();

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'ac', 'some' );
		$xpath->registerNamespace( 'ri', 'thing' );
		$replacings = $this->makeReplacings();
		foreach ( $replacings as $xpathQuery => $callback ) {
			$matches = $xpath->query( $xpathQuery );
			$nonLiveListMatches = [];
			foreach ( $matches as $match ) {
				$nonLiveListMatches[] = $match;
			}
			foreach ( $nonLiveListMatches as $match ) {
				//phpcs:ignore Generic.Files.LineLength.TooLong
				// See: https://wiki.hallowelt.com/index.php/Technik/Migration/Confluence_nach_MediaWiki#Inhalte
				//phpcs:ignore Generic.Files.LineLength.TooLong
				// See: https://confluence.atlassian.com/doc/confluence-storage-format-790796544.html
				call_user_func_array(
					$callback,
					[ $this, $match, $dom, $xpath ]
				);
			}
		}

		$this->runProcessors( $dom );
		$this->postProcessDOM( $dom, $xpath );

		$dom->saveHTMLFile(
			$this->preprocessedFile->getPathname()
		);

		$this->wikiText = parent::doConvert( $this->preprocessedFile );
		$this->runPostProcessors();

		$this->postProcessLinks();
		$this->postprocessWikiText();

		$this->customBuckets->saveToWorkspace( $this->workspace );

		return $this->wikiText;
	}

	/**
	 *
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function runProcessors( $dom ) {
		$currentPageTitle = $this->getCurrentPageTitle();

		$processors = [
			new ConvertPlaceholderMacro(),
			new ConvertInlineCommentMarkerMacro(),
			new PreserveTimeTag(),
			new ConvertTipMacro(),
			new ConvertInfoMacro(),
			new ConvertNoteMacro(),
			new ConvertWarningMacro(),
			new ConvertStatusMacro(),
			new StructuredMacroToc(),
			new StructuredMacroPanel(),
			new StructuredMacroColumn(),
			new StructuredMacroSection(),
			new StructuredMacroChildren( $this->currentPageTitle ),
			new StructuredMacroPageTree(
				$this->dataLookup, $this->currentSpace, $this->currentPageTitle, $this->mainpage
			),
			new StructuredMacroRecentlyUpdated( $this->currentPageTitle ),
			new StructuredMacroInclude( $this->dataLookup, $this->currentSpace ),
			new StructuredMacroExcerptInclude( $this->dataLookup, $this->currentSpace ),
			new Emoticon(),
			new PreserveStructuredMacroTasksReport( $this->dataLookup ),
			new Image(
				$this->dataLookup, $this->currentSpace, $currentPageTitle, $this->nsFileRepoCompat
			),
			new AttachmentLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle, $this->nsFileRepoCompat
			),
			new PageLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle, $this->nsFileRepoCompat
			),
			new UserLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle, $this->nsFileRepoCompat
			),
			new PreserveCode(),
			new StructuredMacroNoFormat(),
			new ConvertTaskListMacro(),
			new StructuredMacroDrawio(
				$this->dataLookup, $this->conversionDataWriter, $this->currentSpace,
				$currentPageTitle, $this->nsFileRepoCompat
			),
			new StructuredMacroGliffy(
				$this->dataLookup, $this->conversionDataWriter, $this->currentSpace,
				$currentPageTitle, $this->customBuckets, $this->nsFileRepoCompat
			),
			new StructuredMacroContenByLabel( $this->currentPageTitle ),
			new StructuredMacroAttachments(),
			new ExpandMacro(),
			new DetailsMacro(),
			new DetailsSummaryMacro(),
			new MacroAlign(),
			new StructuredMacroJira(),
			new StructuredMacroViewFile(
				$this->dataLookup, $this->currentSpace,
				$currentPageTitle, $this->nsFileRepoCompat
			),
			new Widget(),
			new PreservePStyleTag()
		];

		/** @var IProcessor $processor */
		foreach ( $processors as $processor ) {
			$processor->process( $dom );
		}
	}

	/**
	 *
	 * @return void
	 */
	private function runPostProcessors() {
		$postProcessors = [
			new RestorePStyleTag(),
			new RestoreTimeTag(),
			new FixLineBreakInHeadings(),
			new FixImagesWithExternalUrl(),
			new RestoreCode(),
			new NestedHeadings(),
			new RestoreStructuredMacroTasksReport(),
			new FixMultilineTemplate()
		];

		/** @var IPostprocessor $postProcessor */
		foreach ( $postProcessors as $postProcessor ) {
			$this->wikiText = $postProcessor->postprocess( $this->wikiText );
		}
	}

	/**
	 *
	 * @return int
	 */
	private function getBodyContentIdFromFilename() {
		// e.g. "67856345.mraw"
		$filename = $this->rawFile->getFilename();
		$filenameParts = explode( '.', $filename, 2 );
		return (int)$filenameParts[0];
	}

	/**
	 *
	 * @param int $bodyContentId
	 * @return int
	 */
	private function getPageIdFromBodyContentId( $bodyContentId ) {
		$map = $this->dataBuckets->getBucketData( 'body-contents-to-pages-map' );
		return $map[$bodyContentId] ?? -1;
	}

	/**
	 *
	 * @param int $bodyContentId
	 * @return int
	 */
	private function getSpaceDescriptionIDFromBodyContentId( $bodyContentId ) {
		$map = $this->dataBuckets->getBucketData( 'space-description-id-to-body-id-map' );
		$map = array_flip( $map );
		return $map[$bodyContentId] ?? -1;
	}

	/**
	 *
	 * @param int $pageId
	 * @return int
	 */
	private function getSpaceIdFromPageId( $pageId ) {
		$map = $this->dataBuckets->getBucketData( 'page-id-to-space-id' );
		return $map[$pageId] ?? -1;
	}

	/**
	 *
	 * @return DOMDocument
	 */
	private function preprocessFile() {
		$source = $this->preprocessHTMLSource( $this->rawFile );
		$dom = new DOMDocument();
		$dom->recover = true;
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = true;
		$dom->validateOnParse = false;
		$dom->loadXML( $source, LIBXML_PARSEHUGE );

		$preprocessedPathname = str_replace( '.mraw', '.mprep', $this->rawFile->getPathname() );
		$dom->saveHTMLFile( $preprocessedPathname );
		$this->preprocessedFile = new SplFileInfo( $preprocessedPathname );

		return $dom;
	}

	/**
	 * @param ConfluenceConverter $sender
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	private function processMacro( $sender, $match, $dom, $xpath ) {
		$replacement = '';
		$sMacroName = $match->getAttribute( 'ac:name' );

		// Exclude macros that are handled by an `IProcessor`
		if ( in_array(
			$sMacroName,
			[
				'align',
				'attachments',
				'children',
				'code',
				'column',
				'contentbylabel',
				'details',
				'detailssummary',
				'drawio',
				'excerpt-include',
				'expand',
				'include',
				'info',
				'inline-comment-marker',
				'noformat',
				'note',
				'pagetree',
				'placeholder',
				'panel',
				'recently-updated',
				'section',
				'space-details',
				'status',
				'task',
				'task-list',
				'tasks-report-macro',
				'tip',
				'toc',
				'view-file',
				'warning',
				'jira',
				'widget',
				'gliffy'
			]
		) ) {
			return;
		}

		if ( $sMacroName === 'localtabgroup' || $sMacroName === 'localtab' ) {
			$this->processLocalTabMacro( $sender, $match, $dom, $xpath, $replacement, $sMacroName );
		} elseif ( $sMacroName === 'excerpt' ) {
			$this->processExcerptMacro( $sender, $match, $dom, $xpath, $replacement );
		} elseif ( $sMacroName === 'viewdoc' || $sMacroName === 'viewxls' || $sMacroName === 'viewpdf' ) {
			$this->processViewXMacro( $sender, $match, $dom, $xpath, $replacement, $sMacroName );
		} else {
			// TODO: 'calendar', 'contributors', 'anchor',
			// 'navitabs', 'include', 'listlabels', 'content-report-table'
			$this->logMarkup( $match );
			$replacement .= "[[Category:Broken_macro/$sMacroName]]";
		}

		$parentNode = $match->parentNode;
		if ( $parentNode === null ) {
			return;
		}
		$parentNode->replaceChild(
			$dom->createTextNode( $replacement ),
			$match
		);
	}

	/**
	 *
	 * @param DOMNode $oNode
	 */
	protected function logMarkup( $oNode ) {
		if ( $oNode instanceof DOMElement === false ) {
			return;
		}

		error_log(
			"NODE: \n" .
			$oNode->ownerDocument->saveXML( $oNode )
		);
	}

	/**
	 *
	 * @return array
	 */
	private function makeReplacings() {
		return [
			'//ac:macro' => [ $this, 'processMacro' ],
			'//ac:structured-macro' => [ $this, 'processMacro' ]
		];
	}

	/**
	 *
	 * @param SplFileInfo $oHTMLSourceFile
	 * @return string
	 */
	protected function preprocessHTMLSource( $oHTMLSourceFile ) {
		$sContent = file_get_contents( $oHTMLSourceFile->getPathname() );
		$bodyContentId = $this->getBodyContentIdFromFilename( $oHTMLSourceFile->getFilename() );
		$pageId = $this->getPageIdFromBodyContentId( $bodyContentId );

		$preprocessors = [
			new CDATAClosingFixer()
		];
		/** @var IPreprocessor $preprocessor */
		foreach ( $preprocessors as $preprocessor ) {
			$sContent = $preprocessor->preprocess( $sContent );
		}

		/**
		 * As this is a mixture of XML and HTML the XMLParser has trouble
		 * with entities from HTML. To circumvent this we replace all entites
		 * by their literal. A better solution would be to make the entities
		 * known to the XMLParser, e.g. by using a DTD.
		 * This is something for a future development...
		 */
		$aReplaces = array_flip( get_html_translation_table( HTML_ENTITIES ) );
		unset( $aReplaces['&amp;'] );
		unset( $aReplaces['&lt;'] );
		unset( $aReplaces['&gt;'] );
		unset( $aReplaces['&quot;'] );
		foreach ( $aReplaces as $sEntity => $replacement ) {
			$sContent = str_replace( $sEntity, $replacement, $sContent );
		}

		// For now we just replace the layout markup of Confluence with simple
		// HTML div markup
		$sContent = str_replace( '<ac:layout-section', '{{Layout}}<ac:layout-section', $sContent );
		// "ac:layout-section" is the only one with a "ac:type" attribute
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$sContent = str_replace( '<ac:layout-section ac:type="', '<div class="ac-layout-section ', $sContent );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$sContent = str_replace( '<ac:layout-section', '<div class="ac-layout-section"', $sContent );
		$sContent = str_replace( '</ac:layout-section', '</div', $sContent );

		$sContent = str_replace( '<ac:layout-cell', '<div class="ac-layout-cell"', $sContent );
		$sContent = str_replace( '</ac:layout-cell', '</div', $sContent );

		$sContent = str_replace( '<ac:layout', '<div class="ac-layout"', $sContent );
		$sContent = str_replace( '</ac:layout', '</div', $sContent );

		// Append categories
		$categorieMap = $this->dataBuckets->getBucketData( 'title-metadata' );
		$categories = '';
		if ( isset( $categorieMap[$pageId] ) && isset( $categorieMap[$pageId]['categories'] ) ) {
			foreach ( $categorieMap[$pageId]['categories'] as $key => $category ) {
				$category = ucfirst( $category );
				$categories .= "[[Category:$category]]\n";
			}
		}
		$sContent = str_replace( '</body>', $categories . '</body>', $sContent );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$sContent = '<xml xmlns:ac="some" xmlns:ri="thing" xmlns:bs="bluespice">' . $sContent . '</xml>';

		return $sContent;
	}

	/**
	 *
	 * <ac::macro ac:name="localtabgroup">
	 * <ac::rich-text-body>
	 * <ac::macro ac:name="localtab">
	 * <ac::parameter ac:name="title">...</acparameter>
	 * <ac::rich-text-body>...</acrich-text-body>
	 * </ac:macro>
	 * </ac:rich-text-body>
	 * </ac:macro>
	 * @param ConfluenceConverter $sender
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string &$replacement
	 * @param string $sMacroName
	 */
	private function processLocalTabMacro( $sender, $match, $dom, $xpath, &$replacement, $sMacroName ) {
		if ( $sMacroName === 'localtabgroup' ) {
			// Append the "<headertabs />" tag
			$match->parentNode->appendChild(
				$dom->createTextNode( '<headertabs />' )
			);
		} elseif ( $sMacroName === 'localtab' ) {
			$oTitleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item( 0 );
			// Prepend the heading
			$match->parentNode->insertBefore(
				$dom->createElement( 'h1', $oTitleParam->nodeValue ),
				$match
			);
		}

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item( 0 );
		// Move all content out of <ac:rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item( 0 );
			$match->parentNode->insertBefore( $oChild, $match );
		}
	}

	/**
	 * @param ConfluenceConverter $sender
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string &$replacement
	 */
	private function processExcerptMacro( $sender, $match, $dom, $xpath, &$replacement ) {
		$oNewContainer = $dom->createElement( 'div' );
		$oNewContainer->setAttribute( 'class', 'ac-excerpt' );

		// TODO: reflect modes "INLINE" and "BLOCK"
		//See https://confluence.atlassian.com/doc/excerpt-macro-148062.html

		$match->parentNode->insertBefore( $oNewContainer, $match );

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item( 0 );
		// Move all content out of <ac::rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item( 0 );
			$oNewContainer->appendChild( $oChild );
		}
	}

	/**
	 * @param ConfluenceConverter $sender
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string &$replacement
	 * @param string $sMacroName
	 */
	private function processViewXMacro( $sender, $match, $dom, $xpath, &$replacement, $sMacroName ) {
		$oNameParam = $xpath->query( './ac:parameter[@ac:name="name"]', $match )->item( 0 );
		$oRIAttachmentEl = $xpath->query( './ac:parameter/ri:attachment', $match )->item( 0 );
		if ( $oNameParam instanceof DOMElement ) {
			$sTargetFile = $oNameParam->nodeValue;
			// Sometimes the target is not the direct nodeValue but an
			//atttribute value of a child <ri::attachment> element
			if ( empty( $sTargetFile ) && $oRIAttachmentEl instanceof DOMElement ) {
				$sTargetFile = $oRIAttachmentEl->getAttribute( 'ri:filename' );
			}

			$currentPageTitle = $this->getCurrentPageTitle();

			$linkProcessor = new AttachmentLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle, $this->nsFileRepoCompat
			);

			$oContainer = $dom->createElement(
				'span',
				$linkProcessor->makeLink( [ $sTargetFile ] )
			);
			$oContainer->setAttribute( 'class', "ac-$sMacroName" );
			$match->parentNode->insertBefore( $oContainer, $match );
		}
	}

	/**
	 *
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	public function postProcessDOM( $dom, $xpath ) {
		/*
		 * BlueSpice VisualEditor breaks on <div>'s with data attributes
		 * containing JSON
		 */
		$oElementsWithDataAttr = $xpath->query( '//*[@data-atlassian-layout]' );
		foreach ( $oElementsWithDataAttr as $oElementWithDataAttr ) {
			$oElementWithDataAttr->setAttribute( 'data-atlassian-layout', null );
		}
	}

	/**
	 *
	 * @return void
	 */
	public function postProcessLinks() {
		$oldToNewTitlesMap = $this->dataBuckets->getBucketData( 'pages-titles-map' );

		$this->wikiText = preg_replace_callback(
			"/\[\[Media:(.*)]]/",
			static function ( $matches ) use( $oldToNewTitlesMap ) {
				if ( isset( $oldToNewTitlesMap[$matches[1]] ) ) {
					return $oldToNewTitlesMap[$matches[1]];
				}
				return $matches[0];
			},
			$this->wikiText
		);
	}

	/**
	 *
	 * @return void
	 */
	private function postprocessWikiText() {
		// On Windows the CR would be encoded as "&#xD;" in the MediaWiki-XML, which is ulgy and unnecessary
		$this->wikiText = str_replace( "\r", '', $this->wikiText );
		$this->wikiText = str_replace( "###BREAK###", "\n", $this->wikiText );
		$this->wikiText = str_replace( "\n {{", "\n{{", $this->wikiText );
		$this->wikiText = str_replace( "\n }}", "\n}}", $this->wikiText );
		$this->wikiText = str_replace( "\n- ", "\n* ", $this->wikiText );
		$this->wikiText = preg_replace_callback(
			[
				// This is just for "TaskList", as it will add XML as TextNode.
				// It should be removed as soon as TaskList is properly converted.
				"#&lt;span.*?&gt;#si",
				"#&lt;/span&gt;#si",
				"#&lt;div.*?&gt;#si",
				"#&lt;/div&gt;#si",
				// End TaskList specific

				"#&lt;headertabs /&gt;#si",
				"#&lt;subpages(.*?)/&gt;#si",
				"#&lt;img(.*?)/&gt;#s"
			],
			static function ( $aMatches ) {
				return html_entity_decode( $aMatches[0] );
			},
			$this->wikiText
		);

		$this->wikiText .= $this->addAdditionalAttachments();

		$this->wikiText .= "\n <!-- From bodyContent {$this->rawFile->getBasename()} -->";
	}

	/**
	 * @return string
	 */
	private function addAdditionalAttachments(): string {
		$wikiText = '';

		$attachmentsMap = $this->dataBuckets->getBucketData( 'title-attachments' );

		$currentPageTitle = $this->getCurrentPageTitle();

		$linkProcessor = new AttachmentLink(
			$this->dataLookup, $this->currentSpace, $currentPageTitle, $this->nsFileRepoCompat
		);

		if ( isset( $attachmentsMap[$this->currentPageTitle] ) ) {
			$mediaExludeList = $this->buildMediaExcludeList( $this->wikiText );

			$attachmentList = [];
			foreach ( $attachmentsMap[$this->currentPageTitle] as $attachmentFileName ) {
				$mediaLink = $linkProcessor->makeLink( [ $attachmentFileName ] );
				$matches = [];
				preg_match( "#\[\[\s*(Media):(.*?)\s*[\|*|\]\]]#im", $mediaLink, $matches );

				if ( in_array( $matches[2], $mediaExludeList ) ) {
					continue;
				}

				$attachmentList[] = $mediaLink;
			}

			if ( !empty( $attachmentList ) ) {
				$wikiText .= "\n{{AttachmentsSectionStart}}\n";
				foreach ( $attachmentList as $attachment ) {
					$wikiText .= "* $attachment\n";
				}
				$wikiText .= "\n{{AttachmentsSectionEnd}}\n";
			}
		}

		return $wikiText;
	}

	/**
	 *
	 * @param string $wikiText
	 * @return array
	 */
	private function buildMediaExcludeList( $wikiText ): array {
		$excludes = [ 'File', 'Media' ];

		$matches = [];
		$excludes = implode( '|', $excludes );
		preg_match_all( "#\[\[\s*(File|Media):(.*?)\s*[\|*|\]\]]#im", $wikiText, $matches );
		$exludeList = [];
		foreach ( $matches[2] as $match ) {
			$exludeList[] = $match;
		}

		return $exludeList;
	}

	/**
	 * @return string
	 */
	private function getCurrentPageTitle(): string {
		$spaceIdPrefixMap = $this->dataBuckets->getBucketData( 'space-id-to-prefix-map' );
		$prefix = $spaceIdPrefixMap[$this->currentSpace];
		$currentPageTitle = $this->currentPageTitle;

		if ( substr( $currentPageTitle, 0, strlen( $prefix ) ) === $prefix ) {
			$currentPageTitle = str_replace( $prefix, '', $currentPageTitle );
		}

		return $currentPageTitle;
	}
}
