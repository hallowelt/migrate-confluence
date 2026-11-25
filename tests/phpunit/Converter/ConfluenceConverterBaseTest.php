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
			1, 1, 'Folder', 'folder', 'Folder', 'current', '', '', '1', -1, -1, [], [], [ 'isFolder' => true ], []
		);

		$converter = $this->newConverter();
		$writer = $this->createMock( IConverterDataWriter::class );
		$writer->expects( $this->once() )
			->method( 'registerDefaultPage' )
			->with( 1, 'Folder' );
		$this->setProperty( $converter, 'dataLookup', new DBConversionDataLookup( $database ) );
		$this->setProperty( $converter, 'writer', $writer );
		$this->setProperty( $converter, 'contentType', 'page' );
		$this->setProperty( $converter, 'pageId', 1 );
		$this->setProperty( $converter, 'currentSpace', 1 );
		$this->setProperty( $converter, 'wikiText', '' );

		( new ReflectionMethod( ConfluenceConverterBase::class, 'addFolderTemplateIfApplicable' ) )
			->invoke( $converter );

		$this->assertSame( '{{Folder}}', $this->getProperty( $converter, 'wikiText' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase::addCoverBannerIfApplicable
	 * @dataProvider provideCoverBanners
	 */
	public function testAddsCoverBanner(
		string $contentType, ?string $coverJson, bool $withAttachment, string $expected, bool $registers
	): void {
		$database = ( new WorkspaceDbMock() )->createEmpty();
		$isBlogPost = $contentType === 'blogPost';
		$collection = [];
		if ( $coverJson !== null ) {
			$database->addContentProperty( 10, 'coverPicture', 'x', [ 'stringValue' => $coverJson ] );
			$collection = [ 'contentProperties' => [ 10 ] ];
		}
		if ( $isBlogPost ) {
			$database->addBlogPost( 1, 1, 'Post', 'post', 'Post', 'current', '', '', '1', -1, [], [], [], $collection );
		} else {
			$database->addPage(
				1, 1, 'Page', 'page', 'Page', 'current', '', '', '1', -1, -1, [], [], [], $collection
			);
		}
		if ( $withAttachment ) {
			$database->addContentProperty( 11, 'FILESTORE_ID', 'x', [ 'stringValue' => 'abc-123' ] );
			$database->addAttachment(
				5, 1, 'Cover pic.png', 'png', 1, 'current', '1', '', '', -1, '', [], [],
				[ 'contentProperties' => [ 11 ] ]
			);
			if ( $isBlogPost ) {
				$database->addBlogPostAttachment( 5, 1, 'Cover pic.png', 'File:Post-Cover_pic.png' );
			} else {
				$database->addPageAttachment( 5, 1, 'Cover pic.png', 'File:Page-Cover_pic.png' );
			}
		}

		$converter = $this->newConverter();
		$writer = $this->createMock( IConverterDataWriter::class );
		$writer->expects( $registers ? $this->once() : $this->never() )
			->method( 'registerDefaultPage' )
			->with( 1, 'Banner', 'Widget' );
		$this->setProperty( $converter, 'dataLookup', new DBConversionDataLookup( $database ) );
		$this->setProperty( $converter, 'writer', $writer );
		$this->setProperty( $converter, 'contentType', $contentType );
		$this->setProperty( $converter, 'pageId', 1 );
		$this->setProperty( $converter, 'currentSpace', 1 );
		$this->setProperty( $converter, 'wikiText', 'Body' );

		( new ReflectionMethod( ConfluenceConverterBase::class, 'addCoverBannerIfApplicable' ) )
			->invoke( $converter );

		$this->assertSame( $expected, $this->getProperty( $converter, 'wikiText' ) );
	}

	public static function provideCoverBanners(): array {
		// The export double-escapes the JSON string
		$attachment = '{\\"id\\":\\"abc-123\\",\\"position\\":50}';
		$external = '{\\"id\\":\\"https:\\/\\/example.org\\/a.jpg?x=1&y=2\\",\\"position\\":0.5}';
		return [
			'attachment' => [
				'page', $attachment, true,
				"{{#widget:Banner|url={{filepath:Page-Cover_pic.png}}|position=50}}\nBody", true
			],
			'external URL' => [
				'page', $external, false,
				"{{#widget:Banner|url=https://example.org/a.jpg?x=1&y=2|position=0.5}}\nBody", true
			],
			'no cover' => [ 'page', null, false, 'Body', false ],
			'missing attachment' => [ 'page', $attachment, false, "Body\n[[Category:Broken_banner]]", false ],
			'blog post' => [
				'blogPost', $attachment, true,
				"{{#widget:Banner|url={{filepath:Post-Cover_pic.png}}|position=50}}\nBody", true
			],
			'comment is ignored' => [ 'comment', $attachment, true, 'Body', false ],
		];
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
