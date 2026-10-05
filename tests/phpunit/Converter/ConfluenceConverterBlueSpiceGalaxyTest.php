<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBlueSpiceGalaxy;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\TocMacroUsage;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;

class ConfluenceConverterBlueSpiceGalaxyTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBlueSpiceGalaxy::matchesProfile
	 */
	public function testMatchesProfileOnlyForBlueSpiceGalaxy(): void {
		$converter = new ConfluenceConverterBlueSpiceGalaxy(
			[ 'config' => [ 'profile' => 'bluespice-galaxy' ] ], $this->createMock( Workspace::class )
		);
		$this->assertTrue( $converter->matchesProfile() );

		$otherProfile = new ConfluenceConverterBlueSpiceGalaxy(
			[ 'config' => [ 'profile' => 'mediawiki' ] ], $this->createMock( Workspace::class )
		);
		$this->assertFalse( $otherProfile->matchesProfile() );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBlueSpiceGalaxy::getProcessors
	 */
	public function testGetProcessorsExtendsDefaultProcessors(): void {
		$converter = new ConfluenceConverterBlueSpiceGalaxy( [], $this->createMock( Workspace::class ) );
		$this->initMinimalPropertiesForProcessors( $converter );

		$processors = ( new ReflectionMethod( ConfluenceConverterBlueSpiceGalaxy::class, 'getProcessors' ) )
			->invoke( $converter );
		$defaultProcessors = ( new ReflectionMethod(
			ConfluenceConverterBlueSpiceGalaxy::class, 'getDefaultProcessors'
		) )
			->invoke( $converter );

		// The profile is expected to add to, not replace, the default processor list.
		$this->assertGreaterThan( count( $defaultProcessors ), count( $processors ) );
		$defaultClasses = array_map( 'get_class', $defaultProcessors );
		$actualClasses = array_map( 'get_class', $processors );

		// The profile is expected to insert its own processors at a fixed position, leaving the
		// remaining default processors in their original relative order.
		$position = ( new ReflectionClassConstant(
			ConfluenceConverterBase::class, 'PROFILE_AWARE_PROCESSORS_POSITION'
		) )
			->getValue();
		$insertedCount = count( $processors ) - count( $defaultProcessors );
		$classesWithoutInserted = $actualClasses;
		array_splice( $classesWithoutInserted, $position, $insertedCount );
		$this->assertSame( $defaultClasses, $classesWithoutInserted );
	}

	private function initMinimalPropertiesForProcessors( ConfluenceConverterBlueSpiceGalaxy $converter ): void {
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
