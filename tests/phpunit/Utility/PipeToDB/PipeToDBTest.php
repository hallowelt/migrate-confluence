<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\PipeToDB;

use HalloWelt\MigrateConfluence\Utility\PipeToDB;
use PHPUnit\Framework\TestCase;

class PipeToDBTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PipeToDB::PIPE_DESCRIPTOR
	 */
	public function testAssertValidPipeDescriptor(): void {
		$this->assertIsInt( PipeToDB::FILE_DESCRIPTOR );
		$this->assertGreaterThan( 2, PipeToDB::FILE_DESCRIPTOR,
			'make sure that the file descriptor does not conflict with standard input/output/error' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PipeToDB::send
	 */
	public function testSendMessageToPipe(): void {
		$pipe = fopen( 'php://temp', 'r+' );
		$pipeToDB = new PipeToDB( $pipe );

		$pipeToDB->send( 'test', 1, [ 'list' ] );

		rewind( $pipe );
		$this->assertSame( '["test",1,["list"]]' . "\n", stream_get_contents( $pipe ),
			'test for JSON encoded message including important trailing newline' );
		fclose( $pipe );
	}

}
