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
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class TableComplexTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/table-complex-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataLookup = new ConversionDataLookup(
			[ 42 => 'ABC', 23 => 'INF' ],
			[
				'23---Sed_do_eiusmod_tempor_incididunt'
					=> 'INF:Sed_do_eiusmod_tempor_incididunt',
			],
			[],
			[],
			[],
			[
				'8a24c45f93bbe67901943c7033640000' => 'UserA',
				'000000005e7f616b01606dc4e2080003' => 'UserB',
			],
			[ 42 => 'ABC', 23 => 'INF' ],
			[],
			[]
		);

		$processors = [
			new PreserveTimeTag(),
			new UserLink( $dataLookup, 42, 'SomePage' ),
			new PageLink( $dataLookup, 42, 'SomePage' ),
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

		$expectedOutput = file_get_contents( "$this->dir/table-complex-output.wikitext" );

		$this->assertEquals( $expectedOutput, $wikiText );
	}
}
