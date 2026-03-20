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
use Symfony\Component\Console\Output\Output;

class ConfluenceComposer extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/**
	 * @var DataBuckets
	 */
	private $customBuckets;

	/**
	 * @var Output
	 */
	private $output = null;

	/** @var array */
	private $advancedConfig = [];

	/** @var string */
	private $dest = '';

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->customBuckets = new DataBuckets( [
			'title-uploads',
			'title-uploads-fail'
		] );

		$this->customBuckets->loadFromWorkspace( $this->workspace );

		if ( isset( $config['config'] ) ) {
			$this->advancedConfig = $config['config'];
		}
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
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ) {
		$processors = [
			new Files(
				$builder, $this->buckets, $this->workspace,
				$this->output, $this->dest, $this->advancedConfig
			),
			new Pages(
				$builder, $this->buckets, $this->workspace,
				$this->output, $this->dest, $this->advancedConfig
			),
			new Comments(
				$builder, $this->buckets, $this->workspace,
				$this->output, $this->dest, $this->advancedConfig
			),
		];

		foreach ( $processors as $processor ) {
			$processor->execute();
		}

		$this->customBuckets->saveToWorkspace( $this->workspace );
	}
}
