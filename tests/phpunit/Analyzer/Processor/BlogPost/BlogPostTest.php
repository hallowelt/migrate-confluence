<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BlogPost;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class BlogPostTest extends TestCase {

	/** @var array */
	private $requiredData = [
		'global-space-id-to-key-map' => [ 32973 => 'TESTSPACE' ],
		'analyze-body-content-id-to-page-id-map' => [],
		'analyze-blogposts-titles-map' => [],
		'analyze-blogpost-id-to-confluence-key-map' => [],
	];

	/** @return Output */
	private function makeOutput(): Output {
		return new class extends Output {
			public function doWrite( string $message, bool $newline ): void {
			}
		};
	}

	/**
	 * @param string $xmlFile
	 * @param array $data
	 * @return BlogPost
	 */
	private function runProcessor( string $xmlFile, array $data = [] ): BlogPost {
		$processor = new BlogPost( [], false );
		$processor->setOutput( $this->makeOutput() );
		$processor->setData( $data ?: $this->requiredData );

		$xmlReader = new XMLReader();
		$xmlReader->open( $xmlFile );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'BlogPost' ) {
				$read = $xmlReader->next();
				continue;
			}
			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}
			$read = $xmlReader->next();
		}
		$xmlReader->close();

		return $processor;
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testTargetTitleUsesSpaceIdPrefix() {
		$processor = $this->runProcessor( __DIR__ . '/blog_post.xml' );

		$map = $processor->getData( 'analyze-blogpost-id-to-title-map' );
		$this->assertArrayHasKey( 262251, $map );
		$this->assertSame( 'Blog:TESTSPACE/Our_new_tool', $map[262251] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testBodyContentIdIsMappedToPageId() {
		$processor = $this->runProcessor( __DIR__ . '/blog_post.xml' );

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

		$processor = new BlogPost( [], false );
		$processor->setOutput( $this->makeOutput() );
		$processor->setData( $this->requiredData );

		$xmlReader = XMLReader::XML( $xml );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'BlogPost' ) {
				$read = $xmlReader->next();
				continue;
			}
			$processor->execute( $xmlReader );
			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$map = $processor->getData( 'analyze-blogpost-id-to-title-map' );
		$this->assertArrayNotHasKey( 999, $map );
	}
}
