<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\Comments;
use HalloWelt\MigrateConfluence\Composer\Processor\Files;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use HalloWelt\MigrateConfluence\Composer\Processor\TemplateContentPostProcessor;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class ConfluenceComposer extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/**
	 * @var Output|null
	 */
	private ?Output $output = null;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var string */
	private string $dest = '';

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		if ( isset( $config['config'] ) ) {
			$this->migrationConfig = new MigrationConfig( $config['config'] );
		} else {
			$this->migrationConfig = new MigrationConfig( [] );
		}
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	/**
	 * @inheritDoc
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ): void {
		$workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );
		$composerDataLookup = new DBComposerDataLookup( $workspaceDB );
		$processors = [
			new Files(
				$builder, $composerDataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig
			),
			new Pages(
				$builder, $composerDataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				new TemplateContentPostProcessor()
			),
			new Comments(
				$builder, $composerDataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig
			),
		];

		foreach ( $processors as $processor ) {
			$processor->execute();
		}
	}
}
