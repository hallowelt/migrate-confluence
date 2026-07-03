<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateConfig extends Command {

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this->setName( 'validateconfig' );
		$this->setDefinition( new InputDefinition( [
				new InputOption(
					'config',
					null,
					InputOption::VALUE_REQUIRED,
					'Specifies the path to the config yaml file'
				)
			] )
		);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$filename = $input->getOption( 'config' );

		$configOptionHelper = new ConfigOptionHelper( $filename );
		$validationError = $configOptionHelper->validateFile();

		if ( $validationError !== null ) {
			$output->writeln( $validationError );
			exit( 1 );
		}

		$configOptionHelper->showConfig();

		return Command::SUCCESS;
	}

}
