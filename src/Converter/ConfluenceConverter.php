<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\CodeMacro as RestoreCodeMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\EscapePipesInTemplateBody;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixImagesWithExternalUrl;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTemplate;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\NestedHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestorePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTimeTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\TasksReportMacro as RestoreTasksReportMacro;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\HoistMacroFromHeading;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\SanitizeLinkContent;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\html\CDATAClosingFixer;
use HalloWelt\MigrateConfluence\Converter\Processor\AlignMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\AnchorLink;
use HalloWelt\MigrateConfluence\Converter\Processor\AnchorMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentLink;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\CodeMacro as PreserveCodeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ColumnMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ContentByLabelMacro;
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
use HalloWelt\MigrateConfluence\Converter\Processor\Layout;
use HalloWelt\MigrateConfluence\Converter\Processor\LayoutCell;
use HalloWelt\MigrateConfluence\Converter\Processor\LayoutSection;
use HalloWelt\MigrateConfluence\Converter\Processor\LocalTabGroupMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\LocalTabMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\LoremIpsumMacro;
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
use HalloWelt\MigrateConfluence\Converter\Processor\TocMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLink;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewDocMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewPdfMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewPptMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewXlsMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\WarningMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\WidgetMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

class ConfluenceConverter extends PandocHTML implements IOutputAwareInterface, IDestinationPathAware {

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var string */
	private string $dest;

	/** @var DataBuckets */
	private DataBuckets $buckets;

	/** @var DBConversionDataLookup */
	private DBConversionDataLookup $dataLookup;

	/** @var ConversionDataWriter|null */
	private ?ConversionDataWriter $conversionDataWriter = null;

	/** @var SplFileInfo|null */
	private ?SplFileInfo $rawFile = null;

	/** @var int */
	private int $pageId = -1;

	/** @var string */
	private string $wikiText = '';

	/** @var string */
	private string $currentPageTitle = '';

	/** @var string */
	private string $confluencePageTitle = '';

	/** @var int */
	private int $currentSpace = 0;

	/** @var SplFileInfo|null */
	private ?SplFileInfo $preprocessedFile = null;

	/** @var Output|null */
	private ?Output $output = null;

	/** @var string */
	private string $contentType = '';

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 */
	public function __construct( $config, Workspace $workspace ) {
		parent::__construct( $config, $workspace );

	}

	/**
	 * @param string $dest
	 * @return void
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return string
	 */
	public function convert( SplFileInfo $file ): string {
		$this->workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );

		if ( isset( $this->config['config'] ) ) {
			$this->migrationConfig = new MigrationConfig( $this->config['config'] );
		} else {
			$this->migrationConfig = new MigrationConfig( [] );
		}

		$this->dataLookup = new DBConversionDataLookup( $this->workspaceDB );
		$this->conversionDataWriter = ConversionDataWriter::newFromBuckets( $this->buckets );

