<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility;

use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\LinkResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\LinkResolver
 */
class LinkResolverTest extends TestCase {

	public function testTryResolveLinkTarget() {
		$workspaceDb = ( new WorkspaceDbMock() )
			->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDb );
		$linkResolver = new LinkResolver( $dataLookup, 0 );

		$dir = dirname( __DIR__, 1 ) . '/data/LinkResolution';
		$input = file_get_contents( "$dir/link-targets-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'ac', 'sample_namespace' );
		$xpath->registerNamespace( 'ri', 'sample_second_namespace' );

		$linkTargets = [];
		foreach ( $xpath->query( '/xml/*' ) as $node ) {
			$linkTargets[] = $linkResolver->tryResolveLinkTarget( $node );
		}

		$this->assertStringEqualsFile(
			"$dir/link-targets-output.wikitext",
			$actual = implode( "\n", $linkTargets )
		);
	}

	public function testIsRedLink() {

	}
}
