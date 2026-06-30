<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikiUserXmlBuilder;
use Phan\LanguageServer\Server\Workspace;
use Symfony\Component\Console\Output\Output;

class Users implements IConfluenceComposerProcessor {

	/** @var WikiUserXmlBuilder */
	private WikiUserXmlBuilder $builder;

	/** @var DBComposerDataLookup */
	private DBComposerDataLookup $dataLookup;

	/** @var Workspace */
	private Workspace $workspace;

	/** @var Output */
	private Output $output;

	/** @var string */
	private string $dest = '';

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var ComposerDeploymentInfo */
	private ComposerDeploymentInfo $deploymentInfo;

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param Output $output
	 * @param string $dest
	 */
	public function __construct(
		DBComposerDataLookup $dataLookup, Output $output, string $dest
	) {
		$this->builder = new WikiUserXmlBuilder();
		$this->dataLookup = $dataLookup;
		$this->output = $output;
		$this->dest = $dest;
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$users = $this->dataLookup->getUsers();

		foreach ( $users as $user ) {
			$wikiUsername = $user['wiki_user_name'];
			$propertiesJson = $user['properties'];
			$properties = json_decode( $propertiesJson, true );
			if ( !is_array( $properties ) ) {
				$properties = [];
			}

			$this->builder->addUser( $wikiUsername, $properties );
		}

		$this->writeOutputFile();
	}

	/**
	 * @return void
	 */
	private function writeOutputFile(): void {
		$name = $this->getOutputName();
		$name .= '.xml';

		$this->builder->buildAndSave( $this->dest . "/result/$name" );
		$this->builder->reset();

		$this->output->writeln( 'Write users.xml' );
	}

	/**
	 * @return string
	 */
	private function getOutputName(): string {
		return 'users';
	}
}
