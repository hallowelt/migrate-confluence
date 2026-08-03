<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

use RuntimeException;

/**
 * Child-side transport. Serialises one method call per line and writes it to the
 * DB pipe (fd 3).
 */
class PipeChannel {

	public const int FILE_DESCRIPTOR = 3;

	/** @var resource */
	private $stream;
	private bool $ownsStream = false;

	/** @param resource|null $stream */
	public function __construct( $stream = null ) {
		if ( $stream !== null ) {
			$this->stream = $stream;
			return;
		}
		$opened = fopen( 'php://fd/' . self::FILE_DESCRIPTOR, 'w' );
		if ( $opened === false ) {
			throw new RuntimeException( 'Failed to open DB pipe on fd ' . self::FILE_DESCRIPTOR );
		}
		$this->stream = $opened;
		$this->ownsStream = true;
	}

	/** @param array $message [ methodName, ...args ] */
	public function send( array $message ): void {
		// JSON_INVALID_UTF8_SUBSTITUTE: body content from Confluence is not always clean
		// UTF-8. Without it json_encode() returns false and the record is silently lost.
		$json = json_encode(
			$message,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
		);
		if ( $json === false ) {
			throw new RuntimeException( 'Failed to encode pipe message: ' . json_last_error_msg() );
		}
		fwrite( $this->stream, $json . "\n" );
	}

	public function __destruct() {
		if ( $this->ownsStream ) {
			fclose( $this->stream );
			$this->ownsStream = false;
		}
	}
}
