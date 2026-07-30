<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

/**
 * Parent-side replay. Reassembles line-framed messages from a worker's DB pipe
 * and replays each one against a Direct data writer, so WorkspaceDB is touched
 * in exactly one place.
 */
class PipeReplay {

	private string $buffer = '';

	public function __construct( private IDataWriter $target ) {
	}

	/** Feed a raw chunk read from the pipe. Replays every *complete* line in it. */
	public function feed( string $chunk ): void {
		$this->buffer .= $chunk;
		while ( ( $nl = strpos( $this->buffer, "\n" ) ) !== false ) {
			$line = substr( $this->buffer, 0, $nl );
			$this->buffer = substr( $this->buffer, $nl + 1 );
			$this->replayLine( $line );
		}
	}

	/** Call once after the child closed, to flush a tail without trailing newline. */
	public function finish(): void {
		if ( $this->buffer !== '' ) {
			$this->replayLine( $this->buffer );
			$this->buffer = '';
		}
	}

	private function replayLine( string $line ): void {
		$line = trim( $line );
		if ( $line === '' ) {
			return;
		}
		$data = json_decode( $line, true );
		if ( !is_array( $data ) || $data === [] ) {
			$this->logInvalid( $line );

			return;
		}
		$method = array_shift( $data );
		// Guard: $method comes over the wire; refuse anything not on the contract.
		if ( !is_string( $method ) || !method_exists( $this->target, $method ) ) {
			$this->logInvalid( $line );

			return;
		}
		$this->target->{$method}( ...$data );
	}

	private function logInvalid( string $line ): void {
		$this->target->addLogEntry( 'error', 'replay.invalid-worker-output', __CLASS__, $line );
	}
}
