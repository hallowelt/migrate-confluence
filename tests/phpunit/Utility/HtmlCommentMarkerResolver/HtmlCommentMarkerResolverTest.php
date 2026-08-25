<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\HtmlCommentMarkerResolver;

use HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver
 */
class HtmlCommentMarkerResolverTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver::resolve
	 */
	public function testResolvesASinglePairIntoAnHtmlComment(): void {
		$input = 'before ###HTMLCOMMENTOPEN### some debug text ###HTMLCOMMENTCLOSE### after';
		$expected = 'before <!--  some debug text  --> after';

		$this->assertSame( $expected, HtmlCommentMarkerResolver::resolve( $input ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver::resolve
	 */
	public function testResolvesMultipleIndependentPairs(): void {
		$input = '###HTMLCOMMENTOPEN###a###HTMLCOMMENTCLOSE### text '
			. '###HTMLCOMMENTOPEN###b###HTMLCOMMENTCLOSE###';
		$expected = '<!-- a --> text <!-- b -->';

		$this->assertSame( $expected, HtmlCommentMarkerResolver::resolve( $input ) );
	}

	/**
	 * Regression: an unresolved "create-from-template" macro nested inside an
	 * otherwise-unhandled "scroll-ignore" macro produces nested marker pairs.
	 * Real HTML comments cannot be nested, so only the outermost pair must
	 * become an actual comment; the inner pair's markers are dropped while its
	 * text is kept and merged into the single outer comment.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver::resolve
	 */
	public function testFlattensNestedMarkerPairsIntoASingleComment(): void {
		$input = 'before ###HTMLCOMMENTOPEN### outer start '
			. '{{Foo}}###HTMLCOMMENTOPEN### Template could not be found (templateId: 123) ###HTMLCOMMENTCLOSE###'
			. "\n[[Category:Broken_macro/create-from-template]] outer end ###HTMLCOMMENTCLOSE###"
			. '[[Category:Broken_macro/scroll-ignore]] after';

		$expected = 'before <!--  outer start {{Foo}} Template could not be found (templateId: 123) '
			. "\n[[Category:Broken_macro/create-from-template]] outer end  -->"
			. '[[Category:Broken_macro/scroll-ignore]] after';

		$actual = HtmlCommentMarkerResolver::resolve( $input );

		$this->assertSame( $expected, $actual );

		// The output must contain exactly one "<!--" and one "-->", i.e. a single,
		// valid, non-nested HTML comment; not the double markers from the bug report.
		$this->assertSame( 1, substr_count( $actual, '<!--' ) );
		$this->assertSame( 1, substr_count( $actual, '-->' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver::resolve
	 */
	public function testTextWithoutMarkersIsLeftUnchanged(): void {
		$input = 'Just plain wikitext with no markers at all.';

		$this->assertSame( $input, HtmlCommentMarkerResolver::resolve( $input ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\HtmlCommentMarkerResolver::resolve
	 */
	public function testDanglingOpenMarkerIsClosedDefensively(): void {
		$input = 'before ###HTMLCOMMENTOPEN### unterminated debug text';
		$expected = 'before <!--  unterminated debug text -->';

		$this->assertSame( $expected, HtmlCommentMarkerResolver::resolve( $input ) );
	}

}
