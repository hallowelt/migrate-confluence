<?php

namespace HalloWelt\MigrateConfluence\Tests\Database\DataWriter;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeLogEntryWriter;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeReplay;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class PipeLogEntryWriterTest extends TestCase {

	protected function tearDown(): void {
		Log::setDatabaseHandler( null );
		Log::setStep( '' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\DataWriter\PipeLogEntryWriter::addLogEntry
	 */
	public function testWorkerLogEntryReachesHostDbViaPipe(): void {
		$pipe = fopen( 'php://temp', 'r+' );
		Log::setStep( 'analyze' );
		Log::setDatabaseHandler( new PipeLogEntryWriter( new PipeChannel( $pipe ) ) );

		Log::to( 'db' )->critical( 'worker says boom' );

		rewind( $pipe );
		$wire = stream_get_contents( $pipe );
		fclose( $pipe );

		$db = ( new WorkspaceDbMock() )->createEmpty();
		( new PipeReplay( new ExtractorDirectDataWriter( $db ) ) )->feed( $wire );

		$entries = $db->getLogEntriesForStep( 'analyze', 'critical' );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'worker says boom', $entries[0]['text'] );
		$this->assertStringContainsString( __CLASS__, $entries[0]['caller'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::addLogEntry
	 */
	public function testDirectDbHandlerWritesEntry(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		Log::setStep( 'extract' );
		Log::setDatabaseHandler( $db );

		Log::to( 'db' )->warning( 'direct' );

		$entries = $db->getLogEntriesForStep( 'extract', 'warning' );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'direct', $entries[0]['text'] );
	}
}
