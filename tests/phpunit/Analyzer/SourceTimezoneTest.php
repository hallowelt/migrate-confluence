<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer;

use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ProcessorBase;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer::analyze
 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ProcessorBase::buildTimestamp
 */
class SourceTimezoneTest extends TestCase {

	private string $tempDir;

	protected function setUp(): void {
		$this->tempDir = dirname( __DIR__, 3 ) . '/.tmp/source-timezone-test-' . uniqid();
		mkdir( $this->tempDir, 0777, true );
		copy( __DIR__ . '/Processor/Page/page.xml', $this->tempDir . '/entities.xml' );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->tempDir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->tempDir );
	}

	/**
	 * @param array $config
	 * @param string $descriptor
	 * @return string
	 */
	private function analyzeAndGetPageTimestamp( array $config, string $descriptor ): string {
		if ( $descriptor !== '' ) {
			file_put_contents( $this->tempDir . '/exportDescriptor.properties', $descriptor );
		}
		$workspaceDB = ( new WorkspaceDbMock() )->createEmpty();
		$analyzer = new ConfluenceAnalyzer(
			new AnalyzerDirectDataWriter( $workspaceDB ),
			new NullOutput(),
			new MigrationConfig( $config ),
			new WikisConfig( $workspaceDB )
		);
		$analyzer->analyze( new SplFileInfo( $this->tempDir . '/entities.xml' ) );

		return $workspaceDB->getPages()[0]['revision_timestamp'];
	}

	public function testConfigOptionWinsOverDescriptor(): void {
		// 13:14:15 in July in Berlin is CEST (UTC+2)
		$this->assertSame(
			'20260708111415',
			$this->analyzeAndGetPageTimestamp(
				[ 'source-timezone' => 'Europe/Berlin' ],
				"source=cloud\ntimezoneId=UTC\n"
			)
		);
	}

	public function testDescriptorTimezoneIsUsedWithoutConfig(): void {
		$this->assertSame(
			'20260708181415',
			$this->analyzeAndGetPageTimestamp( [], "source=cloud\ntimezoneId=America/Chicago\n" )
		);
	}

	public function testFallsBackToUtc(): void {
		$this->assertSame(
			'20260708131415',
			$this->analyzeAndGetPageTimestamp( [], "source=server\n" )
		);
	}

	public function testInvalidConfiguredTimezoneAborts(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->analyzeAndGetPageTimestamp( [ 'source-timezone' => 'Nowhere/Land' ], '' );
	}

	public function testEmptyOrInvalidDateYieldsEmptyTimestamp(): void {
		$processor = new class extends ProcessorBase {
			public function build( string $date ): string {
				return $this->buildTimestamp( $date );
			}
		};
		$this->assertSame( '', $processor->build( '' ) );
		$this->assertSame( '', $processor->build( '  ' ) );
		$this->assertSame( '', $processor->build( 'not a date' ) );
	}
}
