<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * report all macro usage
 */
class ReportSupport extends Command {

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this->setName( 'reportsupport' );
		$this->setDescription(
			'Report all macro usage'
		);
		parent::configure();

		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'json', null, InputOption::VALUE_NONE, 'print the output as JSON'
			)
		);
	}

	/**
	 * report the tool version and macro support
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$basepath = dirname( __DIR__ ) . '/Converter/Processor';
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $basepath ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		$support = [
			'version' => Version::getVersion(),
			'macros' => [],
		];
		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			if ( $fileObj->getExtension() !== 'php' ) {
				continue;
			}
			$classname = $this->getClassFromFilename( $fileObj->getPathname() );
			try {
				$reflection = new \ReflectionClass( $classname );
				$macroName = $reflection->hasConstant( 'MACRO_NAME' ) ?
					$reflection->getConstant( 'MACRO_NAME' ) : null;
				$supportLevel = $reflection->hasConstant( 'SUPPORT_LEVEL' ) ?
					$reflection->getConstant( 'SUPPORT_LEVEL' ) : null;
				$requiredExtensions = $reflection->hasConstant( 'REQUIRED_EXTENSIONS' ) ?
					$reflection->getConstant( 'REQUIRED_EXTENSIONS' ) : null;
			} catch ( \ReflectionException $e ) {
				// handle the exception if the class does not exist or cannot be instantiated
				$output->writeln( sprintf( 'Failed to instantiate class %s: %s', $classname, $e->getMessage() ) );
				continue;
			}
			if ( $macroName ) {
				/* use the highest reported support level (might be different for output profiles) */
				if (
						array_key_exists( $macroName, $support['macros'] ) &&
						$support['macros'][$macroName]['supportLevel'] >= $supportLevel ) {
					continue;
				}
				if ( $supportLevel === false ) {
					$supportLevel = MacroInfo::SUPPORT_LEVEL_UNKNOWN;
				}
				$support['macros'][$macroName] = [
					'supportLevel' => $supportLevel,
				];
				if ( $requiredExtensions ) {
					$support['macros'][$macroName]['requiredExtensions'] = $requiredExtensions;
				}
			}
		}
		ksort( $support['macros'] );
		$output->writeln( $this->getOutput( $support, $input ) );
		return Command::SUCCESS;
	}

	/**
	 * return either JSON or a plain text report based on the flag `--json`
	 */
	private function getOutput( array $support, InputInterface $input ): string {
		if ( $input->getOption( 'json' ) ) {
			return json_encode( $support, JSON_PRETTY_PRINT );
		}

		$rowTpl = "| %-20s | %-10s | %-50s |\n";
		$rowSplit = sprintf( $rowTpl, str_repeat( '-', 20 ), str_repeat( '-', 10 ), str_repeat( '-', 50 ) );
		$str = sprintf( "Version: %s\nMacros (%d):\n", $support['version'], count( $support['macros'] ) );
		$str .= $rowSplit;
		$str .= sprintf( $rowTpl, 'macro name', 'support', 'required extensions' );
		$str .= $rowSplit;
		foreach ( $support['macros'] as $macroName => $macroInfo ) {
			$str .= sprintf( $rowTpl, $macroName, match ( $macroInfo['supportLevel'] ) {
				MacroInfo::SUPPORT_LEVEL_UNKNOWN => 'unknown',
				MacroInfo::SUPPORT_LEVEL_FULLY => 'full',
				MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY => 'almost full',
				MacroInfo::SUPPORT_LEVEL_PARTIALLY => 'partial',
				MacroInfo::SUPPORT_LEVEL_SKETCHY => 'sketchy',
				MacroInfo::SUPPORT_LEVEL_NONE => 'no',
				default => 'Unknown',
			}, isset( $macroInfo['requiredExtensions'] ) ? implode( ', ', $macroInfo['requiredExtensions'] ) : '' );
		}
		$str .= $rowSplit;
		return $str;
	}

	/**
	 * get the expected class name from a file name
	 */
	private function getClassFromFilename( string $filename ): string {
		$path = str_replace( dirname( __DIR__ ), 'HalloWelt\\MigrateConfluence', $filename );
		$path = str_replace( '/', '\\', $path );
		$path = str_replace( '.php', '', $path );
		return $path;
	}
}
