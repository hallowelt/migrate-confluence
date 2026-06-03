<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikiFileXmlBuilder;
use Symfony\Component\Console\Output\Output;

abstract class FileProcessorBase implements IConfluenceComposerProcessor {

	/** @var WikiFileXmlBuilder */
	protected WikiFileXmlBuilder $builder;

	/** @var DBComposerDataLookup */
	protected DBComposerDataLookup $dataLookup;

	/** @var Workspace */
	protected Workspace $workspace;

	/** @var Output */
	protected Output $output;

	/** @var string */
	protected string $dest = '';

	/** @var MigrationConfig */
	protected MigrationConfig $migrationConfig;

	/** @var ComposerDeploymentInfo */
	protected ComposerDeploymentInfo $deploymentInfo;

	/** @var bool */
	protected bool $multiXmlOutputEnabled = false;

	/** @var int */
	protected int $limit = 0;

	/** @var int */
	protected int $numOfRevisions = 0;

	/** @var int */
	protected int $outputXmlFile = 0;

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 * @param ComposerDeploymentInfo $deploymentInfo
	 */
	public function __construct(
		DBComposerDataLookup $dataLookup, Workspace $workspace,
		Output $output, string $dest, MigrationConfig $migrationConfig,
		ComposerDeploymentInfo $deploymentInfo
	) {
		$this->dataLookup = $dataLookup;
		$this->workspace = $workspace;
		$this->output = $output;
		$this->dest = $dest;
		$this->migrationConfig = $migrationConfig;
		$this->deploymentInfo = $deploymentInfo;

		$this->builder = new WikiFileXmlBuilder();

		$this->limit = $this->migrationConfig->getComposerPagePerXmlLimit();
		if ( $this->limit > 0 ) {
			$this->multiXmlOutputEnabled = true;
		}
	}

	/**
	 * @param string $fileTitle
	 * @param string $path
	 * @param string $timestamp
	 * @param string $contributor
	 * @return void
	 */
	protected function addFileRevision(
		string $fileTitle, string $path,
		string $timestamp = '', string $contributor = '' ): void {
		$this->builder->addFileRevision(
			$fileTitle, $path, $timestamp, $contributor
		);
		$this->numOfRevisions++;

		if ( $this->multiXmlOutputEnabled ) {
			if ( $this->numOfRevisions >= $this->limit ) {
				$this->writeOutputFile();
				$this->numOfRevisions = 0;
			}
		}
	}

	/**
	 * @return void
	 */
	protected function writeOutputFile(): void {
		$name = $this->getOutputName();

		if ( $this->multiXmlOutputEnabled ) {
			$this->outputXmlFile++;
			$num = (string)$this->outputXmlFile;
			$name .= '-' . str_pad( $num, 8, '0', STR_PAD_LEFT );
		}

		$name .= '.xml';

		$this->builder->buildAndSave( $this->dest . "/result/$name" );
		$this->builder->reset();
	}

	/**
	 * @return bool
	 */
	protected function includeHistory(): bool {
		return $this->migrationConfig->getIncludeHistory();
	}

	/**
	 * Sometimes it is neccessary to skip certain pages or namespace.
	 * This can be because e.g. the content length is to long or the namespace
	 * should simply not be part of the migration result.
	 *
	 * To skip this namespaces use this option.
	 *
	 * @param string $title
	 * @return bool
	 */
	protected function skipTitleByConfig( string $title ): bool {
		$namespace = $this->getNamespace( $title );

		// Skip namespaces by configuration
		$skipNamespaces = $this->migrationConfig->getComposerSkipNamespaces();
		if ( in_array( $namespace, $skipNamespaces )
		) {
			$this->output->writeln( "Namespace $namespace skipped by configuration" );
			return true;
		}

		// Skip titles by configuration
		$skipTitles = $this->migrationConfig->getComposerSkipTitles();
		if ( in_array( $title, $skipTitles ) ) {
			$this->output->writeln( "Page $title skipped by configuration" );
			return true;
		}
		return false;
	}

	/**
	 * Skip pages automatically that have to long titles or contents.
	 *
	 * @param int $pageId
	 * @param string $title
	 * @return bool
	 */
	protected function skipPageId( int $pageId, string $title ): bool {
		if ( !$this->dataLookup->isPageInvalid( $pageId ) ) {
			$this->output->writeln( "Page $title skipped due to invalid title or content" );
			return true;
		}
		return false;
	}

	/**
	 * Skip pages automatically that have to long titles or contents.
	 *
	 * @param int $pageId
	 * @param string $title
	 * @return bool
	 */
	protected function skipBlogPostId( int $pageId, string $title ): bool {
		if ( !$this->dataLookup->isBlogPostInvalid( $pageId ) ) {
			$this->output->writeln( "BlogPost $title skipped due to invalid title or content" );
			return true;
		}
		return false;
	}

	/**
	 * Skip pages automatically that have to long titles or contents.
	 *
	 * @param int $attachmentId
	 * @param string $title
	 * @return bool
	 */
	protected function skipAttachmentId( int $attachmentId, string $title ): bool {
		if ( !$this->dataLookup->isAttachmentInvalid( $attachmentId ) ) {
			$this->output->writeln( "Attachment $title skipped due to invalid title or content" );
			return true;
		}
		return false;
	}

	/**
	 * We do not need DrawIO data files in our wiki, just PNG image
	 *
	 * @param string $filename
	 * @return bool
	 */
	protected function isDrawioDataFile( string $filename ): bool {
		$drawIoFileHandler = new DrawIOFileHandler();
		return $drawIoFileHandler->isDrawIODataFile( $filename );
	}

	/**
	 * Generalize file title. It can contain a namespace.
	 *
	 * @param string $pageTitle
	 * @return string
	 */
	protected function gereralizeFilename( string $pageTitle ): string {
		return str_replace( ':', '_', $pageTitle );
	}

	/**
	 * @param string $title
	 * @return string
	 */
	protected function getNamespace( string $title ): string {
		$colonPos = strpos( $title, ':' );
		if ( $colonPos === false ) {
			return 'NS_MAIN';
		}
		return substr( $title, 0, $colonPos );
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'files';
	}

	/**
	 * @param string $filePath
	 * @return string
	 */
	protected function getRelativeFilePath( string $filePath ): string {
		// strip /result form $uploadPath to get the reference path for the file
		return str_replace( '/result/', './', $filePath );
	}
}
