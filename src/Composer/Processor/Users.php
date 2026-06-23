<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\WikiUserXmlBuilder;
use Symfony\Component\Console\Output\Output;

class Users implements IConfluenceComposerProcessor {

	/** @var WikiUserXmlBuilder */
	private WikiUserXmlBuilder $builder;

	/** @var DBComposerDataLookup */
	private DBComposerDataLookup $dataLookup;

	/** @var Output */
	private Output $output;

	/** @var string */
	private string $dest = '';

	/**
	 * @var string
	 */
	private string $subDir = '';

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
	 * @param string $name
	 * @return void
	 */
	public function setSubDir( string $name ): void {
		$this->subDir = $name;
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

		$basePath = $this->dest . "/result/";
		if ( $this->subDir !== '' ) {
			$basePath .= $this->subDir . '/';
		}

		$this->builder->buildAndSave( $basePath . $name );
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
