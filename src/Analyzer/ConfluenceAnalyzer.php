<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as MigrationTitleBuilder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

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
	 * @param Output
	 */
	private $output = null;

	/**
	 *
	 * @var array
	 */
	private $addedAttachmentIds = [];

	/**
	 *
	 * @var string
	 */
	private $pageConfluenceTitle = '';

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
			'space-id-homepages',
			'pages-titles-map',
			'pages-ids-to-titles-map',
			'body-contents-to-pages-map',
			'title-invalids',
			'filenames-to-filetitles-map',
			'page-id-to-space-id',
			'attachment-file-extensions',
			'space-name-to-prefix-map',
			'missing-attachment-id-to-filename',
			'userkey-to-username-map',
			'users',
			'title-files',
			'additional-files'
		] );
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
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
		$this->dom = new DOMDocument();
		$this->dom->load( $file->getPathname() );
		$this->helper = new XMLHelper( $this->dom );

		$this->userMap();
		$this->makeSpacesMap();
		$this->makePagenamesMap();
		$this->addAdditionalFiles();

		return true;
	}

	private function makeSpacesMap() {
		$spaces = $this->helper->getObjectNodes( 'Space' );
		$this->output->writeln( "\nFinding namespaces" );
		foreach ( $spaces as $space ) {
			$spaceId = $this->helper->getIDNodeValue( $space );
			$spaceKey = $this->helper->getPropertyValue( 'key', $space );
			$spaceName = $this->helper->getPropertyValue( 'name', $space );
			if ( substr( $spaceKey, 0, 1 ) === '~' ) {
				// User namespaces
				$spaceKey = $this->sanitizeUserSpaceKey( $spaceKey, $spaceName );
				$this->output->writeln( "\033[31m- $spaceKey (ID:$spaceId) - protected user namespace\033[39m" );
			} else {
				$this->output->writeln( "- $spaceKey (ID:$spaceId)" );
			}

			// Confluence's GENERAL equals MediaWiki's NS_MAIN, thus having no prefix
			if ( $spaceKey === 'GENERAL' ) {
				$spaceKey = '';
			}
			$this->customBuckets->addData( 'space-id-to-prefix-map', $spaceId, $spaceKey, false, true );
			$this->customBuckets->addData( 'space-name-to-prefix-map', $spaceName, $spaceKey, false, true );

			$homePagePropertyNode = $this->helper->getPropertyNode( 'homePage' );
			$homePageId = $this->helper->getIDNodeValue( $homePagePropertyNode );
			$this->customBuckets->addData( 'space-id-homepages', $spaceId, $homePageId, false, true );
		}
	}

	/**
	 *
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

	private function makePagenamesMap() {
		$this->output->writeln( "\nFinding pages" );
		$pageNodes = $this->helper->getObjectNodes( "Page" );
		$spaceIdPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$spaceIdHomepages = $this->customBuckets->getBucketData( 'space-id-homepages' );
		$titleBuilder = new TitleBuilder( $spaceIdPrefixMap, $spaceIdHomepages, $this->helper );
		foreach ( $pageNodes as $pageNode ) {
			if ( $pageNode instanceof DOMElement === false ) {
				continue;
			}

			$status = $this->helper->getPropertyValue( 'contentStatus', $pageNode );
			if ( $status !== 'current' ) {
				continue;
			}

			$spaceId = $this->helper->getPropertyValue( 'space', $pageNode );
			if ( $spaceId === null ) {
				continue;
			}

			$originalVersionID = $this->helper->getPropertyValue( 'originalVersion', $pageNode );
			if ( $originalVersionID !== null ) {
				continue;
			}

			$pageId = $this->helper->getIDNodeValue( $pageNode );

			try {
				$targetTitle = $titleBuilder->buildTitle( $pageNode );
			} catch ( InvalidTitleException $ex ) {
				$this->buckets->addData( 'title-invalids', $pageId, $ex->getInvalidTitle() );
				continue;
			}

			$this->output->writeln( "- '$targetTitle' (ID:$pageId)" );

			/**
			 * Adds data bucket "pages-titles-map", which contains mapping from page title itself to full page title.
			 * Full page title contains parent pages and namespace (if it is not general space).
			 * Example:
			 * "Detailed_planning" -> "Dokumentation/Detailed_planning"
			 */
			$this->pageConfluenceTitle = $this->helper->getPropertyValue( 'title', $pageNode );
			$migrationTitleBuilder = new MigrationTitleBuilder( [] );
			$this->pageConfluenceTitle = $migrationTitleBuilder->appendTitleSegment( $this->pageConfluenceTitle )->build();
			// We need to preserve the spaceID, so we can properly resolve cross-space links
			// in the `convert` stage
			$this->pageConfluenceTitle = "$spaceId---{$this->pageConfluenceTitle}";
			// Some normalization
			$this->pageConfluenceTitle = str_replace( ' ', '_', $this->pageConfluenceTitle );
			$this->customBuckets->addData( 'pages-titles-map', $this->pageConfluenceTitle, $targetTitle, false, true );

			// Also add pages IDs in Confluence to full page title mapping.
			// It is needed to have enough context on converting stage,
			// to know from filename which page is currently being converted.
			$this->customBuckets->addData( 'pages-ids-to-titles-map', $pageId, $targetTitle, false, true );

			$this->customBuckets->addData( 'page-id-to-space-id', $pageId, $spaceId, false, true );

			$revisionTimestamp = $this->buildRevisionTimestamp( $pageNode );
			$bodyContentIds = $this->getBodyContentIds( $pageNode );

			foreach ( $bodyContentIds as $bodyContentId ) {
				// TODO: Add UserImpl-key or directly MediaWiki username
				// (could also be done in `extract` as "metadata" )
				$this->customBuckets->addData( 'body-contents-to-pages-map', $bodyContentId, $pageId, false, true );
			}

			$version = $this->helper->getPropertyValue( 'version', $pageNode );

			$this->addTitleRevision( $targetTitle, implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp" );

			$attachmentRefs = $this->helper->getElementsFromCollection( 'attachments', $pageNode );
			foreach ( $attachmentRefs as $attachmentRef ) {
				$attachmentId = $this->helper->getIDNodeValue( $attachmentRef );
				$attachment = $this->helper->getObjectNodeById( $attachmentId, 'Attachment' );
				$attachmentTargetFilename = $this->makeAttachmentTargetFilename( $attachment, $targetTitle );
				$attachmentReference = $this->makeAttachmentReference( $attachment );
				if ( empty( $attachmentReference ) ) {
					$this->output->writeln(
						//phpcs:ignore Generic.Files.LineLength.TooLong
						"\033[31m\t- File '$attachmentId' ($attachmentTargetFilename) not found\033[39m"
					);
					$this->customBuckets->addData(
						'missing-attachment-id-to-filename',
						$attachmentId,
						$attachmentTargetFilename,
						false,
						true
					);
					continue;
				}
				$this->addTitleAttachment( $targetTitle, $attachmentTargetFilename );
				$this->addFile( $attachmentTargetFilename, $attachmentReference );
				$this->customBuckets->addData( 'title-files', $targetTitle, $attachmentTargetFilename, false, true );
				$this->addedAttachmentIds[$attachmentId] = true;
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
		$fileName = $this->helper->getPropertyValue( 'fileName', $attachment );
		if ( $fileName === null ) {
			$fileName = $this->helper->getPropertyValue( 'title', $attachment );
		}

		$spaceIdPrefixMap = $this->customBuckets->getBucketData( 'space-id-to-prefix-map' );
		$filenameBuilder = new FilenameBuilder( $spaceIdPrefixMap, $this->helper );
		$attachmentId = $this->helper->getIDNodeValue( $attachment );
		try {
			$targetName = $filenameBuilder->buildFilename( $attachment, $containerTitle );
		} catch ( InvalidTitleException $e ) {
			try {
				// Probably it is just too long. Let's try to use a shortened variant
				// This is not ideal, but should be okay as a fallback in most cases.
				$shortContainerTitle = basename( $containerTitle );
				$targetName = $filenameBuilder->buildFilename( $attachment, $shortContainerTitle );
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
			$contentType = $this->helper->getPropertyValue( 'contentType', $attachment );
			if ( $contentType === 'application/gliffy+json' ) {
				$targetName .= '.json';
			} elseif ( $contentType === 'application/gliffy+xml' ) {
				$targetName .= '.xml';
			} else {
				$this->logger->debug(
					"Could not find file extension for $fileName as "
						. "{$attachment->getNodePath()}; "
						. "contentType: $contentType"
				);
				$targetName .= '.unknown';
			}
		}

		$fileKey = "{$this->pageConfluenceTitle}---$fileName";
		// Some normalization
		$fileKey = str_replace( ' ', '_', $fileKey );
		$this->customBuckets->addData( 'filenames-to-filetitles-map', $fileKey, $targetName, false, true );

		return $targetName;
	}

	/**
	 *
	 * @param DOMElement $attachment
	 * @return string
	 */
	private function makeAttachmentReference( $attachment ) {
		$basePath = $this->currentFile->getPath() . '/attachments';
		$attachmentId = $this->helper->getIDNodeValue( $attachment );
		$containerId = $this->helper->getPropertyValue( 'content', $attachment );
		if ( empty( $containerId ) ) {
			$containerId = $this->helper->getPropertyValue( 'containerContent', $attachment );
		}
		$attachmentVersion = $this->helper->getPropertyValue( 'attachmentVersion', $attachment );
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = $this->helper->getPropertyValue( 'version', $attachment );
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

		foreach ( $bodyContentEl as $bodyContentElement ) {
			$ids[] = $this->helper->getIDNodeValue( $bodyContentElement );
		}
		return $ids;
	}

	private function addAdditionalFiles() {
		$this->output->writeln( "\nFinding attachments" );
		$attachments = $this->helper->getObjectNodes( 'Attachment' );
		foreach ( $attachments as $attachment ) {
			if ( $attachment instanceof DOMElement === false ) {
				continue;
			}
			$originalVersionID = $this->helper->getPropertyValue( 'originalVersion', $attachment );

			// Skip legacy versions
			if ( $originalVersionID !== null ) {
				continue;
			}

			$sourceContentID = $this->helper->getPropertyValue( 'sourceContent', $attachment );
			if ( !empty( $sourceContentID ) ) {
				// This has already been added as a page attachment
				continue;
			}

			$attachmentId = $this->helper->getIDNodeValue( $attachment );
			if ( isset( $this->addedAttachmentIds[$attachmentId] ) ) {
				// This has already been added as a page attachment
				continue;
			}

			$path = $this->makeAttachmentReference( $attachment );
			$targetName = $this->makeAttachmentTargetFilename( $attachment, '' );
			$this->output->writeln( "- '$targetName'" );
			$this->addFile( $targetName, $path );
			$this->customBuckets->addData( 'additional-files', $targetName, $path, false, true );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function addFile( $rawFilename, $attachmentReference = 'n/a' ) {
		$parts = explode( '.', $rawFilename );
		if ( count( $parts ) > 1 ) {
			$extension = array_pop( $parts );
			$normalExtension = strtolower( $extension );
			$this->customBuckets->addData(
				'attachment-file-extensions',
				'extensions',
				$normalExtension,
				true,
				true
			);
		}
		return parent::addFile( $rawFilename, $attachmentReference );
	}

	/**
	 *
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

	private function userMap() {
		$this->output->writeln( "\nFinding users" );
		$userImpls = $this->helper->getObjectNodes( 'ConfluenceUserImpl' );
		foreach ( $userImpls as $userImpl ) {
			// Can not use `XMLHelper::getIDNodeValue` here, as the key is not an integer
			$idNode = $userImpl->getElementsByTagName( 'id' )->item( 0 );
			$userImplKey = $idNode->nodeValue;
			$lcUserName = $this->helper->getPropertyValue( 'lowerName', $userImpl );
			$email = $this->helper->getPropertyValue( 'email', $userImpl );

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

			$this->output->writeln( "- '$mediaWikiUsername' (ID:$userImplKey)" );
		}
	}

	/**
	 *
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
		$titleBuilder = new MigrationTitleBuilder( [] );
		$titleBuilder->appendTitleSegment( $newUsername );

		return $titleBuilder->build();
	}
}
