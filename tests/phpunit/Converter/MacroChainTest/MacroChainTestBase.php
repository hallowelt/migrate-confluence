<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\AddDisplayTitle;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\CodeMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\EscapePipesInTemplateBody;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixEmptyListItemWrapper;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixImagesWithExternalUrl;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixLineBreakInHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTemplate;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\NestedHeadings;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RemoveMultipleLinebreaks;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestorePStyleTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTimeTag;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\TasksReportMacro;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\TemplateContentPostProcessor;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\HoistMacroFromHeading;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\SanitizeLinkContent;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\Table;
use PHPUnit\Framework\TestCase;

abstract class MacroChainTestBase extends TestCase {

	/**
	 * @param IProcessor $processor
	 * @param string $inputXml
	 * @return string
	 */
	protected function runChainWithProcessor( IProcessor $processor, string $inputXml ): string {
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

		$processor->process( $dom );

		$wikiText = $this->runPandoc( $dom->saveHTML() );

		$postprocessors = [
			new RestorePStyleTag(),
			new RestoreExcerptMacro(),
			new RestoreTimeTag(),
			new FixLineBreakInHeadings(),
			new FixImagesWithExternalUrl(),
			new CodeMacro(),
			new NestedHeadings(),
			new FixEmptyListItemWrapper(),
			new TasksReportMacro(),
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
		$wikiText = str_replace( '###HTMLCOMMENTOPEN###', '<!-- ', $wikiText );
		$wikiText = str_replace( '###HTMLCOMMENTCLOSE###', ' -->', $wikiText );
		$wikiText = str_replace( "\n {{", "\n{{", $wikiText );
		$wikiText = str_replace( "\n }}", "\n}}", $wikiText );
		$wikiText = str_replace( "\n- ", "\n* ", $wikiText );
		$wikiText = str_replace( ' preserve-attr-data-', ' data-', $wikiText );

		$wikiText = preg_replace_callback(
			[
				"#&lt;headertabs /&gt;#si",
				"#&lt;subpages(.*?)/&gt;#si",
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
	private function runPandoc( string $html ): string {
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