		$result = parent::convert( $file );
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	protected function doConvert( SplFileInfo $file ): string {
		$this->output->writeln( "Converting file " . $file->getPathname() );

		$this->rawFile = $file;

		$bodyContentId = $this->getBodyContentIdFromFilename();
		$contentId = $this->getContentIdFromBodyContentId( $bodyContentId );

		$this->pageId = -1;

		// Test to which type of content the contentId belongs
		if ( $this->workspaceDB->spaceDescriptionIdExists( $contentId ) ) {
			$this->contentType = 'spaceDescription';

			$this->currentSpace = $this->getSpaceIdFromSpaceDescriptionId( $contentId );

			$this->pageId = $this->getSpaceHomepageId( $this->currentSpace );

			$this->confluencePageTitle = $this->workspaceDB->getConfluencePageTitleFromPageId( $this->pageId );
			if ( $this->currentPageTitle === '' ) {
				$this->currentPageTitle = 'not_current_revision_' . $this->pageId;
			}
		} else if ( $this->workspaceDB->pageIdExists( $contentId ) ) {
			$this->contentType = 'page';

			$this->currentSpace = $this->getSpaceIdFromPageId( $contentId );

			$this->pageId = $contentId;

			$this->confluencePageTitle = $this->workspaceDB->getConfluencePageTitleFromPageId( $this->pageId );
			if ( $this->currentPageTitle === '' ) {
				$this->currentPageTitle = 'not_current_revision_' . $this->pageId;
			}
		} else if ( $this->workspaceDB->blogPostIdExists( $contentId ) ) {
			$this->contentType = 'blogPost';

			$this->currentSpace = $this->getSpaceIdFromBlogPostId( $contentId );

			$this->pageId = $contentId;

			$this->confluencePageTitle = $this->workspaceDB->getConfluenceBlogPostTitleFromBlogPostId( $this->pageId );
			if ( $this->currentPageTitle === '' ) {
				$this->currentPageTitle = 'not_current_revision_' . $this->pageId;
			}
		} else if ( $this->workspaceDB->commentIdExists( $contentId ) ) {
			$this->contentType = 'comment';

			$this->pageId = $contentId;

			// Comment body content: convert with minimal context (no page-specific macros expected)
			$this->currentSpace = 0;
			$this->currentPageTitle = '';
			$this->confluencePageTitle = '';
		} else {
			$this->pageId = -1;
		}

		if ( $this->pageId === -1 ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'convert',
				__CLASS__,
				"No context page id found for bodyContentId $bodyContentId"
			);
			return '<-- No context page id found -->';
		}

		if ( $this->currentSpace === -1 ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'convert',
				__CLASS__,
				"No context space id found for bodyContentId $bodyContentId"
			);

