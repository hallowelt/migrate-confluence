<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTemplate;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestorePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTimeTag;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Processor\PreserveTimeTag;
use HalloWelt\MigrateConfluence\Converter\Processor\TaskListMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLink;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class TableComplexTest extends TestCase {
	/** @var string */
	private $dir = '';

	/**
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';
		$this->doTestWith( 'table-complex-input.xml', 'table-complex-output.wikitext' );
	}

	/**
	 * @return void
	 */
	public function testProcessWithInclude() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';
		$this->doTestWith( 'table-complex-with-include-input.xml', 'table-complex-with-include-output.wikitext' );
	}

	/**
	 * @param string $inputFile
	 * @param string $outputFile
	 * @return void
	 */
	private function doTestWith( string $inputFile, string $outputFile ): void {
		$input = file_get_contents( "$this->dir/$inputFile" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		$processors = [
			new PreserveTimeTag(),
			new UserLink( $dataLookup, 42, 'SomePage', new MigrationConfig( [] ) ),
			new PageLink( $dataLookup, 42, 'SomePage', new MigrationConfig( [] ) ),
			new TaskListMacro(),
			new PreservePStyleTag(),
		];

		foreach ( $processors as $processor ) {
			$processor->process( $dom );
		}

		$unhandled = new UnhandledMacroConverter();
		$unhandled->process( $dom );

		$tmpFile = tempnam( sys_get_temp_dir(), 'table-complex-' ) . '.html';
		$dom->saveHTMLFile( $tmpFile );
		$result = [];
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.escapeshellcmd,MediaWiki.Usage.ForbiddenFunctions.exec
		exec( escapeshellcmd( "pandoc -f html -t mediawiki $tmpFile" ), $result );
		$wikiText = implode( "\n", $result );
		unlink( $tmpFile );

		$postProcessors = [
			new RestorePStyleTag(),
			new RestoreTimeTag(),
			new FixLineBreakInHeadings(),
			new FixMultilineTemplate(),
			new FixMultilineTable(),
		];

		foreach ( $postProcessors as $postProcessor ) {
			$wikiText = $postProcessor->postprocess( $wikiText );
		}

		$expectedOutput = file_get_contents( "$this->dir/$outputFile" );
		$normalize = static function ( $text ) {
			return preg_replace( "/\n{2,}/", "\n", $text );
		};

		$this->assertEquals( $normalize( $expectedOutput ), $normalize( $wikiText ) );
	}
}
