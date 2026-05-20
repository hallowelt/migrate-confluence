<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Comments;

use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class CommentsTest extends TestCase {

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
	 * @return void
	 */
	private function runProcessor( string $xmlFile ): void {
		$processor = new Comments( $this->workspaceDB );
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
			if ( $class === 'Comment' ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testPageLevelCommentIsStored() {
		$this->migrationConfig = new MigrationConfig( [] );
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$this->runProcessor(
			__DIR__ . '/comment_page_level.xml'
		);

		$comments = $this->workspaceDB->getComments();
		$comment = $comments[0];

		$this->assertSame( 600, $comment['comment_id'] );
		$this->assertSame( 700, $comment['container_id'] );
		$this->assertSame( '[]', $comment['body_content_ids'] );
		$this->assertSame( 'userkey-abc', $comment['user_key'] );
	}
}
