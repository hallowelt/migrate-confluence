<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase implements LoggerAwareInterface, IOutputAwareInterface {

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
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * @var Input
	 */
	private $input = null;

	/**
	 * @var Output
	 */
	private $output = null;

	/**
	 * @var array
	 */
	private $availableAttachmentIds = [];

	/**
	 * @var array
	 */
	private $addedAttachmentIds = [];

	/**
	 *
	 * @var string
	 */
	private $pageConfluenceTitle = '';

	/**
	 * @var string
	 */
	private $mainpage = 'Main Page';

	/**
	 * @var bool
	 */
	private $extNsFileRepoCompat = false;

	/**
	 * @var array
	 */
	private $advancedConfig = [];

	/**
	 *
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );
		$this->customBuckets = new DataBuckets( [
			'space-id-to-prefix-map',
			'space-key-to-prefix-map',
			'space-name-to-prefix-map',
			'space-id-to-name-map',
			'space-key-to-name-map',
			'space-id-homepages',
			'space-id-to-description-id-map',
			'space-description-id-to-body-id-map',
			'space-details',
			'page-id-to-confluence-title-map',
			'page-id-to-parent-page-id-map',
			'body-content-id-to-page-id-map',
			'attachment-id-to-orig-filename-map',
			'attachment-id-to-space-id-map',
			'attachment-id-to-reference-map',
			'attachment-id-to-container-content-id-map',
			'attachment-id-to-content-status-map',
			'userkey-to-username-map',
			'pages-titles-map',
			'page-id-to-confluence-key-map',
			'page-id-to-title-map',
			'page-id-to-space-id',
			'title-files',
			'additional-files',
			'attachment-orig-filename-target-filename-map',
			'attachment-id-to-target-filename-map',
			'attachment-confluence-file-key-to-target-filename-map',

			'debug-attachment-id-to-target-filename',
			'debug-missing-attachment-id-to-filename',
			'debug-attachment-page-to-attachment-id',
			'debug-fallback-attachment-id-to-target-filename',
			'debug-additional-attachment-id-to-target-filename',
		] );

		$this->logger = new NullLogger();

		$this->setConfigVars();
	}

	/**
	 * @return void
	 */
	private function setConfigVars(): void {
		if ( isset( $this->config['config'] ) ) {
			$this->advancedConfig = $this->config['config'];
		}

		if ( isset( $this->advancedConfig['ext-ns-file-repo-compat'] ) ) {
			if ( is_bool( $this->advancedConfig['ext-ns-file-repo-compat'] ) ) {
				$this->extNsFileRepoCompat = $this->advancedConfig['ext-ns-file-repo-compat'];
			}
		}

		if ( isset( $this->advancedConfig['mainpage'] ) ) {
			$this->mainpage = $this->advancedConfig['mainpage'];
		}
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param Input $input
	 */
	public function setInput( Input $input ) {
		$this->input = $input;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		if ( $file->getFilename() !== 'entities.xml' ) {
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
		$xmlReader = new XMLReader();

		// Process Space and BodyContents objects (needed by other objects)
		$this->output->writeln( "\nPrepare required maps:" );

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
			if ( $class === 'Space' ) {
				$this->buildSpaceMaps( $objectDom );
			} elseif ( $class === 'SpaceDescription' ) {
				$this->buildSpaceDescriptionMap( $objectDom );
			} elseif ( $class === "Page" ) {
				$this->buildParentPageMap( $objectDom );
			} elseif ( $class === "BodyContent" ) {
				$this->buildBodyContentMap( $objectDom );
			} elseif ( $class === "Attachment" ) {
				$this->buildAttachmentMaps( $objectDom );
			} elseif ( $class === "ConfluenceUserImpl" ) {
				$this->buildUserMap( $objectDom );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		// Process Page objects (needed by other objects)
		$this->output->writeln( "\nAnalyze pages:" );

		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$nodeXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $nodeXML );

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'Page' ) {
				$this->buildPageMaps( $objectDom );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		// Process title attachments fallback
		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$nodeXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $nodeXML );

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'Attachment' ) {
				$this->buildTitleAttachmentsFallbackMaps( $objectDom );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		return true;
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildSpaceMaps( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );
		$spaces = $xmlHelper->getObjectNodes( 'Space' );
		if ( count( $spaces ) < 1 ) {
			return;
		}
		$space = $spaces->item( 0 );
		if ( $space instanceof DOMElement === false ) {
			return;
		}

		$spaceId = $xmlHelper->getIDNodeValue( $space );
		if ( $spaceId === -1 ) {
			return;
		}
		$spaceKey = $xmlHelper->getPropertyValue( 'key', $space );
		$spaceName = $xmlHelper->getPropertyValue( 'name', $space );
		if ( substr( $spaceKey, 0, 1 ) === '~' ) {
			// User namespaces
			$spaceKey = $this->sanitizeUserSpaceKey( $spaceKey, $spaceName );
			$this->output->writeln( "\033[31mAdd space $spaceKey (ID:$spaceId) - protected user namespace\033[39m" );
		} else {
			$this->output->writeln( "Add space $spaceKey (ID:$spaceId)" );
		}

		// Confluence's GENERAL equals MediaWiki's NS_MAIN, thus having no prefix
		if ( $spaceKey === 'GENERAL' ) {
			$spaceKey = '';
		}

		if ( isset( $this->advancedConfig['space-prefix'][$spaceKey] ) ) {
			$customSpacePrefix = $this->advancedConfig['space-prefix'][$spaceKey];
		} elseif ( $spaceKey !== '' ) {
			$customSpacePrefix = "{$spaceKey}:";
		} else {
			return;
		}

		$this->customBuckets->addData(
			'space-id-to-prefix-map', $spaceId, $customSpacePrefix, false, true
		);
		$this->customBuckets->addData(
			'space-key-to-prefix-map', $spaceKey, $customSpacePrefix, false, true
		);
		$this->customBuckets->addData(
			'space-name-to-prefix-map', $spaceName, $customSpacePrefix, false, true
		);
		$this->customBuckets->addData(
			'space-id-to-name-map', $spaceId, $spaceName, false, true
		);
		$this->customBuckets->addData(
			'space-key-to-name-map', $spaceKey, $spaceName, false, true
		);

		$homePageId = -1;
		$homePagePropertyNode = $xmlHelper->getPropertyNode( 'homePage', $space );
		if ( $homePagePropertyNode !== null ) {
			$homePageId = $xmlHelper->getIDNodeValue( $homePagePropertyNode );
		}
		if ( $homePageId > -1 ) {
			$this->customBuckets->addData( 'space-id-homepages', $spaceId, $homePageId, false, true );
		}

		$details = [];
		// Property id
		$details['id'] = $spaceId;

		// Property key
		$details['key'] = $spaceKey;

		// Text only propterties
		$properties = [
			'name', 'creationDate', 'lastModificationDate', 'spaceType', 'spaceStatus'
		];

		foreach ( $properties as $property ) {
			$details[$property] = $xmlHelper->getPropertyValue( $property, $space );
		}

		// ID (int) node propterties
		$propertyNode = $xmlHelper->getPropertyNode( 'description' );
		if ( $propertyNode !== null ) {
			$details['description'] = $xmlHelper->getIDNodeValue( $propertyNode );
			$this->customBuckets->addData(
				'space-id-to-description-id-map',
				$spaceId,
				$details['description'],
				false,
				true
			);

			$this->output->writeln( "Add space description ($spaceId)" );
		}

		$propertyNode = $xmlHelper->getPropertyNode( 'homePage' );
		if ( $propertyNode !== null ) {
			$details['homePage'] = $xmlHelper->getIDNodeValue( $propertyNode );
		}

		// ID (key) node propterties
		$properties = [
			'creator', 'lastModifier'
		];

		foreach ( $properties as $property ) {
			$propertyNode = $xmlHelper->getPropertyNode( $property );
			if ( $propertyNode !== null ) {
				$details[$property] = $xmlHelper->getKeyNodeValue( $propertyNode );
			}
		}

		if ( !empty( $details ) ) {
			$this->customBuckets->addData( 'space-details', $spaceId, $details, false, true );
			$this->output->writeln( "Add details description ($spaceId)" );
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildSpaceDescriptionMap( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );
		$spaceDescriptions = $xmlHelper->getObjectNodes( 'SpaceDescription' );
		if ( count( $spaceDescriptions ) < 1 ) {
			return;
		}
		$spaceDescription = $spaceDescriptions->item( 0 );
		if ( $spaceDescription instanceof DOMElement === false ) {
			return;
		}

		$descID = $xmlHelper->getIDNodeValue( $spaceDescription );
		$bodyContents = $xmlHelper->getElementsFromCollection( 'bodyContents', $spaceDescription );
		foreach ( $bodyContents as $bodyContent ) {
			$id = $xmlHelper->getIDNodeValue( $bodyContent );
			$this->customBuckets->addData( 'space-description-id-to-body-id-map', $descID, $id, false, true );
			$this->output->writeln( "\nAdd space description ($id)" );
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildParentPageMap( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );

		$pages = $xmlHelper->getObjectNodes( 'Page' );
		if ( count( $pages ) < 1 ) {

			return;
		}
		$pageNode = $pages->item( 0 );
		if ( $pageNode instanceof DOMElement === false ) {
			return;
		}
		$status = $xmlHelper->getPropertyValue( 'contentStatus', $pageNode );
		if ( $status !== 'current' ) {
			return;
		}
		$spaceId = $xmlHelper->getPropertyValue( 'space', $pageNode );
		if ( $spaceId === null ) {
			return;
		}
		$originalVersionID = $xmlHelper->getPropertyValue( 'originalVersion', $pageNode );
		if ( $originalVersionID !== null ) {
			return;
		}

		$pageId = $xmlHelper->getIDNodeValue( $pageNode );
		$parentPageId = $xmlHelper->getPropertyValue( 'parent', $pageNode );
		if ( $parentPageId !== null ) {
			$this->customBuckets->addData( 'page-id-to-parent-page-id-map', $pageId, $parentPageId, false, true );
		}

		$pageId = $xmlHelper->getIDNodeValue( $pageNode );
		$confluenceTitle = $xmlHelper->getPropertyValue( 'title', $pageNode );
		if ( $confluenceTitle !== null ) {
			$this->customBuckets->addData( 'page-id-to-confluence-title-map', $pageId, $confluenceTitle, false, true );
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildBodyContentMap( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );
		$bodyContentObjects = $xmlHelper->getObjectNodes( 'BodyContent' );
		if ( count( $bodyContentObjects ) < 1 ) {
			return;
		}
		$bodyContentObject = $bodyContentObjects->item( 0 );
		if ( $bodyContentObject instanceof DOMElement === false ) {
			return;
		}

		$bodyContentId = $xmlHelper->getIDNodeValue( $bodyContentObject );
		$pageId = $xmlHelper->getPropertyValue( 'content', $bodyContentObject );
		$this->customBuckets->addData( 'body-content-id-to-page-id-map',
			$bodyContentId,	$pageId,	false, true );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildAttachmentMaps( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );

		$attachmentNodes = $xmlHelper->getObjectNodes( 'Attachment' );
		if ( count( $attachmentNodes ) < 1 ) {
			return;
		}
		$attachmentNode = $attachmentNodes->item( 0 );
		if ( $attachmentNode instanceof DOMElement === false ) {
			return;
		}

		$attachmentId = $xmlHelper->getIDNodeValue( $attachmentNode );
		if ( $attachmentId < 0 ) {
			return;
		}
		$this->availableAttachmentIds[] = $attachmentId;

		$attachmentFilename = $xmlHelper->getPropertyValue( 'fileName', $attachmentNode );
		if ( $attachmentFilename === null ) {
			$attachmentFilename = $xmlHelper->getPropertyValue( 'title', $attachmentNode );
		}

		if ( $attachmentFilename !== '' && is_int( $attachmentId ) ) {
			$this->customBuckets->addData(
				'attachment-id-to-orig-filename-map', $attachmentId, $attachmentFilename, false, true );
		}
		$attachmentSpaceId = $xmlHelper->getPropertyValue( 'space', $attachmentNode );
		if ( is_int( $attachmentId ) ) {
			$this->customBuckets->addData(
				'attachment-id-to-space-id-map', $attachmentId, $attachmentSpaceId, false, true );
		}
		$attachmentReference = $this->makeAttachmentReference( $xmlHelper, $attachmentNode );
		if ( $attachmentReference !== '' ) {
			$this->customBuckets->addData(
				'attachment-id-to-reference-map', $attachmentId, $attachmentReference, false, true );
		}
		$containerContent = $xmlHelper->getPropertyNode( 'containerContent', $attachmentNode );
		if ( $containerContent instanceof DOMElement ) {
			$containerContentId = $xmlHelper->getIDNodeValue( $containerContent );
			if ( $containerContentId >= 0 ) {
				$this->customBuckets->addData(
					'attachment-id-to-container-content-id-map', $attachmentId, $containerContentId, false, true );
			}
		}
		$attachmentNodeContentStatus = $xmlHelper->getPropertyValue( 'contentStatus', $attachmentNode );
		$this->customBuckets->addData(
			'attachment-id-to-content-status-map', $attachmentId, $attachmentNodeContentStatus, false, true );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildUserMap( DOMDocument $dom ): void {
		$xmlHelper = new XMLHelper( $dom );

		$userImpls = $xmlHelper->getObjectNodes( 'ConfluenceUserImpl' );
		if ( count( $userImpls ) < 1 ) {
			return;
		}
		$userImpl = $userImpls->item( 0 );
		if ( $userImpl instanceof DOMElement === false ) {
			return;
		}

		// Can not use `XMLHelper::getIDNodeValue` here, as the key is not an integer
		$idNode = $userImpl->getElementsByTagName( 'id' )->item( 0 );
		$userImplKey = $idNode->nodeValue;
		$lcUserName = $xmlHelper->getPropertyValue( 'lowerName', $userImpl );
		$email = $xmlHelper->getPropertyValue( 'email', $userImpl );
		if ( !$lcUserName ) {
			$this->output->writeln( "\033[31m User $userImplKey has no username\033[39m" );
			return;
		}

		$mediaWikiUsername = $this->makeMWUserName( $lcUserName );

		$this->customBuckets->addData(
			'userkey-to-username-map',
			$userImplKey,
			$mediaWikiUsername,
			false
		);

		$this->customBuckets->addData(
			'users',
			$mediaWikiUsername,
			[
				'email' => $email === null ? '' : $email
			],
			false,
			true
		);

		$this->output->writeln( "Add user '$mediaWikiUsername' (ID:$userImplKey)" );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildPageMaps( DOMDocument $dom ): void {
		$spaceIdToPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$spaceIdHomepages = $this->customBuckets->getBucketData( 'space-id-homepages' );
		$pageIdParentPageIdMap = $this->customBuckets->getBucketData( 'page-id-to-parent-page-id-map' );
		$pageIdConfluendTitleMap = $this->customBuckets->getBucketData( 'page-id-to-confluence-title-map' );
		$bodyContents = $this->customBuckets->getBucketData( 'body-content-id-to-page-id-map' );

		$xmlHelper = new XMLHelper( $dom );

		$pages = $xmlHelper->getObjectNodes( 'Page' );
		if ( count( $pages ) < 1 ) {

			return;
		}
		$pageNode = $pages->item( 0 );
		if ( $pageNode instanceof DOMElement === false ) {
			return;
		}
		$status = $xmlHelper->getPropertyValue( 'contentStatus', $pageNode );
		if ( $status !== 'current' ) {
			return;
		}
		$spaceId = $xmlHelper->getPropertyValue( 'space', $pageNode );
		if ( $spaceId === null ) {
			return;
		}
		$originalVersionID = $xmlHelper->getPropertyValue( 'originalVersion', $pageNode );
		if ( $originalVersionID !== null ) {
			return;
		}

		$pageId = $xmlHelper->getIDNodeValue( $pageNode );

		$titleBuilder = new TitleBuilder(
			$spaceIdToPrefixMap, $spaceIdHomepages, $pageIdParentPageIdMap,
			$pageIdConfluendTitleMap, $xmlHelper, $this->mainpage
		);
		try {
			$targetTitle = $titleBuilder->buildTitle( $pageNode );
		} catch ( InvalidTitleException $ex ) {
			$this->buckets->addData( 'title-invalids', $pageId, $ex->getInvalidTitle() );
			return;
		}

		$this->output->writeln( "Add page '$targetTitle' (ID:$pageId)" );

		/**
		 * Adds data bucket "pages-titles-map", which contains mapping from page title itself to full page title.
		 * Full page title contains parent pages and namespace (if it is not general space).
		 * Example:
		 * "Detailed_planning" -> "Dokumentation/Detailed_planning"
		 */
		$pageConfluenceTitle = $xmlHelper->getPropertyValue( 'title', $pageNode );
		$genericTitleBuilder = new GenericTitleBuilder( [] );
		$pageConfluenceTitle = $genericTitleBuilder
			->appendTitleSegment( $pageConfluenceTitle )->build();
		// We need to preserve the spaceID, so we can properly resolve cross-space links
		// in the `convert` stage
		$pageConfluenceTitle = "$spaceId---{$pageConfluenceTitle}";
		// Some normalization
		$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );
		$this->customBuckets->addData( 'pages-titles-map', $pageConfluenceTitle, $targetTitle, false, true );
		$this->customBuckets->addData( 'page-id-to-confluence-key-map', $pageId, $pageConfluenceTitle, false, true );

		// Also add pages IDs in Confluence to full page title mapping.
		// It is needed to have enough context on converting stage,
		// to know from filename which page is currently being converted.
		$this->customBuckets->addData( 'page-id-to-title-map', $pageId, $targetTitle, false, true );
		$this->customBuckets->addData( 'page-id-to-space-id', $pageId, $spaceId, false, true );

		$revisionTimestamp = $this->buildRevisionTimestamp( $xmlHelper, $pageNode );
		$bodyContentIds = $this->getBodyContentIds( $xmlHelper, $pageNode );
		if ( !empty( $bodyContentIds ) ) {
			foreach ( $bodyContentIds as $bodyContentId ) {
				// TODO: Add UserImpl-key or directly MediaWiki username
				// (could also be done in `extract` as "metadata" )
				$this->customBuckets->addData( 'body-contents-to-pages-map', $bodyContentId, $pageId, false, true );
			}
		} else {
			$bodyContentIds = [];

			foreach ( $bodyContents as $bodyContentId => $contentPageId ) {
				if ( $pageId === $contentPageId ) {
					$bodyContentIds[] = $bodyContentId;

					$this->customBuckets->addData(
						'body-contents-to-pages-map',
						$bodyContentId,
						$pageId,
						false,
						true
					);
				}
			}
		}

		$version = $xmlHelper->getPropertyValue( 'version', $pageNode );

		$this->addTitleRevision( $targetTitle, implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp" );

		// Find attachments

		$this->getAttachmentsFromCollection( $xmlHelper, $pageNode, $spaceId );
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $element
	 * @param int $spaceId
	 * @return void
	 */
	private function getAttachmentsFromCollection( XMLHelper $xmlHelper, DOMElement $element, int $spaceId ): void {
		$pageIdConflueTitleMap = $this->customBuckets->getBucketData( 'page-id-to-confluence-title-map' );
		$pageIdConfluenKeyMap = $this->customBuckets->getBucketData( 'page-id-to-confluence-key-map' );
		$pagesTitlesMap = $this->customBuckets->getBucketData( 'pages-titles-map' );
		$spaceIdToPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$attachmentIdToOrigFilenameMap = $this->customBuckets->getBucketData( 'attachment-id-to-orig-filename-map' );
		$attachmentIdToSpaceIdMap = $this->customBuckets->getBucketData( 'attachment-id-to-space-id-map' );
		$attachmentIdToReferenceMap = $this->customBuckets->getBucketData( 'attachment-id-to-reference-map' );

		$pageId = $xmlHelper->getIDNodeValue( $element );
		$confluenceTitle = $pageIdConflueTitleMap[$pageId];
		$confluenceKey = $pageIdConfluenKeyMap[$pageId];
		$wikiTitle = $pagesTitlesMap[$confluenceKey];

		// In case of ERM34465 this seems to be empty because
		// title-attachments and debug-missing-attachment-id-to-filename are empty
		$attachmentRefs = $xmlHelper->getElementsFromCollection( 'attachments', $element );
		foreach ( $attachmentRefs as $attachmentRef ) {
			$attachmentId = $xmlHelper->getIDNodeValue( $attachmentRef );
			if ( in_array( $attachmentId, $this->addedAttachmentIds ) ) {
				continue;
			}
			if ( !isset( $attachmentIdToOrigFilenameMap[$attachmentId] ) ) {
				continue;
			}
			$attachmentOrigFilename = $attachmentIdToOrigFilenameMap[$attachmentId];
			if ( isset( $attachmentIdToSpaceIdMap[$attachmentId] ) ) {
				$attachmentSpaceId = $attachmentIdToSpaceIdMap[$attachmentId];
			} else {
				$attachmentSpaceId = $spaceId;
			}
			$attachmentTargetFilename = $this->makeAttachmentTargetFilenameFromData(
				$confluenceTitle, $attachmentId, $attachmentSpaceId,
				$attachmentOrigFilename, $wikiTitle, $spaceIdToPrefixMap
			);
			if ( !isset( $attachmentIdToReferenceMap[$attachmentId] ) ) {
				continue;
			}
			$attachmentReference = $attachmentIdToReferenceMap[$attachmentId];

			// In case of ERM34465 no files are added to title-attachments
			$this->addTitleAttachment( $wikiTitle, $attachmentTargetFilename );
			$this->addFile( $attachmentTargetFilename, $attachmentReference );
			$this->customBuckets->addData( 'title-files', $wikiTitle, $attachmentTargetFilename, false, true );
			$this->addedAttachmentIds[] = $attachmentId;

			$confluenceFileKey = str_replace( ' ', '_', "{$spaceId}---{$confluenceTitle}---{$attachmentOrigFilename}" );
			$this->customBuckets->addData(
				'attachment-confluence-file-key-to-target-filename-map',
				$confluenceFileKey,
				$attachmentTargetFilename,
				false,
				true
			);

			$this->customBuckets->addData(
				'attachment-id-to-target-filename-map',
				$attachmentId,
				$attachmentTargetFilename
			);

			$this->customBuckets->addData(
				'attachment-orig-filename-target-filename-map',
				$attachmentOrigFilename,
				$attachmentTargetFilename
			);
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function buildTitleAttachmentsFallbackMaps( DOMDocument $dom ): void {
		$spaceIdPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$attachmentIdToOrigFilenameMap = $this->customBuckets->getBucketData( 'attachment-id-to-orig-filename-map' );
		$attachmentIdToReferenceMap = $this->customBuckets->getBucketData( 'attachment-id-to-reference-map' );
		$attachmentIdToSpaceIdMap = $this->customBuckets->getBucketData( 'attachment-id-to-space-id-map' );
		$pageIdToTitleMap = $this->customBuckets->getBucketData( 'page-id-to-title-map' );
		$pageIdToConfluenceKey = $this->customBuckets->getBucketData( 'page-id-to-confluence-key-map' );

		$xmlHelper = new XMLHelper( $dom );

		$attachmentObjects = $xmlHelper->getObjectNodes( 'Attachment' );
		if ( count( $attachmentObjects ) < 1 ) {
			return;
		}
		$attachmentNode = $attachmentObjects->item( 0 );
		if ( $attachmentNode instanceof DOMElement === false ) {
			return;
		}
		$attachmentNodeContentStatus = $xmlHelper->getPropertyValue( 'contentStatus', $attachmentNode );
		if ( strtolower( $attachmentNodeContentStatus ) !== 'current' ) {
			return;
		}
		$attachmentId = $xmlHelper->getIDNodeValue( $attachmentNode );
		if ( in_array( $attachmentId, $this->addedAttachmentIds ) ) {
			return;
		}
		if ( !in_array( $attachmentId, $this->availableAttachmentIds ) ) {
			return;
		}
		if ( !isset( $attachmentIdToOrigFilenameMap[$attachmentId] ) ) {
			return;
		}
		$attachmentOrigFilename = $attachmentIdToOrigFilenameMap[$attachmentId];

		// Check to which page attachment belongs
		$targetTitle = '';
		$confluenceKey = '';
		$containerContentId = $xmlHelper->getPropertyValue( 'containerContent', $attachmentNode );
		if ( $containerContentId !== null ) {
			if ( isset( $pageIdToTitleMap[$containerContentId] ) ) {
				$targetTitle = $pageIdToTitleMap[$containerContentId];
			}
			if ( isset( $pageIdToConfluenceKey[$containerContentId] ) ) {
				$confluenceKey = $pageIdToConfluenceKey[$containerContentId];
			} else {
				return;
			}
		}
		// TODO: Is this wise?
		$attachmentSpaceId = 0;
		if ( isset( $attachmentIdToSpaceIdMap[$attachmentId] ) ) {
			$attachmentSpaceId = $attachmentIdToSpaceIdMap[$attachmentId];
		}
		$attachmentTargetFilename = $this->makeAttachmentTargetFilenameFromData(
			$confluenceKey, $attachmentId, $attachmentSpaceId, $attachmentOrigFilename,
			$targetTitle, $spaceIdPrefixMap
		);

		if ( !isset( $attachmentIdToReferenceMap[$attachmentId] ) ) {
			$this->output->writeln(
				//phpcs:ignore Generic.Files.LineLength.TooLong
				"\033[31m\t- File '$attachmentId' ($attachmentTargetFilename) not found\033[39m"
			);
			return;
		}

		$attachmentReference = $attachmentIdToReferenceMap[$attachmentId];

		if ( $confluenceKey !== '' ) {
			$this->addTitleAttachment( $targetTitle, $attachmentTargetFilename );
			$this->output->writeln( "Add attachment $attachmentTargetFilename (fallback: {$confluenceKey})" );
		} else {
			$this->customBuckets->addData(
				'additional-files', $attachmentTargetFilename, $attachmentReference, false, true );
			$this->output->writeln( "Add attachment $attachmentTargetFilename (additional)" );
		}

		$this->addFile( $attachmentTargetFilename, $attachmentReference );
		$this->addedAttachmentIds[] = $attachmentId;

		$confluenceFileKey = str_replace( ' ', '',  "{$confluenceKey}---{$attachmentOrigFilename}" );
		$this->customBuckets->addData(
			'attachment-confluence-file-key-to-target-filename-map',
			$confluenceFileKey,
			$attachmentTargetFilename,
			false,
			true
		);

		$this->customBuckets->addData(
			'attachment-id-to-target-filename-map',
			$attachmentId,
			$attachmentTargetFilename
		);

		$this->customBuckets->addData(
			'attachment-orig-filename-target-filename-map',
			$attachmentOrigFilename,
			$attachmentTargetFilename
		);
	}

	/**
	 * @param int|string $spaceKey
	 * @param string $spaceName
	 * @return string
	 */
	private function sanitizeUserSpaceKey( $spaceKey, $spaceName ) {
		$spaceKey = substr( $spaceKey, 1, strlen( $spaceKey ) - 1 );
		if ( is_numeric( $spaceKey ) ) {
			$spaceKey = $spaceName;
		}
		$spaceKey = preg_replace( '/[^A-Za-z0-9]/', '', $spaceKey );
		return 'User' . ucfirst( $spaceKey );
	}

	/**
	 * @param string $userName
	 * @return string
	 */
	private function makeMWUserName( $userName ) {
		// Email adresses are no valid MW usernames. We just use the first part
		// While this could lead to collisions it is very unlikly
		$usernameParts = explode( '@', $userName, 2 );
		$newUsername = $usernameParts[0];
		$newUsername = ucfirst( strtolower( $newUsername ) );

		// A MW username must always be avalid page title
		$titleBuilder = new GenericTitleBuilder( [] );
		$titleBuilder->appendTitleSegment( $newUsername );

		return $titleBuilder->build();
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $pageNode
	 * @return string
	 */
	private function buildRevisionTimestamp( XMLHelper $xmlHelper, DOMElement $pageNode ): string {
		$lastModificationDate = $xmlHelper->getPropertyValue( 'lastModificationDate', $pageNode );
		$time = strtotime( $lastModificationDate );
		$mwTimestamp = date( 'YmdHis', $time );
		return $mwTimestamp;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $pageNode
	 * @return array
	 */
	private function getBodyContentIds( XMLHelper $xmlHelper, DOMElement $pageNode ): array {
		$ids = [];
		$bodyContentEl = $xmlHelper->getElementsFromCollection( 'bodyContents', $pageNode );

		foreach ( $bodyContentEl as $bodyContentElement ) {
			$ids[] = $xmlHelper->getIDNodeValue( $bodyContentElement );
		}
		return $ids;
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	private function hasNoExplicitFileExtension( $file ) {
		if ( $file->getExtension() === '' ) {
			return true;
		}
		// Evil hack for Names like "02.1 Some-Workflow File"
		if ( strlen( $file->getExtension() ) > 10 ) {

		}
		return false;
	}

	/**
	 * @param string $pageConfluenceTitle
	 * @param int $attachmentId
	 * @param int $attachmentSpaceId
	 * @param string $attachmentOrigFilename
	 * @param string $containerTitle
	 * @param array $spaceIdToPrefixMap
	 * @return string
	 */
	private function makeAttachmentTargetFilenameFromData(
		string $pageConfluenceTitle, int $attachmentId, int $attachmentSpaceId,
		string $attachmentOrigFilename, string $containerTitle, array $spaceIdToPrefixMap
	): string {
		$filenameBuilder = new FilenameBuilder( $spaceIdToPrefixMap, null );
		try {
			$targetName = $filenameBuilder->buildFromAttachmentData(
				$attachmentSpaceId, $attachmentOrigFilename, $containerTitle );
		} catch ( InvalidTitleException $e ) {
			try {
				// Probably it is just too long. Let's try to use a shortened variant
				// This is not ideal, but should be okay as a fallback in most cases.
				$shortTargetTitle = basename( $containerTitle );
				$targetName = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId, $attachmentOrigFilename, $shortTargetTitle );
			} catch ( InvalidTitleException $ex ) {
				$this->buckets->addData( 'title-invalids', $attachmentId, $ex->getInvalidTitle() );
				$this->logger->error( $ex->getMessage() );
				return '###INVALID###';
			}
		}

		/*
		 * Some attachments do not have a file extension available. We try
		 * to find an extension by looking a the content type, but
		 * sometimes even this won't help... ("octet-stream")
		 */
		$file = new SplFileInfo( $targetName );
		if ( $this->hasNoExplicitFileExtension( $file ) ) {
			$this->logger->debug(
				"Could not find file extension for $attachmentId"
			);
			$targetName .= '.unknown';
		}

		$fileKey = "{$pageConfluenceTitle}---$attachmentOrigFilename";
		// Some normalization
		$fileKey = str_replace( ' ', '_', $fileKey );
		$this->customBuckets->addData( 'filenames-to-filetitles-map', $fileKey, $targetName, false, true );

		return $targetName;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $attachment
	 * @return void
	 */
	private function makeAttachmentReference( XMLHelper $xmlHelper, DOMElement $attachment ) {
		$basePath = $this->currentFile->getPath() . '/attachments';
		$attachmentId = $xmlHelper->getIDNodeValue( $attachment );
		$containerId = $xmlHelper->getPropertyValue( 'content', $attachment );
		if ( empty( $containerId ) ) {
			$containerId = $xmlHelper->getPropertyValue( 'containerContent', $attachment );
		}
		$attachmentVersion = $xmlHelper->getPropertyValue( 'attachmentVersion', $attachment );
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = $xmlHelper->getPropertyValue( 'version', $attachment );
		}

		/**
		 * Sometimes there is no explicit version set in the "attachment" object. In such cases
		 * there we always fetch the highest number from the respective directory
		 */
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = '__LATEST__';
		}

		$path = $basePath . "/" . $containerId . '/' . $attachmentId . '/' . $attachmentVersion;
		if ( !file_exists( $path ) ) {
			return '';
		}

		return $path;
	}
}
