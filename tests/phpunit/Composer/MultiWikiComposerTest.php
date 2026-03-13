<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\MultiWikiComposer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Output\Output;

class MultiWikiComposerTest extends TestCase {

	/**
	 * @param array $wikisConfig
	 * @param array $bucketData key → value map returned by getBucketData
	 * @return MultiWikiComposer
	 */
	private function makeComposer(
		array $wikisConfig = [],
		array $bucketData = []
	): MultiWikiComposer {
		$workspace = $this->createMock( Workspace::class );
		$buckets = $this->createMock( DataBuckets::class );
		$buckets->method( 'getBucketData' )->willReturnCallback(
			static function ( $key ) use ( $bucketData ) {
				return $bucketData[$key] ?? [];
			}
		);
		$output = $this->createMock( Output::class );
		$customBuckets = $this->createMock( DataBuckets::class );

		return new MultiWikiComposer(
			$wikisConfig, $buckets, $workspace, [], $output, '/tmp', $customBuckets
		);
	}

	/**
	 * @param object $obj
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	// phpcs:ignore MediaWiki.Commenting.FunctionComment.ObjectTypeHintParam
	private function callPrivate( object $obj, string $method, array $args = [] ) {
		$ref = new ReflectionClass( $obj );
		$m = $ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $obj, $args );
	}

	/**
	 * @param object $obj
	 * @param string $property
	 * @return mixed
	 */
	// phpcs:ignore MediaWiki.Commenting.FunctionComment.ObjectTypeHintParam
	private function getPrivate( object $obj, string $property ) {
		$ref = new ReflectionClass( $obj );
		$p = $ref->getProperty( $property );
		$p->setAccessible( true );
		return $p->getValue( $obj );
	}

