<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerPool {

	private const int SELECT_TIMEOUT_SEC = 1;
	private const int CHUNK = 65536;

	public function __construct(
		private OutputInterface $output,
		private IDataWriter $dbTarget
	) {
	}

	/**
	 * @param string[] $baseCommand PHP binary + script + args, without --worker
	 * @param int $workers
	 * @return int Command::SUCCESS | Command::FAILURE
	 */
	public function run( array $baseCommand, int $workers ): int {
		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
			PipeChannel::FILE_DESCRIPTOR => [ 'pipe', 'w' ],
		];

		$procs = [];       // i => resource
		$replays = [];     // i => PipeReplay (own buffer per worker)
		$exit = [];        // i => int|null
		$openPipes = [];   // i => count of pipes still open
		$streams = [];     // (int)$stream => [ i, kind, resource ]

		for ( $i = 0; $i < $workers; $i++ ) {
			$pipes = [];
			// Array form: proc_open bypasses the shell — no escapeshellarg needed.
			// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.proc_open
			$proc = proc_open( [ ...$baseCommand, '--worker=' . $i ], $descriptors, $pipes );
			if ( !is_resource( $proc ) ) {
				$this->output->writeln( "<error>Failed to start worker {$i}.</error>" );
				$this->terminate( $procs );
				return Command::FAILURE;
			}
			$this->output->writeln( "Starting worker {$i}" );

			fclose( $pipes[0] );
			foreach ( [ 1, 2, PipeChannel::FILE_DESCRIPTOR ] as $fd ) {
				stream_set_blocking( $pipes[$fd], false );
			}

			$procs[$i] = $proc;
			$replays[$i] = new PipeReplay( $this->dbTarget );
			$exit[$i] = null;
			$openPipes[$i] = 3;

			$streams[ (int)$pipes[1] ] = [ $i, 'out', $pipes[1] ];
			$streams[ (int)$pipes[2] ] = [ $i, 'err', $pipes[2] ];
			$streams[ (int)$pipes[ PipeChannel::FILE_DESCRIPTOR ] ]
				= [ $i, 'db', $pipes[ PipeChannel::FILE_DESCRIPTOR ] ];
		}

		// Pump until every pipe hit EOF. stream_select blocks until *some* pipe is
		// readable — this replaces the usleep() busy-poll of the old loop.
		while ( $streams !== [] ) {
			$read = array_map( static fn ( array $s ) => $s[2], $streams );
			$write = null;
			$except = null;

			$ready = @stream_select( $read, $write, $except, self::SELECT_TIMEOUT_SEC );
			if ( $ready === false ) {
				// Interrupted syscall (EINTR): retry.
				continue;
			}

			foreach ( $read as $stream ) {
				$key = (int)$stream;
				[ $i, $kind ] = $streams[$key];

				$chunk = fread( $stream, self::CHUNK );
				if ( $chunk !== false && $chunk !== '' ) {
					$this->consume( $i, $kind, $chunk, $replays[$i] );
				}

				if ( feof( $stream ) ) {
					if ( $kind === 'db' ) {
						// Flush a trailing record without newline, if any.
						$replays[$i]->finish();
					}
					fclose( $stream );
					unset( $streams[$key] );
					if ( --$openPipes[$i] === 0 ) {
						$exit[$i] = proc_close( $procs[$i] );
						$this->output->writeln( "Worker {$i} finished with exit code {$exit[$i]}." );
					}
				}
			}
		}

		$failed = array_keys(
			array_filter( $exit, static fn ( $code ) => $code !== Command::SUCCESS )
		);
		if ( $failed !== [] ) {
			$this->output->writeln( '<error>Workers failed: ' . implode( ', ', $failed ) . '</error>' );
			return Command::FAILURE;
		}

		$this->output->writeln( '<info>All workers completed successfully.</info>' );
		return Command::SUCCESS;
	}

	/**
	 * Rebuild the command (PHP binary + script + current args) without any
	 * pre-existing --worker flag, so children inherit everything else unchanged.
	 * Keeps --workers so children know the total count for their slice.
	 *
	 * @return string[]
	 */
	public static function baseCommandFromArgv(): array {
		$argv = $_SERVER['argv'];
		$cmd = [ PHP_BINARY, $argv[0] ];

		for ( $i = 1, $n = count( $argv ); $i < $n; $i++ ) {
			$arg = $argv[$i];
			if ( preg_match( '#^--worker(=.*)?$#', $arg ) ) {
				// Skip a separate value token too: "--worker 3".
				if ( $arg === '--worker' ) {
					$i++;
				}
				continue;
			}
			$cmd[] = $arg;
		}

		return $cmd;
	}

	private function consume( int $i, string $kind, string $chunk, PipeReplay $replay ): void {
		if ( $kind === 'db' ) {
			$replay->feed( $chunk );
			return;
		}
		// stdout / stderr: forward line-prefixed. rtrim avoids a trailing blank line.
		foreach ( explode( "\n", rtrim( $chunk, "\n" ) ) as $line ) {
			$this->output->writeln( "[Worker {$i}] " . $line );
		}
	}

	/** @param array<int,resource> $procs already-started children to clean up */
	private function terminate( array $procs ): void {
		foreach ( $procs as $proc ) {
			if ( is_resource( $proc ) ) {
				proc_terminate( $proc );
				proc_close( $proc );
			}
		}
	}
}
