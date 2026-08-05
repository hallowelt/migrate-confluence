<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Output\Output;

/**
 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents
 */
class InvalidContentsTest extends TestCase {

	/** @var string */
	private string $tmpDir = '';

	/** @var ReflectionMethod */
	private ReflectionMethod $method;

	/** @var InvalidContents */
	private InvalidContents $processor;

	protected function setUp(): void {
		parent::setUp();
		$this->tmpDir = sys_get_temp_dir() . '/migrate-confluence-invalid-test-' . uniqid( '', true );
		mkdir( $this->tmpDir . '/result', 0755, true );

		$migrationConfig = $this->createMock( MigrationConfig::class );
		$migrationConfig->method( 'getComposerPagePerXmlLimit' )->willReturn( 0 );

		$this->processor = new InvalidContents(
			$this->createMock( Builder::class ),
			$this->createMock( DBComposerDataLookup::class ),
			$this->createMock( Workspace::class ),
			$this->makeOutput(),
			$this->tmpDir,
			$migrationConfig
		);

		$this->method = new ReflectionMethod( InvalidContents::class, 'appendInvalidCategories' );
	}

	protected function tearDown(): void {
		$this->deleteDir( $this->tmpDir );
		parent::tearDown();
	}

	public function testNoChangeWhenReasonUnknown(): void {
		$result = $this->method->invoke( $this->processor, 'some content', 'unknown reason' );
		$this->assertSame( 'some content', $result );
	}

	public function testNoChangeWhenReasonEmpty(): void {
		$result = $this->method->invoke( $this->processor, 'some content', '' );
		$this->assertSame( 'some content', $result );
	}

	public function testOverlongPageCategory(): void {
		$result = $this->method->invoke(
			$this->processor,
			'content',
			'BodyContent exceeded length of 512 characters'
		);
		$this->assertStringContainsString( '[[Category:Overly_long_page]]', $result );
	}

	public function testTitleEndsWithInvalidCharacter(): void {
		$result = $this->method->invoke(
			$this->processor,
			'content',
			'Title ends with invalid character'
		);
		$this->assertStringContainsString(
			'[[Category:Invalid_title_ends_with_invalid_character]]',
			$result
		);
	}

	public function testInvalidNamespaceCharacter(): void {
		$result = $this->method->invoke(
			$this->processor,
			'content',
			'Invalid namespace character detected'
		);
		$this->assertStringContainsString( '[[Category:Invalid_title_namespace_character]]', $result );
	}

	public function testTitleMultipleColons(): void {
		$result = $this->method->invoke(
			$this->processor,
			'content',
			'Title contains multiple colons'
		);
		$this->assertStringContainsString( '[[Category:Invalid_title_multiple_colons]]', $result );
	}

	public function testTitleTooLong(): void {
		$result = $this->method->invoke(
			$this->processor,
			'content',
			'Title contains too many characters (>255)'
		);
		$this->assertStringContainsString( '[[Category:Invalid_title_too_long]]', $result );
	}

	public function testMultipleMatchingReasonsProduceMultipleCategories(): void {
		$invalidText = "Title ends with invalid character\nTitle contains multiple colons";
		$result = $this->method->invoke( $this->processor, 'content', $invalidText );

		$this->assertStringContainsString(
			'[[Category:Invalid_title_ends_with_invalid_character]]',
			$result
		);
		$this->assertStringContainsString( '[[Category:Invalid_title_multiple_colons]]', $result );
	}

	public function testContentIsRtrimmedBeforeCategoriesAppended(): void {
		$result = $this->method->invoke(
			$this->processor,
			"content   \n\n",
			'Title contains multiple colons'
		);
		$this->assertStringStartsWith( 'content', $result );
		$this->assertStringContainsString( '[[Category:', $result );
		// No whitespace between original content and category line.
		$this->assertStringNotContainsString( "content   \n\n[[", $result );
	}

	/** @return Output */
	private function makeOutput(): Output {
		return new class extends Output {
			public function doWrite( string $message, bool $newline ): void {
			}
		};
	}

	private function deleteDir( string $dir ): void {
		if ( $dir === '' || !is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( $items === false ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->deleteDir( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}
}
