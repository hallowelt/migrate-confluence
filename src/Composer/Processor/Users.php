<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MigrateConfluence\Composer\DataReader\IComposerDataReader;
use HalloWelt\MigrateConfluence\Composer\IConfluenceComposerProcessor;
use HalloWelt\MigrateConfluence\Utility\WikiUserXmlBuilder;
use Symfony\Component\Console\Output\Output;

class Users implements IConfluenceComposerProcessor {

	/** @var WikiUserXmlBuilder */
	private WikiUserXmlBuilder $builder;

	/** @var IComposerDataReader */
	private IComposerDataReader $reader;

	/** @var Output */
	private Output $output;

	/** @var string */
	private string $dest = '';

	/**
	 * @var string
	 */
	private string $subDir = '';

	/**
	 * @param IComposerDataReader $reader
	 * @param Output $output
	 * @param string $dest
	 */
	public function __construct(
		IComposerDataReader $reader, Output $output, string $dest
	) {
		$this->builder = new WikiUserXmlBuilder();
		$this->reader = $reader;
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
		$users = $this->reader->getUsers();

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
