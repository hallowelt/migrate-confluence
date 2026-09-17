<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\TocMacroUsage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class ConfluenceConverterBaseTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase::addFolderTemplateIfApplicable
	 */
	public function testAddsFolderTemplateToEmptyFolderPage(): void {
		$database = ( new WorkspaceDbMock() )->createEmpty();
		$database->addPage(
			1, 1, 'Folder', 'Folder', 'current', '', '', '1', -1, -1, [], [], [ 'isFolder' => true ], []
		);

		$converter = $this->newConverter();
		$this->setProperty( $converter, 'dataLookup', new DBConversionDataLookup( $database ) );
		$this->setProperty( $converter, 'contentType', 'page' );
		$this->setProperty( $converter, 'pageId', 1 );
		$this->setProperty( $converter, 'wikiText', '' );

		( new ReflectionMethod( ConfluenceConverterBase::class, 'addFolderTemplateIfApplicable' ) )
			->invoke( $converter );

		$this->assertSame( '{{Folder}}', $this->getProperty( $converter, 'wikiText' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase::matchesProfile
	 */
	public function testMatchesProfileIsFalseForBaseClass(): void {
		$converter = $this->newConverter( [ 'config' => [ 'profile' => 'mediawiki' ] ] );

		$this->assertFalse( $converter->matchesProfile() );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase::getProcessors
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase::getDefaultProcessors
	 */
	public function testGetProcessorsDefaultsToDefaultProcessors(): void {
		$converter = $this->newConverter();
		$this->initMinimalPropertiesForProcessors( $converter );

		$processors = ( new ReflectionMethod( ConfluenceConverterBase::class, 'getProcessors' ) )
			->invoke( $converter );
		$defaultProcessors = ( new ReflectionMethod( ConfluenceConverterBase::class, 'getDefaultProcessors' ) )
			->invoke( $converter );

		$this->assertSameSize( $defaultProcessors, $processors );
		foreach ( $processors as $index => $processor ) {
			$this->assertInstanceOf( get_class( $defaultProcessors[$index] ), $processor );
		}
	}

	/**
	 * ConfluenceConverterBase is abstract; use a minimal anonymous concrete subclass.
	 */
	private function newConverter( array $config = [] ): ConfluenceConverterBase {
		return new class ( $config, $this->createMock( Workspace::class ) ) extends ConfluenceConverterBase {
		};
	}

	/**
	 * Set the properties that getDefaultProcessors()/getProcessors() read, since
	 * they are otherwise only initialized during convert().
	 */
	private function initMinimalPropertiesForProcessors( ConfluenceConverterBase $converter ): void {
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

	private function getProperty( object $object, string $name ): mixed {
		return ( new ReflectionProperty( $object, $name ) )->getValue( $object );
	}
}
