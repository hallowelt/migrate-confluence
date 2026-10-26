<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use HalloWelt\MigrateConfluence\Composer\ISpaceIdsDependentProcessor;
use HalloWelt\MigrateConfluence\Composer\ISubDirAware;
use HalloWelt\MigrateConfluence\Utility\DirectoryHelper;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\PageConstraintsXml;
use Symfony\Component\Console\Output\Output;

abstract class ProcessorBase implements IConfluenceComposerProcessor, ISpaceIdsDependentProcessor, ISubDirAware {

	/** @var bool */
	protected bool $multiXmlOutputEnabled = false;

	/** @var int */
	protected int $limit = 0;

	/** @var int */
	protected int $numOfRevisions = 0;

	/** @var int */
	protected int $outputXmlFile = 0;

	/** @var string */
	protected string $subDir = '';

	/** @var int[]|null */
	protected ?array $currentSpaceIds = null;

	/** @var array<string, array> Constraints rows by wiki title */
	private array $pageConstraints = [];

	/** @var array<string, true> Titles in the output file under construction */
	private array $titlesInOutputFile = [];

	/**
	 * @param Builder $builder
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		protected Builder $builder,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig,
	) {
		$this->limit = $this->migrationConfig->getComposerPagePerXmlLimit();
		if ( $this->limit > 0 ) {
			$this->multiXmlOutputEnabled = true;
		}
	}

	/**
	 * @param string $name
	 * @return void
	 */
	public function setSubDir( string $name ): void {
		$this->subDir = $name;
	}

	/**
	 * @param int[] $spaceIds
	 * @return void
	 */
	public function setCurrentSpaceIds( array $spaceIds ): void {
		$this->currentSpaceIds = $spaceIds;
	}

	/**
	 * Sets the restrictions to be written for a page. Must be called before its revisions are added.
	 *
	 * @param string $wikiTitle
	 * @param array|null $row Stored constraints; null (none found) restricts the page completely
	 */
	protected function registerPageConstraints( string $wikiTitle, ?array $row ): void {
		if ( !$this->migrationConfig->getAddPageConstraints() ) {
			return;
		}
		if ( $row === null ) {
			$this->output->writeln( "Warning: No page constraints found for '$wikiTitle'; page is locked down." );
			$row = PageConstraintsXml::FAIL_CLOSED;
		}
		$this->pageConstraints[$wikiTitle] = $row;
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
		$this->titlesInOutputFile[$wikiPageName] = true;
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
		$this->titlesInOutputFile['File:' . $filename] = true;
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

		$basePath = $this->getBasePath();

		// Pages spanning several output files need the element in each of them
		foreach ( array_keys( $this->titlesInOutputFile ) as $title ) {
			if ( isset( $this->pageConstraints[$title] ) ) {
				$element = $this->builder->createCustomPageElement( 'pageconstraints' );
				PageConstraintsXml::fill( $element, $this->pageConstraints[$title] );
				$this->builder->addCustomPageElement( $title, $element );
			}
		}
		$this->titlesInOutputFile = [];

		$this->builder->buildAndSave( $basePath . $name );
		$this->builder->reset();
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

	/**
	 * @return string
	 */
	private function getBasePath(): string {
		$basePath = $this->dest . "/result/";
		if ( $this->subDir !== '' ) {
			$basePath .= $this->subDir . '/';
		}
		DirectoryHelper::ensure( $basePath );
		return $basePath;
	}
}
