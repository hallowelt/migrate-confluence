<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageTemplateContents;
use PHPUnit\Framework\TestCase;

class ExtractPageTemplateContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageTemplateContents::execute
	 */
	public function testExtractsNonEmptyTemplateContentsAsRawFiles(): void {
		$workspaceDB = $this->createMock( IExtractorDataReader::class );
		$workspace = $this->createMock( Workspace::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$workspaceDB->method( 'getCurrentPageTemplateContents' )->willReturn( [
			[ 'template_id' => 20, 'content' => 'Template content' ],
			[ 'template_id' => 21, 'content' => '' ],
		] );

		$workspace->expects( $this->once() )
			->method( 'saveRawContent' )
			->with( 'pt_20', '<html><body>Template content</body></html>' );

		$processor = new ExtractPageTemplateContents( $workspaceDB, $workspace, $writer );
		$processor->execute();
	}
}
