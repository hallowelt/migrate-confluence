<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * send data from worker processes to main process in order to be written to the database
 */
class PipeToDB {

	public const int FILE_DESCRIPTOR = 3;

	/** @var resource|false */
	private $pipe = false;

	/** @var bool */
	private $closePipe = false;

	/**
	 * @param resource|false $pipe
	 * @throws \RuntimeException
	 */
	public function __construct( $pipe = false ) {
		if ( $pipe ) {
			$this->pipe = $pipe;
		} else {
			$this->pipe = fopen( 'php://fd/' . self::FILE_DESCRIPTOR, 'w' );
			if ( $this->pipe === false ) {
				throw new \RuntimeException( 'Failed to open pipe from worker' );
			}
			$this->closePipe = true;
		}
	}

	/**
	 */
	public function __destruct() {
		if ( $this->pipe !== false && $this->closePipe ) {
			fclose( $this->pipe );
		}
	}

	/**
	 * @param mixed ...$args the data to send to the DB
	 */
	public function send( ...$args ): void {
		$data = json_encode( $args );
		fwrite( $this->pipe, $data . "\n" );
	}

}
