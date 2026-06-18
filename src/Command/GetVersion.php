<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetVersion extends Command {

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this
			->setName( 'version' )
			->setDescription( 'Print version information' );
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$version = Version::getVersion();
		if ( !$version ) {
			$output->getErrorOutput()->writeln( 'Version information not found in composer.json' );
			return Command::FAILURE;
		}

		if ( $output instanceof ConsoleOutputInterface ) {
			$output->writeln( sprintf( $output->isVerbose() ? "tool: %s\nphp:  %s" : '%s', $version, PHP_VERSION ) );
		} else {
			$output->write( $version );
		}

		return Command::SUCCESS;
	}

}