	/**
	 * @param object $obj
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	// phpcs:ignore MediaWiki.Commenting.FunctionComment.ObjectTypeHintParam
	private function setPrivate( object $obj, string $property, $value ): void {
		$ref = new ReflectionClass( $obj );
		$p = $ref->getProperty( $property );
		$p->setAccessible( true );
		$p->setValue( $obj, $value );
	}

	// ---------------------------------------------------------------------------
	// translatePageTitle
	// ---------------------------------------------------------------------------

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::translatePageTitle
	 * @return void
	 */
	public function testTranslatePageTitleStripsSourcePrefix(): void {
		$c = $this->makeComposer();
		$this->assertSame( 'SomePage', $c->translatePageTitle( 'A:SomePage', 'A:', '' ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::translatePageTitle
	 * @return void
	 */
	public function testTranslatePageTitleReplacesWithTargetPrefix(): void {
		$c = $this->makeComposer();
		$this->assertSame( 'MyNS:SomePage', $c->translatePageTitle( 'A:SomePage', 'A:', 'MyNS:' ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::translatePageTitle
	 * @return void
	 */
	public function testTranslatePageTitleWithSubpagePath(): void {
		$c = $this->makeComposer();
		$this->assertSame( 'Parent/Child', $c->translatePageTitle( 'A:Parent/Child', 'A:', '' ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::translatePageTitle
	 * @return void
	 */
	public function testTranslatePageTitleEmptySourcePrefixAddsTarget(): void {
		$c = $this->makeComposer();
		$this->assertSame( 'MyNS:SomePage', $c->translatePageTitle( 'SomePage', '', 'MyNS:' ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::translatePageTitle
	 * @return void
	 */
	public function testTranslatePageTitleBothPrefixesEmptyIsNoOp(): void {
		$c = $this->makeComposer();
		$this->assertSame( 'SomePage', $c->translatePageTitle( 'SomePage', '', '' ) );
	}

	// ---------------------------------------------------------------------------
	// rewriteIntraWikiLinks
	// ---------------------------------------------------------------------------

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::rewriteIntraWikiLinks
	 * @return void
	 */
	public function testRewriteStripsSourcePrefix(): void {
		$c = $this->makeComposer();
		$this->assertSame(
			'[[PageTitle|label]]',
			$c->rewriteIntraWikiLinks( '[[A:PageTitle|label]]', 'A:', '' )
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::rewriteIntraWikiLinks
	 * @return void
	 */
	public function testRewriteReplacesWithTargetPrefix(): void {
		$c = $this->makeComposer();
		$this->assertSame(
			'[[MyNS:PageTitle|label]]',
			$c->rewriteIntraWikiLinks( '[[A:PageTitle|label]]', 'A:', 'MyNS:' )
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::rewriteIntraWikiLinks
	 * @return void
	 */
	public function testRewriteLeavesOtherSpaceLinksUntouched(): void {
		$c = $this->makeComposer();
		$this->assertSame(
			'[[PageA]] and [[B:PageB]]',
			$c->rewriteIntraWikiLinks( '[[A:PageA]] and [[B:PageB]]', 'A:', '' )
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::rewriteIntraWikiLinks
	 * @return void
	 */
	public function testRewriteMultipleLinks(): void {
		$c = $this->makeComposer();
		$this->assertSame(
			'[[First]] text [[Second|Display]] more [[Third/Sub]]',
			$c->rewriteIntraWikiLinks(
				'[[A:First]] text [[A:Second|Display]] more [[A:Third/Sub]]', 'A:', ''
			)
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::rewriteIntraWikiLinks
	 * @return void
	 */
	public function testRewriteEmptySourcePrefixIsNoOp(): void {
		$c = $this->makeComposer();
		$this->assertSame(
			'[[SomePage|label]]',
			$c->rewriteIntraWikiLinks( '[[SomePage|label]]', '', '' )
		);
	}

	// ---------------------------------------------------------------------------
	// buildNamespaceToWikiMap
	// ---------------------------------------------------------------------------

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::buildNamespaceToWikiMap
	 * @return void
	 */
	public function testBuildNamespaceToWikiMapUsesGlobalPrefixBucket(): void {
		$c = $this->makeComposer(
			[
				'WikiA' => [ 'spaces' => [ 'A' => '' ] ],
				'WikiB' => [ 'spaces' => [ 'B' => '' ] ],
			],
			[ 'global-space-key-to-prefix-map' => [ 'A' => 'A:', 'B' => 'B:' ] ]
		);
		$this->callPrivate( $c, 'buildNamespaceToWikiMap' );
		$map = $this->getPrivate( $c, 'namespaceToWikiMap' );

		$this->assertSame( [ 'wiki' => 'WikiA', 'targetPrefix' => '' ], $map['A:'] );
		$this->assertSame( [ 'wiki' => 'WikiB', 'targetPrefix' => '' ], $map['B:'] );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::buildNamespaceToWikiMap
	 * @return void
	 */
	public function testBuildNamespaceToWikiMapFallbackWhenNotInBucket(): void {
		$c = $this->makeComposer(
			[ 'WikiA' => [ 'spaces' => [ 'A' => 'MyNS:' ] ] ]
		);
		$this->callPrivate( $c, 'buildNamespaceToWikiMap' );
		$map = $this->getPrivate( $c, 'namespaceToWikiMap' );

		$this->assertSame( [ 'wiki' => 'WikiA', 'targetPrefix' => 'MyNS:' ], $map['A:'] );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::buildNamespaceToWikiMap
	 * @return void
	 */
	public function testBuildNamespaceToWikiMapGeneralSpaceUsesEmptyPrefix(): void {
		$c = $this->makeComposer(
			[ 'WikiA' => [ 'spaces' => [ 'GENERAL' => '' ] ] ]
		);
		$this->callPrivate( $c, 'buildNamespaceToWikiMap' );
		$map = $this->getPrivate( $c, 'namespaceToWikiMap' );

		$this->assertArrayHasKey( '', $map );
		$this->assertSame( [ 'wiki' => 'WikiA', 'targetPrefix' => '' ], $map[''] );
	}

	// ---------------------------------------------------------------------------
	// getWikiEntryForTitle
	// ---------------------------------------------------------------------------

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::getWikiEntryForTitle
	 * @return void
	 */
	public function testGetWikiEntryReturnsMappedEntry(): void {
		$c = $this->makeComposer();
		$this->setPrivate( $c, 'namespaceToWikiMap', [
			'A:' => [ 'wiki' => 'WikiA', 'targetPrefix' => '' ],
			'B:' => [ 'wiki' => 'WikiB', 'targetPrefix' => '' ],
		] );

		$entry = $this->callPrivate( $c, 'getWikiEntryForTitle', [ 'A:SomePage' ] );
		$this->assertSame( 'WikiA', $entry['wiki'] );
		$this->assertSame( 'A:', $entry['sourcePrefix'] );
		$this->assertSame( '', $entry['targetPrefix'] );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::getWikiEntryForTitle
	 * @return void
	 */
	public function testGetWikiEntryReturnsNullForUnmappedTitle(): void {
		$c = $this->makeComposer();
		$this->setPrivate( $c, 'namespaceToWikiMap', [
			'A:' => [ 'wiki' => 'WikiA', 'targetPrefix' => '' ],
		] );

		$this->assertNull( $this->callPrivate( $c, 'getWikiEntryForTitle', [ 'C:Unknown' ] ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::getWikiEntryForTitle
	 * @return void
	 */
	public function testGetWikiEntryHandlesNsMainTitle(): void {
		$c = $this->makeComposer();
		$this->setPrivate( $c, 'namespaceToWikiMap', [
			'' => [ 'wiki' => 'WikiA', 'targetPrefix' => '' ],
		] );

		$entry = $this->callPrivate( $c, 'getWikiEntryForTitle', [ 'SomePage' ] );
		$this->assertNotNull( $entry );
		$this->assertSame( 'WikiA', $entry['wiki'] );
	}

	// ---------------------------------------------------------------------------
	// imagesPath
	// ---------------------------------------------------------------------------

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::imagesPath
	 * @return void
	 */
	public function testImagesPathReturnsPerWikiPath(): void {
		$c = $this->makeComposer();
		$path = $this->callPrivate( $c, 'imagesPath', [ 'WikiA' ] );
		$this->assertSame( 'result/WikiA/images', $path );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Composer\MultiWikiComposer::imagesPath
	 * @return void
	 */
	public function testImagesPathSanitizesWikiName(): void {
		$c = $this->makeComposer();
		$path = $this->callPrivate( $c, 'imagesPath', [ 'My Wiki!' ] );
		$this->assertSame( 'result/My_Wiki_/images', $path );
	}
}
