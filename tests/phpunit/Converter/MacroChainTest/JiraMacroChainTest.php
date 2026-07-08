<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro;

/**
 * @group full
 */
class JiraMacroChainTest extends MacroChainTestBase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$fixtures = [
			'jira-macro-broken-input.xml' => 'jira-macro-broken-output.wikitext',
			'jira-macro-input.xml' => 'jira-macro-output.wikitext',
			'jira-macro-jql-board-input.xml' => 'jira-macro-jql-board-output.wikitext',
			'jira-macro-jql-calendar-input.xml' => 'jira-macro-jql-calendar-output.wikitext',
			'jira-macro-jql-input.xml' => 'jira-macro-jql-output.wikitext',
			'jira-macro-jql-summary-input.xml' => 'jira-macro-jql-summary-output.wikitext',
			'jira-macro-jql-timeline-input.xml' => 'jira-macro-jql-timeline-output.wikitext',
			'jira-macro-key-wins-input.xml' => 'jira-macro-key-wins-output.wikitext',
		];

		foreach ( $fixtures as $inputFixture => $expectedFixture ) {
			$inputPath = "$dir/$inputFixture";
			$expectedPath = "$dir/$expectedFixture";
			$this->assertFileExists( $inputPath, "Missing input fixture $inputFixture" );
			$this->assertFileExists( $expectedPath, "Missing expected fixture $expectedFixture" );
			$inputXml = (string)file_get_contents( $inputPath );
			$expected = $this->applyConfluenceFinalReplacements( (string)file_get_contents( $expectedPath ) );
			$actual = $this->runChainWithProcessor( $this->createProcessor(), $inputXml );
			$this->assertSame( $expected, $actual, "Mismatch for fixture $inputFixture" );
		}
	}

	/**
	 * @return IProcessor
	 */
	private function createProcessor(): IProcessor {

		return new JiraMacro();
	}

}