			return '<-- No context space id found -->';
		}

		try {
			$dom = $this->preprocessFile();
		} catch ( Exception $e ) {
			$rawContent = file_get_contents( $this->rawFile->getPathname() );
			$unconvertedContent = "<-- Unconvertable RAW start-->\n";
			$unconvertedContent .= $rawContent;
			$unconvertedContent .= "\n<-- Unconvertable RAW end-->\n[[Category:Unconvertable]]";
			return $unconvertedContent;
		}

		$this->runProcessors( $dom );

		$unhandledMacroProcessor = new UnhandledMacroConverter();
		$unhandledMacroProcessor->process( $dom );

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'ac', 'some' );
		$xpath->registerNamespace( 'ri', 'thing' );

		$this->postProcessDOM( $xpath );

		$dom->saveHTMLFile(
			$this->preprocessedFile->getPathname()
		);

		$this->wikiText = parent::doConvert( $this->preprocessedFile );
		$this->runPostProcessors();

		$this->postProcessLinks();
		$this->postprocessWikiText();

		$this->checkContentLength( $bodyContentId );

		return $this->wikiText;
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return void
	 */
	private function runProcessors( DOMDocument $dom ): void {
		$processors = [
			new Layout(),
			new LayoutSection(),
			new LayoutCell(),
			new AnchorMacro(),
			new Placeholder(),
			new InlineCommentMarker(),
			new PreserveTimeTag(),
			new TipMacro(),
			new InfoMacro(),
			new NoteMacro(),
			new WarningMacro(),
			new StatusMacro(),
			new TocMacro(),
			new PanelMacro(),
			new ColumnMacro(),
			new SectionMacro(),
			new ChildrenMacro(
				$this->currentSpace,
				$this->currentPageTitle,
				$this->dataLookup
			),
			new PageTreeMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->currentPageTitle,
				$this->migrationConfig->getMainPageName()
			),
			new RecentlyUpdatedMacro( $this->currentPageTitle ),
			new IncludeMacro(
				$this->dataLookup,
				$this->currentSpace
			),
			new ExcerptMacro(),
			new ExcerptIncludeMacro(
				$this->dataLookup,
				$this->currentSpace
			),
			new Emoticon(),
			new PreserveTasksReportMacro( $this->dataLookup ),
			new Image(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->migrationConfig
			),
			new AttachmentLink(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->migrationConfig
			),
			new AnchorLink(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->migrationConfig
			),
			new PageLink(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->migrationConfig
			),
			new UserLink(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->migrationConfig
			),
			new PreserveCodeMacro(),
			new NoFormatMacro(),
			new TaskListMacro(),
			new DrawioMacro(
				$this->dataLookup,
				$this->conversionDataWriter,
				$this->currentSpace,
				$this->confluencePageTitle
			),
			new GliffyMacro(
				$this->dataLookup,
				$this->conversionDataWriter,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->workspaceDB
			),
			new ContentByLabelMacro( $this->currentPageTitle ),
			new AttachmentsMacro(),
			new GalleryMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->migrationConfig
			),
			new ExpandMacro(),
			new DetailsMacro(),
			new DetailsSummaryMacro(),
			new AlignMacro(),
			new JiraMacro(),
			new MarkdownMacro(),
			new ViewFileMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->advancedConfig
			),
			new ViewDocMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->advancedConfig
			),
			new ViewXlsMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->advancedConfig
			),
			new ViewPptMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->advancedConfig
			),
			new ViewPdfMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->confluencePageTitle,
				$this->advancedConfig
			),
			new WidgetMacro(),
			new PreservePStyleTag(),
			new TableFilterMacro(),
			new LocalTabMacro(),
			new LocalTabGroupMacro(),
			new LoremIpsumMacro(),
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
	private function runPostProcessors(): void {
		$postProcessors = [
			new RestorePStyleTag(),
			new RestoreExcerptMacro(),
			new RestoreTimeTag(),
			new FixLineBreakInHeadings(),
			new FixImagesWithExternalUrl(),
			new RestoreCodeMacro(),
			new NestedHeadings(),
			new RestoreTasksReportMacro(),
			new FixMultilineTemplate(),
			new EscapePipesInTemplateBody(),
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
	private function getBodyContentIdFromFilename(): int {
		// e.g. "67856345.mraw"
		$filename = $this->rawFile->getFilename();
		$filenameParts = explode( '.', $filename, 2 );
		return (int)$filenameParts[0];
	}

	/**
	 *
	 * @param int $bodyContentId
	 *
	 * @return int
	 */
	private function getContentIdFromBodyContentId( int $bodyContentId ): int {
		$map = $this->workspaceDB->getContentIdForBodyContentId( $bodyContentId );
		return $map;
	}

	/**
	 * @param int $spaceDescId
	 * @return int
	 */
	private function getSpaceIdFromSpaceDescriptionId( int $spaceDescId ): int {
		return $this->workspaceDB->getSpaceIdForDescriptionId( $spaceDescId );
	}

	/**
	 * @param int $pageId
	 * @return int
	 */
	private function getSpaceIdFromPageId( int $pageId ): int {
		return $this->workspaceDB->getSpaceIdForPageId( $pageId );
	}

	/**
	 * @param int $blogPostId
	 * @return int
	 */
	private function getSpaceIdFromBlogPostId( int $blogPostId ): int {
		return $this->workspaceDB->getSpaceIdForBlogPostId( $blogPostId );
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
	 * @return DOMDocument
	 * @throws Exception
	 */
	private function preprocessFile(): DOMDocument {
		$source = $this->preprocessHTMLSource( $this->rawFile );
		$dom = new DOMDocument();
		$dom->recover = true;
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = true;
		$dom->validateOnParse = false;
		$validXML = $dom->loadXML( $source, LIBXML_PARSEHUGE );
		if ( $validXML === false ) {
			throw new Exception( 'Unconvertable' );
		}

		$this->preprocessDomSource( $dom );

		$preprocessedPathname = str_replace( '.mraw', '.mprep', $this->rawFile->getPathname() );
		$dom->saveHTMLFile( $preprocessedPathname );
		$this->preprocessedFile = new SplFileInfo( $preprocessedPathname );

		return $dom;
	}

	/**
	 * @param SplFileInfo $oHTMLSourceFile
	 * @return string
	 */
	protected function preprocessHTMLSource( SplFileInfo $oHTMLSourceFile ): string {
		$sContent = file_get_contents( $oHTMLSourceFile->getPathname() );

		$preprocessors = [
			new CDATAClosingFixer()
		];

		/** @var IHtmlPreprocessor $preprocessor */
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

		// Append categories
		if ( $this->contentType === 'page' ) {
			$metaData = $this->workspaceDB->getPageMeta();
		} else if ( $this->contentType === 'blogPost' ) {
			$metaData = $this->workspaceDB->getPageMeta();
		} else {
			$metaData = [];
		}

		$categories = '';
		if ( isset( $metaData['categories'] ) ) {
			foreach ( $metaData['categories'] as $category ) {
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
	 * @param DOMDocument $dom
	 * @return void
	 */
	protected function preprocessDomSource( DOMDocument $dom ): void {
		$preprocessors = [
			new SanitizeLinkContent(),
			new HoistMacroFromHeading()
		];

		/** @var IDomPreprocessor $preprocessor */
		foreach ( $preprocessors as $preprocessor ) {
			$preprocessor->preprocess( $dom );
		}
	}

	/**
	 * BlueSpice VisualEditor breaks on <div>'s with data attributes
	 * containing JSON
	 *
	 * @param DOMXPath $xpath
	 *
	 * @return void
	 */
	public function postProcessDOM( DOMXPath $xpath ): void {
		$oElementsWithDataAttr = $xpath->query( '//*[@data-atlassian-layout]' );
		if ( !( $oElementsWithDataAttr instanceof DOMNodeList ) ) {
			return;
		}

		foreach ( $oElementsWithDataAttr as $oElementWithDataAttr ) {
			if ( $oElementWithDataAttr instanceof DOMElement ) {
				$oElementWithDataAttr->setAttribute( 'data-atlassian-layout', '' );
			}
		}
	}

	/**
	 *
	 * @return void
	 */
	public function postProcessLinks(): void {
		$oldToNewTitlesMap = array_merge(
			$this->buckets->getBucketData( 'global-pages-titles-map' ),
			$this->buckets->getBucketData( 'global-blogposts-titles-map' )
		);

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
	private function postprocessWikiText(): void {
		// On Windows the CR would be encoded as "&#xD;" in the MediaWiki-XML, which is ulgy and unnecessary
		$this->wikiText = str_replace( "\r", '', $this->wikiText );
		$this->wikiText = str_replace( "###BREAK###", "\n", $this->wikiText );
		$this->wikiText = str_replace( '###HTMLCOMMENTOPEN###', '<!-- ', $this->wikiText );
		$this->wikiText = str_replace( '###HTMLCOMMENTCLOSE###', ' -->', $this->wikiText );
		$this->wikiText = str_replace( "\n {{", "\n{{", $this->wikiText );
		$this->wikiText = str_replace( "\n }}", "\n}}", $this->wikiText );
		$this->wikiText = str_replace( "\n- ", "\n* ", $this->wikiText );
		$this->wikiText = preg_replace_callback(
			[
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

		$linkProcessor = new AttachmentLink(
			$this->dataLookup, $this->currentSpace, $this->confluencePageTitle, $this->advancedConfig
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
	 * @param string $wikiText
	 *
	 * @return array
	 */
	private function buildMediaExcludeList( string $wikiText ): array {
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
	 * Content size sometimes breakes import
	 *
	 * @param int $bodyContentId
	 * @return void
	 */
	private function checkContentLength( int $bodyContentId ): void {
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
			$this->workspaceDB->addLogEntry(
				'warning',
				'convert',
				__CLASS__,
				"bodyContentId $bodyContentId contains large content (>$exceed KB)"
			);
			$this->output->writeln( "bodyContentId $bodyContentId contains large content" );
		}
	}
}
