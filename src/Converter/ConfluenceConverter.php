<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\CodeMacro as RestoreCodeMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixImagesWithExternalUrl;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTemplate;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\NestedHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestorePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTimeTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\TasksReportMacro as RestoreTasksReportMacro;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\CDATAClosingFixer;
use HalloWelt\MigrateConfluence\Converter\Processor\AlignMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\AnchorMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentLink;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\CodeMacro as PreserveCodeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ColumnMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ContenByLabelMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsSummaryMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\Emoticon;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ExpandMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\Image;
use HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\InfoMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\InlineCommentMarker;
use HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\LocalTabGroupMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\LocalTabMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\MarkdownMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\NoFormatMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\NoteMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\PanelMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\Placeholder;
use HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveTimeTag;
use HalloWelt\MigrateConfluence\Converter\Processor\RecentlyUpdatedMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\SectionMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TaskListMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TasksReportMacro as PreserveTasksReportMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TipMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\Toc;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLink;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewDocMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewXlsMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\WarningMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\WidgetMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

class ConfluenceConverter extends PandocHTML implements IOutputAwareInterface {

	/** @var bool */
	protected $bodyContentFile = null;

	/** @var DataBuckets */
	private $executionTimeBuckets = null;

	/** @var DataBuckets */
	private $buckets = null;

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

	/** @var string */
	private $mainpage = 'Main Page';

