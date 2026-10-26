<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\BgcolorMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\CenterMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\CheeseMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ClickableMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ColgroupMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\DivMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\HighlightMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\HtmlcommentMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\LiMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\LozengeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\OlMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\PreMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\PrivacyMarkMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\PrivacyPolicyMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\RoundrectMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\SpanMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\StrikeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TableMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TbodyMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TdMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TheadMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ThMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\TrMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\UlMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\WelcomeMessageMacro;

/**
 * @group full
 */
class SimpleMacrosChainTest extends MacroChainTestBase {

	/**
	 * @param IProcessor|IProcessor[] $processor
	 * @param string $macroXml
	 * @param string $expected
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\HtmlTagMacroBase::process
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ConvertMacroToTemplateBase::process
	 * @dataProvider provideCases
	 * @return void
	 */
	public function testMacroChain( $processor, string $macroXml, string $expected ): void {
		if ( $processor instanceof \Closure ) {
			$processor = $processor( $this->placeholderManager, $this->dataLookup );
		}
		$xml = '<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace"><p>' . $macroXml . '</p></xml>';
		$actual = $this->runChainWithProcessor( $processor, $xml );
		$this->assertSame( $expected . "\n\n{{DISPLAYTITLE:SomeConfluenceTitle|noreplace}}", $actual );
	}

