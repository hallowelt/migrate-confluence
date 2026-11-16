<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\Hooks;

use HalloWelt\MigrateConfluence\Utility\Hooks\AnalyzerHooks;
use HalloWelt\MigrateConfluence\Utility\Hooks\HookHandler;
use HalloWelt\MigrateConfluence\Utility\Hooks\Hooks;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SplFileInfo;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\Hooks\Hooks
 * @covers \HalloWelt\MigrateConfluence\Utility\Hooks\AnalyzerHooks
 */
class HooksTest extends TestCase {

	protected function tearDown(): void {
		$prop = ( new ReflectionClass( HookHandler::class ) )->getProperty( 'handlers' );
		$prop->setValue( null, [ 'filters' => [], 'actions' => [] ] );
	}

	public function testHookNameIsKebabCaseOfMethodName(): void {
		$this->assertSame( 'convert-content-pre', Hooks::hookName( 'convertContentPre' ) );
		$this->assertSame( 'include-import-file', Hooks::hookName( 'includeImportFile' ) );
	}

	public function testHookMethodCallsHandlerRegisteredUnderKebabName(): void {
		HookHandler::setUp( [ 'filters' => [ 'include-import-file' => static fn ( $include ) => false ] ] );

		$this->assertFalse( AnalyzerHooks::includeImportFile( true, new SplFileInfo( 'entities.xml' ) ) );
	}
}