	/** @var bool */
	private $isSpaceDescriptionContent = false;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 */
	public function __construct( $config, Workspace $workspace ) {
		parent::__construct( $config, $workspace );

		$this->buckets = new DataBuckets( [
			'global-page-id-to-title-map',
			'global-pages-titles-map',
			'global-title-attachments',
			'global-body-content-id-to-page-id-map',
			'global-space-id-to-description-id-map',
			'global-page-id-to-space-id',
			'global-space-id-to-key-map',
			'global-space-id-to-prefix-map',
			'global-space-id-homepages',
			'global-filenames-to-filetitles-map',
			'global-title-metadata',
			'global-attachment-orig-filename-target-filename-map',
			'global-files',
			'global-userkey-to-username-map',
			'global-body-content-id-to-space-description-id-map',
			'global-gliffy-map',
			'global-attachment-metadata',
			'global-attachment-id-to-confluence-file-key-map',
		] );

		$this->buckets->loadFromWorkspace( $this->workspace );

		$this->customBuckets = new DataBuckets( [
			'warning-convert-body-content-id-content-size',
		] );
		$this->executionTimeBuckets = new DataBuckets( [
			'convert-body-content-id-execution-time',
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
		$executionTime = new ExecutionTime();

		$this->customBuckets->loadFromWorkspace( $this->workspace );
		$this->executionTimeBuckets->loadFromWorkspace( $this->workspace );

		$this->output->writeln( $file->getPathname() );
		$this->dataLookup = ConversionDataLookup::newFromBuckets( $this->buckets );
		$this->conversionDataWriter = ConversionDataWriter::newFromBuckets( $this->buckets );
		$this->rawFile = $file;

		if ( isset( $this->config['config']['mainpage'] ) ) {
			$this->mainpage = $this->config['config']['mainpage'];
		}

		$this->isSpaceDescriptionContent = false;
		$bodyContentId = $this->getBodyContentIdFromFilename();
		$pageId = $this->getPageIdFromBodyContentId( $bodyContentId );
		if ( $pageId === -1 ) {
			$spaceDescId = $this->getSpaceDescriptionIdFromBodyContentId( $bodyContentId );
			$spaceId = $this->getSpaceIdFromSpaceDescriptionId( $spaceDescId );
			$pageId = $this->getSpaceHomepageId( $spaceId );
			$this->isSpaceDescriptionContent = true;
		}
		if ( $pageId === -1 ) {
			return '<-- No context page id found -->';
		}
		$this->currentSpace = $this->getSpaceIdFromPageId( (int)$pageId );
		if ( $this->currentSpace === -1 ) {
			return '<-- No context space id found -->';
		}

		$pagesIdsToTitlesMap = $this->buckets->getBucketData( 'global-page-id-to-title-map' );
		if ( isset( $pagesIdsToTitlesMap[$pageId] ) ) {
			$this->currentPageTitle = $pagesIdsToTitlesMap[$pageId];
		} else {
			$this->currentPageTitle = 'not_current_revision_' . $pageId;
		}

		$dom = $this->preprocessFile();

		$xpath = new DOMXPath( $dom );

		$this->runProcessors( $dom );

		$unhandledMacroProcessor = new UnhandledMacroConverter();
		$unhandledMacroProcessor->process( $dom );

		$xpath->registerNamespace( 'ac', 'some' );
		$xpath->registerNamespace( 'ri', 'thing' );
		$this->postProcessDOM( $dom, $xpath );

		$dom->saveHTMLFile(
			$this->preprocessedFile->getPathname()
		);

		$this->wikiText = parent::doConvert( $this->preprocessedFile );
		$this->runPostProcessors();

		$this->postProcessLinks();
		$this->postprocessWikiText();

		// Content size sometimes breakes import
		$exceed = '';
		$wikiTextLength = strlen( $this->wikiText );
		$wikiTextLength = $wikiTextLength / 1000;
		if ( $wikiTextLength > 512 ) {
			$exceed = '512';
		} elseif ( $wikiTextLength > 256 ) {
			$exceed = '256';
		} elseif ( $wikiTextLength > 100 ) {
			$exceed = '100';
		}
		if ( $exceed !== '' ) {
			$this->buckets->addData(
				'warning-convert-body-content-id-content-size',
				$exceed,
				$bodyContentId
			);
			$this->output->writeln( "bodyContentId {$this->currentSpace} contains large content" );
		}

		$executionTimeString = $executionTime->getHumanReadableTime();
		$this->executionTimeBuckets->addData(
			'convert-body-content-id-execution-time',
			$bodyContentId,
			$executionTimeString,
			false,
			true
		);
		$this->executionTimeBuckets->saveToWorkspace( $this->workspace );
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
			new AnchorMacro(),
			new Placeholder(),
			new InlineCommentMarker(),
			new PreserveTimeTag(),
			new TipMacro(),
			new InfoMacro(),
			new NoteMacro(),
			new WarningMacro(),
			new StatusMacro(),
			new Toc(),
			new PanelMacro(),
			new ColumnMacro(),
			new SectionMacro(),
			new ChildrenMacro( $this->currentPageTitle ),
			new PageTreeMacro(
				$this->dataLookup, $this->currentSpace, $this->currentPageTitle, $this->mainpage
			),
			new RecentlyUpdatedMacro( $this->currentPageTitle ),
			new IncludeMacro( $this->dataLookup, $this->currentSpace ),
			new ExcerptMacro(),
			new ExcerptIncludeMacro( $this->dataLookup, $this->currentSpace ),
			new Emoticon(),
			new PreserveTasksReportMacro( $this->dataLookup ),
			new Image(
				$this->dataLookup, $this->currentSpace, $currentPageTitle
			),
			new AttachmentLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle
			),
			new PageLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle
			),
			new UserLink(
				$this->dataLookup, $this->currentSpace, $currentPageTitle
			),
			new PreserveCodeMacro(),
			new NoFormatMacro(),
			new TaskListMacro(),
			new DrawioMacro(
				$this->dataLookup, $this->conversionDataWriter, $this->currentSpace,
				$currentPageTitle
			),
			new GliffyMacro(
				$this->dataLookup, $this->conversionDataWriter, $this->currentSpace,
				$currentPageTitle, $this->buckets
			),
			new ContenByLabelMacro( $this->currentPageTitle ),
			new AttachmentsMacro(),
			new GalleryMacro(
				$this->dataLookup, $this->currentSpace, $currentPageTitle
			),
			new ExpandMacro(),
			new DetailsMacro(),
			new DetailsSummaryMacro(),
			new AlignMacro(),
			new JiraMacro(),
			new MarkdownMacro(),
			new ViewFileMacro(
				$this->dataLookup, $this->currentSpace,
				$currentPageTitle
			),
			new ViewDocMacro(
				$this->dataLookup, $this->currentSpace,
				$currentPageTitle
			),
			new ViewXlsMacro(
				$this->dataLookup, $this->currentSpace,
				$currentPageTitle
			),
			new ViewFileMacro(
				$this->dataLookup, $this->currentSpace,
				$currentPageTitle
			),
			new WidgetMacro(),
			new PreservePStyleTag(),
			new TableFilterMacro(),
			new LocalTabMacro(),
			new LocalTabGroupMacro()
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
			new RestoreCodeMacro(),
			new NestedHeadings(),
			new RestoreTasksReportMacro(),
			new FixMultilineTemplate(),
			new FixMultilineTable(),
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
		$map = $this->buckets->getBucketData( 'global-body-content-id-to-page-id-map' );
		return $map[$bodyContentId] ?? -1;
	}

