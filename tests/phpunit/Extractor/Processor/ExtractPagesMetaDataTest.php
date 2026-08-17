<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ExtractPagesMetaDataTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData::execute
	 */
	public function testAddsPageMetaWithConfiguredAndLabelCategories(): void {
		$workspaceDB = $this->createMock( IExtractorDataReader::class );
		$migrationConfig = $this->createMock( MigrationConfig::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$migrationConfig->method( 'getCategories' )->willReturn( [ 'ConfiguredCategory' ] );
		$workspaceDB->method( 'getCurrentPages' )->willReturn( [
			[
				'page_id' => 30,
				'wiki_title' => 'TEST:Page',
				'original_version_id' => -1,
				'collection' => json_encode( [ 'labellings' => [ 200 ] ] ),
			],
		] );
		$workspaceDB->method( 'getLabellingById' )->with( 200 )->willReturn( [ 'label_id' => 300 ] );
		$workspaceDB->method( 'getLabelById' )->with( 300 )->willReturn( [ 'name' => 'LabelCategory' ] );

		$writer->expects( $this->once() )
			->method( 'addPageMeta' )
			->with(
				30,
				[ 'categories' => [ 'ConfiguredCategory', 'LabelCategory' ] ]
			);

		$writer->expects( $this->once() )->method( 'addLogEntry' );

		$processor = new ExtractPagesMetaData( $workspaceDB, $writer, $migrationConfig );
		$processor->execute();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData::execute
	 */
	public function testDoesNotLeakLabelCategoriesBetweenPages(): void {
		$workspaceDB = $this->createMock( IExtractorDataReader::class );
		$migrationConfig = $this->createMock( MigrationConfig::class );
		$writer = $this->createMock( ExtractorDirectDataWriter::class );

		$migrationConfig->method( 'getCategories' )->willReturn( [] );
		$workspaceDB->method( 'getCurrentPages' )->willReturn( [
			[
				'page_id' => 10,
				'wiki_title' => 'TEST:PageWithLabel',
				'original_version_id' => -1,
				'collection' => json_encode( [ 'labellings' => [ 200 ] ] ),
			],
			[
				'page_id' => 20,
				'wiki_title' => 'TEST:PageWithoutLabel',
				'original_version_id' => -1,
				'collection' => json_encode( [ 'labellings' => [] ] ),
			],
		] );
		$workspaceDB->method( 'getLabellingById' )->with( 200 )->willReturn( [ 'label_id' => 300 ] );
		$workspaceDB->method( 'getLabelById' )->with( 300 )->willReturn( [ 'name' => 'LabelCategory' ] );

		$writer->expects( $this->once() )
			->method( 'addPageMeta' )
			->with(
				10,
				[ 'categories' => [ 'LabelCategory' ] ]
			);

		$processor = new ExtractPagesMetaData( $workspaceDB, $writer, $migrationConfig );
		$processor->execute();
	}
}
