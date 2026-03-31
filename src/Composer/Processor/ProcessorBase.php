<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IConfluenceComposerProcessor {

	/** @var Builder */
	protected $builder;

	/** @var DataBuckets */
	protected $buckets;

	/** @var Workspace */
	protected $workspace;

	/** @var Output */
	protected $output;

	/** @var string */
	protected $dest = '';

	/** @var array */
	protected $config = [];

	/** @var bool */
	protected $multiXmlOutputEnabled = false;

	/** @var int */
	protected $limit = 0;

	/** @var int */
	protected $numOfRevisions = 0;

	/** @var int */
	protected $outputXmlFile = 0;

	/**
	 * @param Builder $builder
	 * @param DataBuckets $buckets
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param array $config
	 */
	public function __construct(
		Builder $builder, DataBuckets $buckets, Workspace $workspace,
		Output $output, string $dest, array $config
	) {
		$this->builder = $builder;
		$this->buckets = $buckets;
		$this->workspace = $workspace;
		$this->output = $output;
		$this->dest = $dest;
		$this->config = $config;

		if ( isset( $this->config['composer-page-per-xml-limit'] ) ) {
			$this->limit = $this->config['composer-page-per-xml-limit'];
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
		if ( isset( $this->config['include-history'] )
			&& $this->config['include-history'] === true
		) {
			return true;
		}
		return false;
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
		if (
			isset( $this->config['composer-skip-namespace'] )
			&& in_array( $namespace, $this->config['composer-skip-namespace'] )
		) {
			$this->output->writeln( "Namespace $namespace skipped by configuration" );
			return true;
		}

		// Sometimes titles have contents >256kB which might break the import. To skip this titles
		// use this option
		if (
			isset( $this->advancedConfig['composer-skip-titles'] )
			&& in_array( $pageTitle, $this->advancedConfig['composer-skip-titles'] )
		) {
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