	/**
	 * @return array
	 */
	public function provideCases(): array {
		$w = $this->createConverterDataWriter();
		return [
			'bgcolor' => [
				new BgcolorMacro(),
				'<ac:structured-macro ac:name="bgcolor"><ac:parameter ac:name="color">red</ac:parameter>'
					. '<ac:rich-text-body><p>text</p></ac:rich-text-body></ac:structured-macro>',
				"<div style=\"background-color: red;\">\n\ntext\n\n\n</div>"
			],
			'bgcolor-default' => [
				new BgcolorMacro(),
				'<ac:structured-macro ac:name="bgcolor">'
					. '<ac:rich-text-body><p>text</p></ac:rich-text-body></ac:structured-macro>',
				"<div style=\"background-color: green;\">\n\ntext\n\n\n</div>"
			],
			'center' => [
				new CenterMacro(),
				'<ac:structured-macro ac:name="center">'
					. '<ac:rich-text-body><p>text</p></ac:rich-text-body></ac:structured-macro>',
				"<div style=\"text-align: center;\">\n\ntext\n\n\n</div>"
			],
			'highlight' => [
				new HighlightMacro(),
				'a <ac:structured-macro ac:name="highlight"><ac:parameter ac:name="color">blue</ac:parameter>'
					. '<ac:rich-text-body>text</ac:rich-text-body></ac:structured-macro> b',
				'a <span style="background-color: blue;">text</span> b'
			],
			'div' => [
				new DivMacro(),
				'<ac:structured-macro ac:name="div"><ac:parameter ac:name="class">box</ac:parameter>'
					. '<ac:parameter ac:name="style">color: red;</ac:parameter>'
					. '<ac:rich-text-body><p>text</p></ac:rich-text-body></ac:structured-macro>',
				"<div class=\"box\" style=\"color: red;\">\n\ntext\n\n\n</div>"
			],
			'span' => [
				new SpanMacro(),
				'a <ac:structured-macro ac:name="span"><ac:parameter ac:name="title">tip</ac:parameter>'
					. '<ac:rich-text-body>text</ac:rich-text-body></ac:structured-macro> b',
				'a <span title="tip">text</span> b'
			],
			'strike' => [
				new StrikeMacro(),
				'a <ac:structured-macro ac:name="strike">'
					. '<ac:rich-text-body>text</ac:rich-text-body></ac:structured-macro> b',
				'a <s>text</s> b'
			],
			'pre' => [
				static fn ( $pm ) => new PreMacro( $pm ),
				'<ac:structured-macro ac:name="pre"><ac:rich-text-body>line</ac:rich-text-body></ac:structured-macro>',
				'<pre>line</pre>'
			],
			'pre-whitespace-and-class' => [
				static fn ( $pm ) => new PreMacro( $pm ),
				'<ac:structured-macro ac:name="pre"><ac:parameter ac:name="class">c</ac:parameter>'
					. '<ac:plain-text-body><![CDATA[a <b> &' . "\n  c]]>"
					. '</ac:plain-text-body></ac:structured-macro>',
				"<pre class=\"c\">a &lt;b&gt; &amp;\n  c</pre>"
			],
			'pre-markup' => [
				static fn ( $pm ) => new PreMacro( $pm ),
				'<ac:structured-macro ac:name="pre"><ac:rich-text-body>a <strong>b</strong></ac:rich-text-body>'
					. '</ac:structured-macro>',
				"<pre>a '''b'''</pre>"
			],
			'clickable' => [
				new ClickableMacro(),
				'<ac:structured-macro ac:name="clickable"><ac:parameter ac:name="link">http://example.org'
					. '</ac:parameter>'
					. '<ac:rich-text-body>click</ac:rich-text-body></ac:structured-macro>',
				'[http://example.org click]'
			],
			'htmlcomment' => [
				static fn ( $pm ) => new HtmlcommentMacro( $pm ),
				'a <ac:structured-macro ac:name="htmlcomment"><ac:rich-text-body>hidden -- text</ac:rich-text-body>'
					. '</ac:structured-macro> b',
				'a <!-- hidden - - text --> b'
			],
			'colgroup-keeps-cells' => [
				[ new TableMacro(), new ColgroupMacro(), new TbodyMacro(), new TrMacro(), new TdMacro() ],
				'<ac:structured-macro ac:name="table"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="colgroup"><ac:parameter ac:name="span">2</ac:parameter>'
					. '<ac:parameter ac:name="width">100</ac:parameter></ac:structured-macro>'
					. '<ac:structured-macro ac:name="tbody"><ac:rich-text-body><ac:structured-macro ac:name="tr">'
					. '<ac:rich-text-body><ac:structured-macro ac:name="td"><ac:rich-text-body>a</ac:rich-text-body>'
					. '</ac:structured-macro><ac:structured-macro ac:name="td"><ac:rich-text-body>b</ac:rich-text-body>'
					. '</ac:structured-macro></ac:rich-text-body></ac:structured-macro></ac:rich-text-body>'
					. '</ac:structured-macro></ac:rich-text-body></ac:structured-macro>',
				"{| class=\"wikitable\"\n|-\n| a\n| b\n|}"
			],
			'lists' => [
				[ new UlMacro(), new OlMacro(), new LiMacro() ],
				'<ac:structured-macro ac:name="ul"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="li">'
						. '<ac:rich-text-body>one</ac:rich-text-body></ac:structured-macro>'
					. '<ac:structured-macro ac:name="li">'
						. '<ac:rich-text-body>two</ac:rich-text-body></ac:structured-macro>'
					. '</ac:rich-text-body></ac:structured-macro>',
				"* one\n* two"
			],
			'table' => [
				[
					new TableMacro(), new TheadMacro(), new TbodyMacro(), new TrMacro(), new ThMacro(), new TdMacro()
				],
				'<ac:structured-macro ac:name="table"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="thead"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="tr"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="th"><ac:rich-text-body>H</ac:rich-text-body></ac:structured-macro>'
					. '</ac:rich-text-body></ac:structured-macro></ac:rich-text-body></ac:structured-macro>'
					. '<ac:structured-macro ac:name="tbody"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="tr"><ac:rich-text-body>'
					. '<ac:structured-macro ac:name="td"><ac:parameter ac:name="colspan">1</ac:parameter>'
					. '<ac:rich-text-body>C</ac:rich-text-body></ac:structured-macro>'
					. '</ac:rich-text-body></ac:structured-macro></ac:rich-text-body></ac:structured-macro>'
					. '</ac:rich-text-body></ac:structured-macro>',
				"{| class=\"wikitable\"\n|-\n! H\n|-\n| C\n|}"
			],
			'cheese' => [
				new CheeseMacro( $w, 1 ),
				'<ac:structured-macro ac:name="cheese">'
					. '<ac:rich-text-body>Gouda!</ac:rich-text-body></ac:structured-macro>',
				'{{Cheese|body = Gouda!}}'
			],
			'lozenge' => [
				new LozengeMacro( $w, 1 ),
				'<ac:structured-macro ac:name="lozenge"><ac:parameter ac:name="title">Done</ac:parameter>'
					. '<ac:parameter ac:name="color">green</ac:parameter></ac:structured-macro>',
				'{{Lozenge|title = Done|color = green}}'
			],
			'roundrect' => [
				new RoundrectMacro( $w, 1 ),
				'<ac:structured-macro ac:name="roundrect"><ac:parameter ac:name="title">T</ac:parameter>'
					. '<ac:rich-text-body><p>text</p></ac:rich-text-body></ac:structured-macro>',
				"{{RoundRect\n|title = T\n|body = \n\n\ntext\n\n}}\n"
			],
			'privacy-mark' => [
				new PrivacyMarkMacro( $w, 1 ),
				'<ac:structured-macro ac:name="privacy-mark"><ac:parameter ac:name="title">PII</ac:parameter>'
					. '</ac:structured-macro>',
				'{{PrivacyMark|title = PII}}'
			],
			'privacy-policy' => [
				static fn ( $pm, $lookup ) => new PrivacyPolicyMacro( $w, 42, $lookup ),
				'<ac:structured-macro ac:name="privacy-policy"></ac:structured-macro>',
				"{{PrivacyPolicy\n}}\n"
			],
			'privacy-policy-page-current-space' => [
				static fn ( $pm, $lookup ) => new PrivacyPolicyMacro( $w, 42, $lookup ),
				'<ac:structured-macro ac:name="privacy-policy"><ac:parameter ac:name="page">Some page</ac:parameter>'
					. '<ac:rich-text-body><p>We log nothing.</p></ac:rich-text-body></ac:structured-macro>',
				"{{PrivacyPolicy\n|page = ABC:Some_page\n|body = \n\n\nWe log nothing.\n\n}}\n"
			],
			'privacy-policy-page-other-space' => [
				static fn ( $pm, $lookup ) => new PrivacyPolicyMacro( $w, 42, $lookup ),
				'<ac:structured-macro ac:name="privacy-policy"><ac:parameter ac:name="page">MKT:Brand Assets'
					. '</ac:parameter>'
					. '<ac:rich-text-body><p>We log nothing.</p></ac:rich-text-body></ac:structured-macro>',
				"{{PrivacyPolicy\n|page = MKT:Brand_Assets\n|body = \n\n\nWe log nothing.\n\n}}\n"
			],
			'privacy-policy-page-unknown' => [
				static fn ( $pm, $lookup ) => new PrivacyPolicyMacro( $w, 42, $lookup ),
				'<ac:structured-macro ac:name="privacy-policy"><ac:parameter ac:name="page">Missing</ac:parameter>'
					. '<ac:rich-text-body><p>We log nothing.</p></ac:rich-text-body></ac:structured-macro>',
				"{{PrivacyPolicy\n|page = Confluence_page---42---Missing\n|body = \n\n\nWe log nothing.\n\n}}\n"
			],
			'welcome-message' => [
				new WelcomeMessageMacro( $w, 1 ),
				'<ac:structured-macro ac:name="welcome-message"></ac:structured-macro>',
				'{{WelcomeMessage}}'
			],
		];
	}
}
