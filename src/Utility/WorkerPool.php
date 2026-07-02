<?php

namespace HalloWelt\MigrateConfluence\Utility;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Spawns and monitors a pool of worker sub-processes, forwarding their stdout/stderr
 * to the console and dispatching DB-pipe lines via a caller-supplied callback.
 */
class WorkerPool {

	/**
	 * @param OutputInterface $output
	 * @param callable(string):void $onDbLine Called for every line received on the DB pipe
	 * @param int $pollInterval usleep() microseconds between poll iterations
	 */
	public function __construct(
		private OutputInterface $output,
		private $onDbLine,
		private int $pollInterval = 100000
	) {
	}

	/**
	 * Build the base command array for spawning a worker by reusing the current
	 * process's argv, stripping any existing --worker option.
	 *
	 * @return array
	 */
	public static function buildBaseCommand(): array {
		$argv = $_SERVER['argv'];
		$cmd = [ PHP_BINARY, $argv[0] ];

		for ( $i = 1; $i < count( $argv ); $i++ ) {
			$arg = $argv[$i];
			if ( preg_match( '#^--worker(=.*)?$#', $arg ) ) {
				if ( $arg === '--worker' ) {
					$i++;
				}
				continue;
			}
			$cmd[] = $arg;
		}

		return $cmd;
	}

	/**
	 * Spawn $workers child processes and supervise them until all finish.
	 *
	 * @param array $baseCmd Base command returned by buildBaseCommand()
	 * @param int $workers Number of workers to spawn
	 * @return int Command::SUCCESS or Command::FAILURE
	 */
	public function run( array $baseCmd, int $workers ): int {
		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
			PipeToDB::FILE_DESCRIPTOR => [ 'pipe', 'w' ],
		];

		$processes = [];
		$pipes = [];
		$DBWritePipes = [];

		for ( $i = 0; $i < $workers; $i++ ) {
			$cmd = array_merge( $baseCmd, [ '--worker=' . $i ] );
			$cmdString = implode( ' ', array_map( 'escapeshellarg', $cmd ) );
			$this->output->writeln( "Starting worker {$i}: <comment>{$cmdString}</comment>" );
			// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.proc_open
			$proc = proc_open( $cmdString, $descriptors, $workerPipes );
			if ( $proc === false ) {
				$this->output->writeln( "<error>Failed to start worker {$i}.</error>" );
				return Command::FAILURE;
			}
			stream_set_blocking( $workerPipes[1], false );
			stream_set_blocking( $workerPipes[2], false );
			stream_set_blocking( $workerPipes[PipeToDB::FILE_DESCRIPTOR], false );
			fclose( $workerPipes[0] );
			$processes[$i] = $proc;
			$pipes[$i] = [ $workerPipes[1], $workerPipes[2] ];
			$DBWritePipes[$i] = $workerPipes[PipeToDB::FILE_DESCRIPTOR];
		}

		$exitCodes = array_fill( 0, $workers, null );
		while ( count( array_filter( $processes ) ) > 0 ) {
			foreach ( $processes as $i => $proc ) {
				if ( $proc === null ) {
					continue;
				}
				foreach ( $pipes[$i] as $pipe ) {
					$line = fgets( $pipe );
					while ( $line !== false ) {
						$this->output->write( "[Worker {$i}] " . $line );
						$line = fgets( $pipe );
					}
				}
				$line = fgets( $DBWritePipes[$i] );
				while ( $line !== false ) {
					( $this->onDbLine )( $line );
					$line = fgets( $DBWritePipes[$i] );
				}
				$status = proc_get_status( $proc );
				if ( !$status['running'] ) {
					foreach ( $pipes[$i] as $pipe ) {
						// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
						while ( ( $line = fgets( $pipe ) ) !== false ) {
							$this->output->write( "[Worker {$i}] " . $line );
						}
						fclose( $pipe );
					}
					$line = fgets( $DBWritePipes[$i] );
					while ( $line !== false ) {
						( $this->onDbLine )( $line );
						$line = fgets( $DBWritePipes[$i] );
					}
					fclose( $DBWritePipes[$i] );
					$exitCodes[$i] = proc_close( $proc );
					$processes[$i] = null;
					$this->output->writeln( "Worker {$i} finished with exit code {$exitCodes[$i]}." );
				}
			}
			usleep( $this->pollInterval );
		}

		$failed = array_filter( $exitCodes, static function ( $code ) {
			return $code !== Command::SUCCESS;
		} );

		if ( !empty( $failed ) ) {
			$failedList = implode( ', ', array_keys( $failed ) );
			$this->output->writeln( "<error>One or more workers failed: workers {$failedList}</error>" );
			return Command::FAILURE;
		}

		$this->output->writeln( '<info>All workers completed successfully.</info>' );
		return Command::SUCCESS;
	}
}
