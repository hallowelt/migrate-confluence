<?php

namespace HalloWelt\MigrateConfluence\Command;

use DOMDocument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckResult extends Command {

	/**
	 * @var Input\InputInterface
	 */
	private $input = null;

	/**
	 * @var OutputInterface
	 */
	private $output = null;

	/**
	 * @var string
	 */
	private $workspaceDir = '';

	/**
	 * @var array
	 */
	private $titles = [];

	protected function configure() {
		$this->setName( 'checkresult' );
		$this
			->setDefinition( new InputDefinition( [
				new InputOption(
					'src',
					null,
					InputOption::VALUE_REQUIRED,
					'Specifies the path to the result directory'
				)
			] ) );

		return parent::configure();
	}

	/**
	 * @param Input\InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->input = $input;
		$this->output = $output;

		$this->workspaceDir = realpath( $this->input->getOption( 'src' ) );
		$dom = new DOMDocument();
		$dom->load( "{$this->workspaceDir}/output.xml" );

		$titleEls = $dom->getElementsByTagName( 'title' );
		foreach ( $titleEls as $titleEl ) {
			$this->titles[] = $titleEl->nodeValue;
		}

		$textEls = $dom->getElementsByTagName( 'text' );
		foreach ( $textEls as $textEl ) {
			$wikiText = $textEl->nodeValue;

			preg_replace_callback( '#\[\[(.*?)\]\]#', function ( $matches ) {
				$this->checkLink( $matches[1] );
			}, $wikiText );
		}

		$this->createReport();
	}

	/**
	 * @param string $targetFileName
	 * @return bool
	 */
	private function fileAvailable( $targetFileName ) {
		return file_exists( "{$this->workspaceDir}/images/$targetFileName" );
	}

	/**
	 * @param string $targetPageName
	 * @return bool
	 */
	private function pageAvailable( $targetPageName ) {
		return in_array( $targetPageName, $this->titles );
	}

	/**
	 * @var array
	 */
	private $brokenPageLinks = [];

	/**
	 * @var array
	 */
	private $brokenFileLinks = [];

	/**
	 * @var int
	 */
	private $numberOfPageLinks = 0;

	/**
	 * @var int
	 */
	private $numberOfFileLinks = 0;

	/**
	 * @param string $linkDesc
	 * @return void
	 */
	private function checkLink( $linkDesc ) {
		$linkDesc = html_entity_decode( $linkDesc );
		$linkDescParts = explode( '|', $linkDesc );
		$linkTarget = $linkDescParts[0];

		$targetParts = explode( ':', $linkTarget, 2 );
		$maybeNamespacePrefix = '';
		$maybePagename = $linkTarget;
		if ( count( $targetParts ) === 2 ) {
			$maybeNamespacePrefix = $targetParts[0];
			$maybePagename = $targetParts[1];
		}

		if ( $maybeNamespacePrefix === 'File' ) {
			$this->numberOfFileLinks++;
			if ( !$this->fileAvailable( $maybePagename ) ) {
				$this->brokenFileLinks[] = $maybePagename;
			}
			return;
		}

		if ( $maybeNamespacePrefix === 'Media' ) {
			$this->numberOfFileLinks++;
			if ( !$this->fileAvailable( $maybePagename ) ) {
				$this->brokenFileLinks[] = $maybePagename;
			}
			return;
		}

		// Category pages are not part of the migration process. Those links are broken by default.
		if ( $maybeNamespacePrefix === 'Category' ) {
			return;
		}

		$this->numberOfPageLinks++;
		if ( !$this->pageAvailable( $linkTarget ) ) {
			$this->brokenPageLinks[] = $linkTarget;
		}
	}

	private function createReport() {
		$numberOfBrokenPageLinks = count( $this->brokenPageLinks );
		$this->output->writeln( "Page links (broken/total): {$numberOfBrokenPageLinks}/{$this->numberOfPageLinks}" );
		$numberOfBrokenFileLinks = count( $this->brokenFileLinks );
		$this->output->writeln( "File links (broken/total): {$numberOfBrokenFileLinks}/{$this->numberOfFileLinks}" );

		if ( !empty( $this->brokenPageLinks ) ) {
			$this->output->writeln( "\nBroken page links:" );
			foreach ( $this->brokenPageLinks as $brokenPageName ) {
				$this->output->writeln( "\t$brokenPageName" );
			}
		}

		if ( !empty( $this->brokenFileLinks ) ) {
			$this->output->writeln( "\nBroken file links:" );
			foreach ( $this->brokenFileLinks as $brokenFileName ) {
				$this->output->writeln( "\t$brokenFileName" );
			}
		}
		$this->output->writeln( "\nCheck done." );
	}
}
