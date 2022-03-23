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
		foreach( $titleEls as $titleEl ) {
			$this->titles[] = $titleEl->nodeValue;
		}

		$textEls = $dom->getElementsByTagName( 'text' );
		foreach( $textEls as $textEl ) {
			$wikiText = $textEl->nodeValue;

			preg_replace_callback( '#\[\[(.*?)\]\]#', function( $matches ) {
				$this->checkLink( $matches[1] );
			}, $wikiText );
		}

	}

	private function fileAvailable( $targetFileName ) {
		return file_exists( "{$this->workspaceDir}/images/$targetFileName" );
	}

	private function pageAvailable( $targetPageName ) {
		return in_array( $targetPageName, $this->titles );
	}

	private function checkLink( $linkDesc ) {
		$linkDescParts = explode( '|', $linkDesc );
		$linkTarget = $linkDescParts[0];
		var_dump( $linkTarget );
	}
}