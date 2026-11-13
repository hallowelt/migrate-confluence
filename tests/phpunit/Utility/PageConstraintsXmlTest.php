<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility;

use DOMDocument;
use HalloWelt\MigrateConfluence\Utility\DirectoryHelper;
use HalloWelt\MigrateConfluence\Utility\PageConstraintsXml;
use HalloWelt\MigrateConfluence\Utility\WikiFileXmlBuilder;
use PHPUnit\Framework\TestCase;

class PageConstraintsXmlTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsXml::fill
	 */
	public function testFill(): void {
		$dom = new DOMDocument();
		$el = $dom->createElement( 'pageconstraints' );
		PageConstraintsXml::fill( $el, [
			'type' => 'read_and_edit',
			'timestamp' => '2026-03-09T13:06:33Z',
			'targets' => [ [ 'kind' => 'group', 'name' => 'Developers', 'level' => 'read' ] ],
		] );
		$dom->appendChild( $el );

		$this->assertSame(
			'<pageconstraints type="read_and_edit" inherit="0" timestamp="2026-03-09T13:06:33Z">'
			. '<target kind="group" name="Developers" level="read"/></pageconstraints>',
			$dom->saveXML( $el )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\WikiFileXmlBuilder::setPageConstraints
	 */
	public function testFileBuilderWritesConstraintsAfterTitle(): void {
		$path = dirname( __DIR__, 3 ) . '/.tmp/files-constraints-test.xml';
		DirectoryHelper::ensure( dirname( $path ) );
		$builder = new WikiFileXmlBuilder();
		$builder->setPageConstraints( 'a.png', PageConstraintsXml::NONE );
		$builder->addFileRevision( 'a.png', './images/a.png' );
		$builder->buildAndSave( $path );

		$dom = new DOMDocument();
		$dom->load( $path );
		unlink( $path );

		$fileEl = $dom->getElementsByTagName( 'file' )->item( 0 );
		$names = [];
		foreach ( $fileEl->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE ) {
				$names[] = $child->nodeName;
			}
		}
		$this->assertSame( [ 'title', 'pageconstraints', 'revision' ], $names );
	}
}
