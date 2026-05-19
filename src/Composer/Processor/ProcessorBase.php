<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IConfluenceComposerProcessor {

	/** @var Builder */
	protected Builder $builder;

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

	/** @var bool */
	protected bool $multiXmlOutputEnabled = false;

	/** @var int */
	protected int $limit = 0;

	/** @var int */
	protected int $numOfRevisions = 0;

	/** @var int */
	protected int $outputXmlFile = 0;

	/**
	 * @param Builder $builder
	 * @param DBComposerDataLookup $dataLookup
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		Builder $builder, DBComposerDataLookup $dataLookup, Workspace $workspace,
		Output $output, string $dest, MigrationConfig $migrationConfig
	) {
		$this->builder = $builder;
		$this->dataLookup = $dataLookup;
		$this->workspace = $workspace;
		$this->output = $output;
		$this->dest = $dest;
		$this->migrationConfig = $migrationConfig;

		$this->limit = $this->migrationConfig->getComposerPagePerXmlLimit();
		if ( $this->limit > 0 ) {
			$this->multiXmlOutputEnabled = true;
		}
	}

	/**
	 * @param string $wikiPageName
	 * @param string $wikiText
	 * @param string $timestamp
	 * @param string $username
	 * @param string $model
	 * @param string $format
	 * @param array $slotData
	 *
	 * @return void
	 */
	protected function addRevision(
		string $wikiPageName, string $wikiText, string $timestamp = '',
		string $username = '', string $model = '', string $format = '', array $slotData = []
	): void {
		$this->builder->addRevision(
			$wikiPageName, $wikiText, $timestamp, $username, $model, $format, $slotData
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
	 * This is not yet supported by MediaWiki
	 *
	 * @param string $filename
	 * @param string $wikitext
	 * @param string $base64Contents
	 * @param string $timestamp
	 * @param string $username
	 * @param string $model
	 * @param string $format
	 * @return void
	 */
	protected function addFileRevision(
		string $filename, string $wikitext, string $base64Contents,
		string $timestamp = '', string $username = '', string $model = '', string $format = ''
	): void {
		$this->builder->addFileRevision(
			$filename, $wikitext, $base64Contents, $timestamp, $username, $model, $format
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
	 * Sometimes not all namespaces should be used for the import.
	 * To skip this namespaces use this option.
	 *
	 * @param string $pageTitle
	 * @return bool
	 */
	protected function skipTitle( string $pageTitle ): bool {
		$namespace = $this->getNamespace( $pageTitle );

		$skipNamespaces = $this->migrationConfig->getComposerSkipNamespaces();
		if ( in_array( $namespace, $skipNamespaces )
		) {
			$this->output->writeln( "Namespace $namespace skipped by configuration" );
			return true;
		}

		// Sometimes titles have contents >256kB which might break the import. To skip this titles
		// use this option
		$skipTitles = $this->migrationConfig->getComposerSkipTitles();
		if ( in_array( $pageTitle, $skipTitles )) {
			$this->output->writeln( "Page $pageTitle skipped by configuration" );
			return true;
		}
		return false;
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
		return 'output';
	}
}
