<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BlogPost;

use DOMDocument;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class BlogPostTest extends TestCase {

	/** @var array */
	private $requiredData = [
		'global-space-id-to-key-map' => [ 32973 => 'TESTSPACE' ],
		'analyze-body-content-id-to-page-id-map' => [],
		'analyze-pages-titles-map' => [],
		'analyze-page-id-to-confluence-key-map' => [],
	];

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testTargetTitleIsBlogGeneralPrefixed() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/blog_post.xml' );

		$processor = new BlogPost( [], false );
		$processor->setOutput( new NullOutput() );
		$processor->setData( $this->requiredData );
		$processor->execute( $dom );

		$map = $processor->getData( 'analyze-page-id-to-title-map' );
		$this->assertArrayHasKey( 262251, $map );
		$this->assertSame( 'Blog:General/Our new tool', $map[262251] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testBodyContentIdIsMappedToPageId() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/blog_post.xml' );

		$processor = new BlogPost( [], false );
		$processor->setOutput( new NullOutput() );
		$processor->setData( $this->requiredData );
		$processor->execute( $dom );

		$map = $processor->getData( 'global-body-content-id-to-page-id-map' );
		$this->assertArrayHasKey( 262252, $map );
		$this->assertSame( 262251, $map[262252] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testDraftBlogPostIsSkipped() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<hibernate-generic>
    <object class="BlogPost" package="com.atlassian.confluence.pages">
        <id name="id">999</id>
        <property name="title"><![CDATA[Draft Post]]></property>
        <property name="contentStatus"><![CDATA[draft]]></property>
        <property name="version">1</property>
        <property name="lastModificationDate">2020-11-09 16:07:42.492</property>
        <property name="space" class="Space" package="com.atlassian.confluence.spaces">
            <id name="id">32973</id>
        </property>
    </object>
</hibernate-generic>';
		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$processor = new BlogPost( [], false );
		$processor->setOutput( new NullOutput() );
		$processor->setData( $this->requiredData );
		$processor->execute( $dom );

		$map = $processor->getData( 'analyze-page-id-to-title-map' );
		$this->assertArrayNotHasKey( 999, $map );
	}
}
