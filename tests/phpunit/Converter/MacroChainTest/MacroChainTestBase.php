<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\AddDisplayTitle;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\EscapePipesInTemplateBody;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixEmptyListItemWrapper;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixImagesWithExternalUrl;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTemplate;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\NestedHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RemoveMultipleLinebreaks;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\TemplateContentPostProcessor;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\HoistMacroFromHeading;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\SanitizeLinkContent;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\Table;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;
use PHPUnit\Framework\TestCase;

abstract class MacroChainTestBase extends TestCase {

	protected DBConversionDataLookup $dataLookup;

	protected PlaceholderManager $placeholderManager;

	protected function setUp(): void {
		$workspaceDb = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$this->dataLookup = new DBConversionDataLookup( $workspaceDb );
		$this->placeholderManager = new PlaceholderManager();
	}

	/**
	 * @param IProcessor|IProcessor[] $processor one processor, or several to run in sequence
	 * @param string $inputXml
	 * @return string
	 */
	protected function runChainWithProcessor( $processor, string $inputXml ): string {
		$inputXml = ltrim( $inputXml );
		$dom = new DOMDocument();
		$dom->loadXML( $inputXml );

		$preprocessors = [
			new SanitizeLinkContent(),
			new HoistMacroFromHeading(),
			new Table(),
		];
		foreach ( $preprocessors as $preprocessor ) {
			$preprocessor->preprocess( $dom );
		}

		foreach ( is_array( $processor ) ? $processor : [ $processor ] as $singleProcessor ) {
			$singleProcessor->process( $dom );
		}

		$wikiText = $this->runPandoc( $dom->saveHTML() );
		$wikiText = $this->placeholderManager->replacePlaceholders( $wikiText );

		$postprocessors = [
			new RestoreExcerptIncludeMacro( $this->dataLookup ),
			new FixLineBreakInHeadings(),
			new FixImagesWithExternalUrl(),
			new NestedHeadings(),
			new FixEmptyListItemWrapper(),
			new FixMultilineTemplate(),
			new EscapePipesInTemplateBody(),
			new FixMultilineTable(),
			new TemplateContentPostProcessor( 'SomePage' ),
			new RemoveMultipleLinebreaks(),
			new AddDisplayTitle( 'SomeConfluenceTitle', 'SomePage' ),
		];
		foreach ( $postprocessors as $postprocessor ) {
			$wikiText = $postprocessor->postprocess( $wikiText );
		}

		return $this->applyConfluenceFinalReplacements( $wikiText );
	}

	/**
	 * Mirrors ConfluenceConverter::postprocessWikiText() replacement steps.
	 *
	 * @param string $wikiText
	 * @return string
	 */
	protected function applyConfluenceFinalReplacements( string $wikiText ): string {
		$wikiText = str_replace( "\r", '', $wikiText );
		$wikiText = str_replace( '###BREAK###', "\n", $wikiText );
		$wikiText = str_replace( "\n {{", "\n{{", $wikiText );
		$wikiText = str_replace( "\n }}", "\n}}", $wikiText );
		$wikiText = str_replace( "\n- ", "\n* ", $wikiText );
		$wikiText = str_replace( ' preserve-attr-data-', ' data-', $wikiText );

		$wikiText = preg_replace_callback(
			[
				"#&lt;img(.*?)/&gt;#s"
			],
			static function ( $matches ) {
				return html_entity_decode( $matches[0] );
			},
			$wikiText
		);

		return $wikiText;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	protected function runPandoc( string $html ): string {
		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		$command = [ 'pandoc', '-f', 'html', '-t', 'mediawiki' ];
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.proc_open
		$proc = proc_open( $command, $descriptors, $pipes );
		$this->assertIsResource( $proc, 'Failed to start pandoc process.' );

		fwrite( $pipes[0], $html );
		fclose( $pipes[0] );

		$output = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$errorOutput = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$exitCode = proc_close( $proc );
		$this->assertSame(
			0,
			$exitCode,
			'pandoc conversion failed. ' . trim( (string)$errorOutput )
		);

		return (string)$output;
	}
}
