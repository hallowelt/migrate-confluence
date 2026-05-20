<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BlogPost;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class BlogPostTest extends TestCase {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

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
	 * @return void
	 */
	private function runProcessor( string $xmlFile, array $data = [] ): void {
		$processor = new BlogPost( $this->workspaceDB, $this->migrationConfig );
		$processor->setOutput( $this->makeOutput() );

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

		$this->workspaceDB->updateBlogPostWikiTitle( 262251, 'Blog:TESTSPACE/Our_new_tool' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testTargetTitleUsesSpaceIdPrefix() {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$this->runProcessor( __DIR__ . '/blog_post.xml' );

		$blogPosts = $this->workspaceDB->getBlogPosts();
		$blogPost = $blogPosts[0];

		$this->assertSame( 'Blog:TESTSPACE/Our_new_tool', $blogPost['wiki_title'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testBodyContentIdIsMappedToPageId() {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$this->runProcessor( __DIR__ . '/blog_post.xml' );

		$blogPosts = $this->workspaceDB->getBlogPosts();
		$blogPost = $blogPosts[0];

		$bodyContentIds = json_decode( $blogPost['body_content_ids'], true );

		$this->assertSame( 262252, (int)$bodyContentIds[0] );
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

		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$processor = new BlogPost( $this->workspaceDB, $this->migrationConfig );
		$processor->setOutput( $this->makeOutput() );

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

		$blogPosts = $this->workspaceDB->getBlogPosts();

		// Draft blog post should be skipped and not stored in the database
		$this->assertEquals( [], $blogPosts );
	}
}
