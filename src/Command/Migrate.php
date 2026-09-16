<?php

namespace HalloWelt\MigrateConfluence\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends Command {

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this->setName( 'migrate' );
		$this->setDescription( 'Runs analyze, extract, convert and compose in order.' );
		$this->addOption(
			'src',
			null,
			InputOption::VALUE_REQUIRED,
			'Specifies the path to the input file or directory'
		);
		$this->addOption(
			'dest',
			null,
			InputOption::VALUE_REQUIRED,
			'Specifies the path to the output workspace directory'
		);
		$this->addOption(
			'config',
			null,
			InputOption::VALUE_REQUIRED,
			'Specifies the path to the config yaml file'
		);
		$this->addOption(
			'wikis',
			null,
			InputOption::VALUE_REQUIRED,
			'Specifies the path to the csv file containing interwiki configuration'
		);
		$this->addOption(
			'workers',
			null,
			InputOption::VALUE_REQUIRED,
			'Number of parallel worker processes to spawn for supported steps',
			1
		);
		$this->addOption(
			'cmd',
			null,
			InputOption::VALUE_REQUIRED,
			'Shell command to run after all migration steps finish successfully'
		);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$src = (string)$input->getOption( 'src' );
		$dest = (string)$input->getOption( 'dest' );

		if ( $src === '' ) {
			$output->writeln( '<error>Missing required option --src</error>' );
			return Command::FAILURE;
		}

		if ( $dest === '' ) {
			$output->writeln( '<error>Missing required option --dest</error>' );
			return Command::FAILURE;
		}

		$commonOptions = [
			'config' => $this->getOptionalString( $input, 'config' ),
			'workers' => (string)$input->getOption( 'workers' ),
		];

		$steps = [
			[
				'name' => 'analyze',
				'src' => $src,
				'dest' => $dest,
				'options' => [
					'config' => $commonOptions['config'],
					'wikis' => $this->getOptionalString( $input, 'wikis' ),
					'workers' => $commonOptions['workers'],
				]
			],
			[
				'name' => 'extract',
				'src' => $src,
				'dest' => $dest,
				'options' => [
					'config' => $commonOptions['config'],
				]
			],
			[
				'name' => 'convert',
				'src' => $dest,
				'dest' => $dest,
				'options' => [
					'config' => $commonOptions['config'],
					'workers' => $commonOptions['workers'],
				]
			],
			[
				'name' => 'compose',
				'src' => $dest,
				'dest' => $dest,
				'options' => [
					'config' => $commonOptions['config'],
				]
			],
		];

		foreach ( $steps as $step ) {
			$status = $this->runStep( $step['name'], $step['src'], $step['dest'], $step['options'], $output );
			if ( $status !== Command::SUCCESS ) {
				$output->writeln( "<error>Step '{$step['name']}' failed with exit code $status</error>" );
				return $status;
			}
		}

		$cmd = $this->getOptionalString( $input, 'cmd' );
		if ( $cmd !== null ) {
			$output->writeln( '<info>Running finish command</info>' );
			$status = $this->runShellCommand( $cmd );
			if ( $status !== Command::SUCCESS ) {
				$output->writeln( "<error>Finish command failed with exit code $status</error>" );
				return $status;
			}
		}

		$output->writeln( '<info>Migration completed successfully.</info>' );
		return Command::SUCCESS;
	}

	/**
	 * @param InputInterface $input
	 * @param string $name
	 * @return string|null
	 */
	private function getOptionalString( InputInterface $input, string $name ): ?string {
		$value = $input->getOption( $name );
		if ( $value === null || $value === '' ) {
			return null;
		}

		return (string)$value;
	}

	/**
	 * @param string $name
	 * @param string $src
	 * @param string $dest
	 * @param array $options
	 * @param OutputInterface $output
	 * @return int
	 */
	private function runStep( string $name, string $src, string $dest, array $options, OutputInterface $output ): int {
		$output->writeln( "<info>Starting step '$name'</info>" );

		$command = [
			PHP_BINARY,
			$this->getConsoleScript(),
			$name,
			'--src=' . $src,
			'--dest=' . $dest,
		];

		foreach ( $options as $optionName => $optionValue ) {
			if ( $optionValue === null || $optionValue === '' ) {
				continue;
			}
			$command[] = '--' . $optionName . '=' . $optionValue;
		}

		return $this->runShellCommand( $this->escapeCommand( $command ) );
	}

	/**
	 * @return string
	 */
	private function getConsoleScript(): string {
		return $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['argv'][0] ?? __DIR__ . '/../../bin/migrate-confluence';
	}

	/**
	 * @param string[] $parts
	 * @return string
	 */
	private function escapeCommand( array $parts ): string {
		return implode( ' ', array_map( 'escapeshellarg', $parts ) );
	}

	/**
	 * @param string $command
	 * @return int
	 */
	private function runShellCommand( string $command ): int {
		$status = Command::SUCCESS;
		passthru( $command, $status );

		return $status;
	}
}