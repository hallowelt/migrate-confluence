<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverterMediaWiki;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\TocMacroUsage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class ConfluenceConverterMediaWikiTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterMediaWiki::matchesProfile
	 */
	public function testMatchesProfileOnlyForMediaWiki(): void {
		$converter = new ConfluenceConverterMediaWiki(
			[ 'config' => [ 'profile' => 'mediawiki' ] ], $this->createMock( Workspace::class )
		);
		$this->assertTrue( $converter->matchesProfile() );

		$otherProfile = new ConfluenceConverterMediaWiki(
			[ 'config' => [ 'profile' => 'bluespice-galaxy' ] ], $this->createMock( Workspace::class )
		);
		$this->assertFalse( $otherProfile->matchesProfile() );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterMediaWiki::getProcessors
	 */
	public function testGetProcessorsAppendsStatusMacro(): void {
		$converter = new ConfluenceConverterMediaWiki( [], $this->createMock( Workspace::class ) );
		$this->initMinimalPropertiesForProcessors( $converter );

		$processors = ( new ReflectionMethod( ConfluenceConverterMediaWiki::class, 'getProcessors' ) )
			->invoke( $converter );

		$this->assertInstanceOf( StatusMacro::class, end( $processors ) );
	}

	private function initMinimalPropertiesForProcessors( ConfluenceConverterMediaWiki $converter ): void {
		$database = ( new WorkspaceDbMock() )->createEmpty();
		$this->setProperty( $converter, 'dataLookup', new DBConversionDataLookup( $database ) );
		$this->setProperty( $converter, 'tocMacroUsage', new TocMacroUsage() );
		$this->setProperty( $converter, 'wikiPageTitle', 'Page' );
		$this->setProperty( $converter, 'currentSpace', 0 );
		$this->setProperty( $converter, 'conversionDataWriter', new ConversionDataWriter( '/tmp' ) );
		$this->setProperty( $converter, 'confluencePageTitle', 'Page' );
		$this->setProperty( $converter, 'writer', $this->createMock( IConverterDataWriter::class ) );
	}

	private function setProperty( object $object, string $name, mixed $value ): void {
		$property = new ReflectionProperty( $object, $name );
		$property->setValue( $object, $value );
	}
}
