<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class ConfluenceConverterTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverter::addFolderTemplateIfApplicable
	 */
	public function testAddsFolderTemplateToEmptyFolderPage(): void {
		$database = ( new WorkspaceDbMock() )->createEmpty();
		$database->addPage(
			1, 1, 'Folder', 'Folder', 'current', '', '', '1', -1, -1, [], [], [ 'isFolder' => true ], []
		);

		$converter = new ConfluenceConverter( [], $this->createMock( Workspace::class ) );
		$this->setProperty( $converter, 'dataLookup', new DBConversionDataLookup( $database ) );
		$this->setProperty( $converter, 'contentType', 'page' );
		$this->setProperty( $converter, 'pageId', 1 );
		$this->setProperty( $converter, 'wikiText', '' );

		( new ReflectionMethod( ConfluenceConverter::class, 'addFolderTemplateIfApplicable' ) )
			->invoke( $converter );

		$this->assertSame( '{{Folder}}', $this->getProperty( $converter, 'wikiText' ) );
	}

	private function setProperty( object $object, string $name, mixed $value ): void {
		$property = new ReflectionProperty( $object, $name );
		$property->setValue( $object, $value );
	}

	private function getProperty( object $object, string $name ): mixed {
		return ( new ReflectionProperty( $object, $name ) )->getValue( $object );
	}
}