	/**
	 *
	 * @param int $bodyContentId
	 * @return int
	 */
	private function getSpaceDescriptionIdFromBodyContentId( int $bodyContentId ): int {
		$map = $this->buckets->getBucketData( 'global-body-content-id-to-space-description-id-map' );
		return $map[$bodyContentId] ?? -1;
	}

	/**
	 * @param int $spaceDescId
	 * @return int
	 */
	private function getSpaceIdFromSpaceDescriptionId( int $spaceDescId ): int {
		$map = $this->buckets->getBucketData( 'global-space-id-to-description-id-map' );
		$mapFlipped = array_flip( $map );
		return $mapFlipped[$spaceDescId] ?? -1;
	}

	/**
	 * @param int $spaceId
	 * @return int
	 */
	private function getSpaceHomepageId( int $spaceId ): int {
		$map = $this->buckets->getBucketData( 'global-space-id-homepages' );
		return $map[$spaceId] ?? -1;
	}

	/**
	 *
	 * @param int $pageId
	 * @return int
	 */
	private function getSpaceIdFromPageId( $pageId ) {
		$map = $this->buckets->getBucketData( 'global-page-id-to-space-id' );
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
		$categorieMap = $this->buckets->getBucketData( 'global-title-metadata' );
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
		$oldToNewTitlesMap = $this->buckets->getBucketData( 'global-pages-titles-map' );

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

		if ( !$this->isSpaceDescriptionContent ) {
			$this->wikiText .= $this->addAdditionalAttachments();
		}

		$this->wikiText .= "\n <!-- From bodyContent {$this->rawFile->getBasename()} -->";
	}

	/**
	 * @return string
	 */
	private function addAdditionalAttachments(): string {
		$wikiText = '';

		$attachmentsMap = $this->buckets->getBucketData( 'global-title-attachments' );

		$currentPageTitle = $this->getCurrentPageTitle();

		$linkProcessor = new AttachmentLink(
			$this->dataLookup, $this->currentSpace, $currentPageTitle
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

				if ( str_ends_with( $matches[2], '.unknown' ) ) {
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
		$exclude = implode( '|', $excludes );

		$matches = [];
		preg_match_all( "#\[\[\s*($exclude):(.*?)\s*[\|*|\]\]]#im", $wikiText, $matches );
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
		$prefix = '';
		$spaceIdPrefixMap = $this->buckets->getBucketData( 'global-space-id-to-prefix-map' );
		if ( !isset( $spaceIdPrefixMap[$this->currentSpace] ) ) {
			$this->output->writeln( "SpaceId {$this->currentSpace} not found in spaceIdPrefixMap" );
		}
		$prefix = $spaceIdPrefixMap[$this->currentSpace];
		$currentPageTitle = $this->currentPageTitle;

		if ( substr( $currentPageTitle, 0, strlen( $prefix ) ) === $prefix ) {
			$currentPageTitle = str_replace( $prefix, '', $currentPageTitle );
		}

		return $currentPageTitle;
	}
}
