<?php

namespace HalloWelt\MigrateConfluence\Tests\Database\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class PipeChannelTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel::FILE_DESCRIPTOR
	 */
	public function testAssertValidPipeDescriptor(): void {
		$this->assertIsInt( PipeChannel::FILE_DESCRIPTOR );
		$this->assertGreaterThan( 2, PipeChannel::FILE_DESCRIPTOR,
			'make sure that the file descriptor does not conflict with standard input/output/error' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel::send
	 */
	public function testSendMessageToPipe(): void {
		$pipe = fopen( 'php://temp', 'r+' );
		$pipeChannel = new PipeChannel( $pipe );

		$pipeChannel->send( [ 'test', 1, [ 'list' ] ] );

		rewind( $pipe );
		$this->assertSame( '["test",1,["list"]]' . "\n", stream_get_contents( $pipe ),
			'test for JSON encoded message including important trailing newline' );
		fclose( $pipe );
	}

}
