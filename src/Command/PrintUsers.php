<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\CommandLineTools\Commands\BatchFileProcessorBase;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use XMLReader;

/**
 * Recursively searches `--src` for `entities.xml` files and prints a single,
 * deduplicated CSV of Confluence users to stdout. The CSV is usable as the
 * `--usermap` input of the `analyze` command.
 */
class PrintUsers extends BatchFileProcessorBase {

	/** @var resource */
	private $out;

	/** @var array Tracks confluence-usernames already printed */
	private array $seen = [];

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this->setName( 'printusers' );
		$this->setDescription(
			'Recursively search --src for entities.xml files and print a usermap CSV'
		);
		parent::configure();
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		// If --dest was given explicitly, treat it as an output file name;
		// otherwise print the CSV to stdout.
		if ( $input->hasParameterOption( '--dest' ) ) {
			$destFile = $input->getOption( 'dest' );
			$this->out = fopen( $destFile, 'w' );
			if ( $this->out === false ) {
				$output->writeln( "<error>Could not open '$destFile' for writing</error>" );
				return self::FAILURE;
			}
		} else {
			$this->out = fopen( 'php://stdout', 'w' );
		}
		fputcsv( $this->out, [ 'confluence-userkey', 'confluence-username', 'wiki-username' ] );

		// Keep stdout as pure CSV; send the base class' progress output to stderr.
		$errOutput = new StreamOutput( fopen( 'php://stderr', 'w' ), $output->getVerbosity() );

		try {
			return parent::execute( $input, $errOutput );
		} finally {
			fclose( $this->out );
		}
	}

	/**
	 * Filter to entities.xml files only, like Analyze::makeFileList does.
	 *
	 * @return void
	 */
	protected function makeFileList(): void {
		parent::makeFileList();

		$this->files = array_filter(
			$this->files,
			static function ( $file ) {
				return $file->getFilename() === 'entities.xml';
			}
		);
	}

	/**
	 * @return array
	 */
	protected function makeExtensionWhitelist(): array {
		return [ 'xml' ];
	}

	/**
	 * @param SplFileInfo $file
	 *
	 * @return bool
	 */
	protected function processFile( SplFileInfo $file ): bool {
		$this->output->writeln( "Reading users from '{$file->getPathname()}'", OutputInterface::VERBOSITY_VERBOSE );

		$xmlReader = new XMLReader();
		$xmlReader->open( $file->getPathname() );

		while ( $xmlReader->read() ) {
			if ( $xmlReader->nodeType !== XMLReader::ELEMENT || $xmlReader->name !== 'object' ) {
				continue;
			}
			if ( $xmlReader->getAttribute( 'class' ) !== 'ConfluenceUserImpl' ) {
				continue;
			}

			$this->printUser( $xmlReader );
		}

		$xmlReader->close();

		return true;
	}

	/**
	 * @param XMLReader $xmlReader Positioned on a `ConfluenceUserImpl` object element
	 *
	 * @return void
	 */
	private function printUser( XMLReader $xmlReader ): void {
		$userKey = '';
		$lowerName = '';
		$node = $xmlReader->expand();
		foreach ( $node->childNodes as $child ) {
			if ( $child->nodeName === 'id' && $child->getAttribute( 'name' ) === 'key' ) {
				$userKey = trim( $child->textContent );
			} elseif ( $child->nodeName === 'property' && $child->getAttribute( 'name' ) === 'lowerName' ) {
				$lowerName = trim( $child->textContent );
			}
		}

		if ( $userKey === '' || isset( $this->seen[$userKey] ) ) {
			return;
		}
		$this->seen[$userKey] = true;

		fputcsv( $this->out, [ $userKey, $lowerName, Users::makeMWUserName( $lowerName ) ] );
	}
}
