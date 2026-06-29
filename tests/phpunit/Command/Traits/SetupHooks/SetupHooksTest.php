<?php

namespace HalloWelt\MigrateConfluence\Tests\Command\Traits\SetupHooks;

use HalloWelt\MigrateConfluence\Command\Traits\SetupHooks;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\HookHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \HalloWelt\MigrateConfluence\Command\Traits\SetupHooks
 */
class SetupHooksTest extends TestCase {

	/** @var string[] paths of all temp files created during a test */
	private array $tempFiles = [];

	protected function setUp(): void {
		$this->resetHookHandler();
	}

	protected function tearDown(): void {
		foreach ( $this->tempFiles as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->tempFiles = [];
		$this->resetHookHandler();
	}

	private function resetHookHandler(): void {
		$ref = new ReflectionClass( HookHandler::class );
		$prop = $ref->getProperty( 'handlers' );
		$prop->setValue( null, [ 'filters' => [], 'actions' => [] ] );
	}

	/**
	 * Write a minimal stand-in "config file" (just needs to exist on disk).
	 */
	private function writeConfigFile(): string {
		$path = tempnam( sys_get_temp_dir(), 'hooks_test_' ) . '.php';
		file_put_contents( $path, "# config placeholder" );
		$this->tempFiles[] = $path;
		return $path;
	}

	/**
	 * Write a hook handler PHP file in the same directory as $configFile and return its basename.
	 */
	private function writeHandlerNextToConfig( string $configFile, string $phpSource ): string {
		$basename = 'handler_' . uniqid() . '.php';
		$path = dirname( $configFile ) . DIRECTORY_SEPARATOR . $basename;
		file_put_contents( $path, $phpSource );
		$this->tempFiles[] = $path;
		return $basename;
	}

	private function makeSubject(): object {
		return new class {
			use SetupHooks;

			public function run( array $config, ?string $configFilePath, $logger = null ): void {
				$this->installCustomerHooks( $config, $configFilePath, $logger );
			}
		};
	}

	public function testDoesNothingWhenConfigFilePathIsNull(): void {
		$subject = $this->makeSubject();
		$subject->run( [], null );
		$this->addToAssertionCount( 1 );
	}

	public function testDoesNothingWhenConfigFilePathDoesNotExist(): void {
		$subject = $this->makeSubject();
		$subject->run( [], '/nonexistent/path/config.yaml' );
		$this->addToAssertionCount( 1 );
	}

	public function testThrowsWhenHookHandlerFileNotFound(): void {
		$configFile = $this->writeConfigFile();
		$subject = $this->makeSubject();
		$this->expectException( \RuntimeException::class );
		$subject->run(
			[ 'config' => [ 'hook-handler' => 'nonexistent_hooks.php' ] ],
			$configFile
		);
	}

	public function testThrowsWhenHookHandlerFileDoesNotReturnArray(): void {
		$configFile = $this->writeConfigFile();
		$basename = $this->writeHandlerNextToConfig(
			$configFile,
			"<?php\nreturn 'not-an-array';\n"
		);
		$subject = $this->makeSubject();
		$this->expectException( \RuntimeException::class );
		$subject->run(
			[ 'config' => [ 'hook-handler' => $basename ] ],
			$configFile
		);
	}

	public function testHooksAreRegisteredWithHookHandler(): void {
		$configFile = $this->writeConfigFile();
		$basename = $this->writeHandlerNextToConfig(
			$configFile,
			"<?php\nreturn [\n" .
			"  'filters' => ['uc-filter' => 'strtoupper'],\n" .
			"  'actions' => [],\n" .
			"];\n"
		);

		$subject = $this->makeSubject();
		$subject->run(
			[ 'config' => [ 'hook-handler' => $basename ] ],
			$configFile,
			$this->createMock( DBLog::class )
		);

		$result = HookHandler::filter( 'uc-filter', 'hello' );
		$this->assertSame( 'HELLO', $result, 'Registered filter should transform the value' );
	}

}
