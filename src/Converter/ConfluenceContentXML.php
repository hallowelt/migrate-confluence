<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML;
use SplFileInfo;

class ConfluenceContentXML extends PandocHTML {

	protected $bodyContentFile = null;

	/**
	 *
	 * @var SplFileInfo
	 */
	private $rawFile = null;

	/**
	 *
	 * @var SplFileInfo
	 */
	private $preprocessedFile = null;

	protected function doConvert( SplFileInfo $file ): string {
		$this->rawFile = $file;
		$dom = $this->preprocessFile();
		$this->wikiText = parent::doConvert( $this->preprocessedFile );
		$this->postprocessWikiText();
		return $this->wikiText;

		$tables = $dom->getElementsByTagName( 'table' );
		foreach( $tables as $table ) {
			$classAttr = $table->getAttribute( 'class' );
			if( $classAttr === '' ) {
				$table->setAttribute( 'class', 'wikitable' );
			}
		}

		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace('ac', 'some');
		$xpath->registerNamespace('ri', 'thing');
		$replacings = $this->makeReplacings();
		foreach( $replacings as $xpathQuery => $callback ) {
			$matches = $xpath->query( $xpathQuery );
			$nonLiveListMatches = array();
			foreach( $matches as $match ) {
				$nonLiveListMatches[] = $match;
			}
			foreach( $nonLiveListMatches as $match ) {
				//See: https://wiki.hallowelt.com/index.php/Technik/Migration/Confluence_nach_MediaWiki#Inhalte
				//See: https://confluence.atlassian.com/doc/confluence-storage-format-790796544.html
				call_user_func_array(
					$callback,
					array( $this, $match, $dom, $xpath )
				);
			}
		}

		$this->postProcessDOM( $dom, $xpath );

		$dom->saveHTMLFile(
			$this->bodyContentFile->getPathname()
		);
	}

	private function preprocessFile() {
		$source = $this->preprocessHTMLSource( $this->rawFile );
		$dom = new DOMDocument();
		$dom->recover = true;
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = true;
		$dom->validateOnParse = false;
		$dom->loadXML( $source );

		$preprocessedPathname = str_replace( '.mraw', '.mprep', $this->rawFile->getPathname() );
		$dom->saveHTMLFile( $preprocessedPathname );
		$this->preprocessedFile = new SplFileInfo( $preprocessedPathname );

		return $dom;
	}

	public function getWikiText() {
		if( $this->bodyContentFile === null || !$this->bodyContentFile->isReadable() ) {
			return '';
		}
		$sourceFileName = $this->bodyContentFile->getFilename(); //54789357.html
		$fileNameParts = explode( '.', $sourceFileName ); //array( "54789357", "html" )
		array_pop( $fileNameParts ); //array( "54789357" )

		$targetFileName = implode( '.', $fileNameParts ).'.wiki';
		$targetDirectory = dirname( $this->bodyContentFile->getPath() ).'/wiki/';
		$targetFile = new SplFileInfo(
			$targetDirectory . $targetFileName
		);

		/*
		 * Use pandoc to do the HTML > MediaWiki conversion!
		 * http://pandoc.org/
		 */
		$sCmd = sprintf(
			'pandoc %s -f html -t mediawiki -o %s',
			$this->bodyContentFile->getPathname(),
			$targetFile->getPathname()
		);

		$retval = null;
		$result = wfShellExec( $sCmd, $retval );
		if( $retval !== 0 ) {
			$this->log( $retval );
		}
		if( !empty( $result ) ) {
			$this->log( $result );
		}

		$this->wikiText = file_get_contents( $targetFile->getPathname() );
		$this->postProcessWikiText( $this->wikiText );

		return $this->wikiText;
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	private function processStructuredMacro( $sender, $match, $dom, $xpath ) {
		$this->processMacro($sender, $match, $dom, $xpath);
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	private function processMacro( $sender, $match, $dom, $xpath ) {
		$replacement = '';
		$sMacroName = $match->getAttribute('ac:name');

		if( $sMacroName === 'gliffy' ) {
			$this->processGliffyMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'localtabgroup' || $sMacroName === 'localtab' ) {
			$this->processLocalTabMacro( $sender, $match, $dom, $xpath, $replacement, $sMacroName );
		}
		elseif( $sMacroName === 'excerpt' ) {
			$this->processExcerptMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'code' ) {
			$this->processCodeMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'viewdoc' || $sMacroName === 'viewxls' || $sMacroName === 'viewpdf' ) {
			$this->processViewXMacro( $sender, $match, $dom, $xpath, $replacement, $sMacroName );
		}
		elseif( $sMacroName === 'children' ) {
			$this->processChildrenMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'widget' ) {
			$this->processWidgetMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'section' ) {
			$this->processSectionMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'column' ) {
			$this->processColumnMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'recently-updated' ) {
			$this->processRecentlyUpdatedMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'tasklist' ) {
			$this->processTaskListMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'info' ) {
			$this->processInfoMacro( $sender, $match, $dom, $xpath, $replacement );
		}
		elseif( $sMacroName === 'toc' ) {
			$replacement = "\n__TOC__\n###BREAK###";
		}
		else {
			//TODO: 'calendar', 'contributors', 'anchor',
			// 'pagetree', 'navitabs', 'include', 'info', 'listlabels',
			// status'
			#$this->log( "Unknown makro '$sMacroName' found in {$file->getPathname()}!" );
			#$this->logMarkup( $match );
		}
		$replacement .= "[[Category:Broken_macro/$sMacroName]]";

		$this->notify( 'processMacro', array( $match, $dom, $xpath, &$replacement, $sMacroName ) );

		$match->parentNode->replaceChild(
			$dom->createTextNode( $replacement ),
			$match
		);
	}

	/**
	 *
	 * @param DOMNode $oNode
	 */
	protected function logMarkup( $oNode ) {
		if( $oNode instanceof DOMElement === false ) {
			return;
		}

		error_log(
			"NODE: \n".
			$oNode->ownerDocument->saveXML( $oNode )
		);
	}

	public function makeReplacings() {
		return array(
			'//ac:link' => array( $this, 'processLink' ),
			'//ac:image' => array( 'Image', 'process' ),
			#'//ac:layout' => array( $this, 'processLayout' ),
			'//ac:macro' => array( $this, 'processMacro' ),
			'//ac:structured-macro' => array( $this, 'processStructuredMacro' ),
			'//ac:emoticon' => array( $this, 'processEmoticon' ),
			'//ac:task-list' => array( $this, 'processTaskList' ),
			'//ac:inline-comment-marker' => array( $this, 'processInlineCommentMarker' ),
		);
	}

	/**
	 *
	 * @param SplFileInfo $oHTMLSourceFile
	 * @return string
	 */
	protected function preprocessHTMLSource( $oHTMLSourceFile ) {
		$sContent = file_get_contents( $oHTMLSourceFile->getPathname() );

		/**
		 * As this is a mixture of XML and HTML the XMLParser has trouble
		 * with entities from HTML. To circumvent this we replace all entites
		 * by their literal. A better solution would be to make the entities
		 * known to the XMLParser, e.g. by using a DTD.
		 * This is something for a future development...
		 */
		$aReplaces = array_flip( get_html_translation_table( HTML_ENTITIES ) );
		unset( $aReplaces['&amp;'] );
		unset( $aReplaces['&lt;'] );
		unset( $aReplaces['&gt;'] );
		unset( $aReplaces['&quot;'] );
		foreach ($aReplaces as $sEntity => $replacement ) {
			$sContent = str_replace( $sEntity, $replacement, $sContent );
		}

		//For now we just replace the layout markup of Confluence with simple
		//HTML div markup
		$sContent = str_replace( '<ac:layout-section ac:type="', '<div class="ac-layout-section ', $sContent ); //"ac:layout-section" is the only one with a "ac:type" attribute
		$sContent = str_replace( '<ac:layout-section', '<div class="ac-layout-section"', $sContent );
		$sContent = str_replace( '</ac:layout-section', '</div', $sContent );

		$sContent = str_replace( '<ac:layout-cell', '<div class="ac-layout-cell"', $sContent );
		$sContent = str_replace( '</ac:layout-cell', '</div', $sContent );

		$sContent = str_replace( '<ac:layout', '<div class="ac-layout"', $sContent );
		$sContent = str_replace( '</ac:layout', '</div', $sContent );

		#$sContent = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'
		$sContent = '<xml xmlns:ac="some" xmlns:ri="thing" xmlns:bs="bluespice">'.$sContent.'</xml>';

		return $sContent;
	}

	/**
	 *
<ac::macro ac:name="localtabgroup">
	<ac::rich-text-body>
		<ac::macro ac:name="localtab">
			<ac::parameter ac:name="title">...</acparameter>
			<ac::rich-text-body>...</acrich-text-body>
		</ac:macro>
	</ac:rich-text-body>
</ac:macro>
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 * @param string $sMacroName
	 */
	private function processLocalTabMacro($sender, $match, $dom, $xpath, &$replacement, $sMacroName) {
		if( $sMacroName === 'localtabgroup' ) {
			//Append the "<headertabs />" tag
			$match->parentNode->appendChild(
				$dom->createTextNode('<headertabs />')
			);
		}
		elseif ( $sMacroName === 'localtab' ) {
			$oTitleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item(0);
			//Prepend the heading
			$match->parentNode->insertBefore(
				$dom->createElement('h1', $oTitleParam->nodeValue ),
				$match
			);
		}

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
		//Move all content out of <ac:rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item(0);
			$match->parentNode->insertBefore( $oChild, $match );
		}
	}

	/**
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processExcerptMacro($sender, $match, $dom, $xpath, &$replacement) {
		$oNewContainer = $dom->createElement( 'div' );
		$oNewContainer->setAttribute( 'class', 'ac-excerpt' );

		//TODO: reflect modes "INLINE" and "BLOCK"
		//See https://confluence.atlassian.com/doc/excerpt-macro-148062.html

		$match->parentNode->insertBefore( $oNewContainer, $match );

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
		//Move all content out of <ac::rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item(0);
			$oNewContainer->appendChild( $oChild );
		}
	}

	/**
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processCodeMacro($sender, $match, $dom, $xpath, &$replacement) {
		$titleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item(0);
		$oLanguageParam = $xpath->query( './ac:parameter[@ac:name="language"]', $match )->item(0);
		$sLanguage = '';
		if($oLanguageParam instanceof DOMElement) {
			$sLanguage = $oLanguageParam->nodeValue;
		}

		$oPlainTextBody = $xpath->query( './ac:plain-text-body', $match )->item(0);
		$sContent = $oPlainTextBody->nodeValue;

		$syntaxhighlight = $dom->createElement( 'syntaxhighlight', $sContent );
		if( $sLanguage !== '' ) {
			$syntaxhighlight->setAttribute( 'lang', $sLanguage );
		}
		if( empty( $sContent ) ) {
			error_log("CODE: '$sLanguage': $sContent in {$file->getPathname()}");
			$this->logMarkup( $dom->documentElement );
		}
		if( $titleParam instanceof DOMElement ) {
			$match->parentNode->insertBefore(
				$dom->createElement( 'h6', $titleParam->nodeValue ),
				$match
			);
		}

		$match->parentNode->insertBefore( $syntaxhighlight, $match );
	}

	/**
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 * @param string $sMacroName
	 */
	private function processViewXMacro($sender, $match, $dom, $xpath, &$replacement, $sMacroName ) {
		$oNameParam = $xpath->query( './ac:parameter[@ac:name="name"]', $match )->item(0);
		$oRIAttachmentEl = $xpath->query( './ac:parameter/ri:attachment', $match )->item(0);
		if($oNameParam instanceof DOMElement) {
			$sTargetFile = $oNameParam->nodeValue;
			//Sometimes the target is not the direct nodeValue but an
			//atttribute value of a child <ri::attachment> element
			if( empty( $sTargetFile ) && $oRIAttachmentEl instanceof DOMElement ) {
				$sTargetFile = $oRIAttachmentEl->getAttribute( 'ri:filename' );
			}
			if( empty( $sTargetFile ) ) {
				$this->log( 'EMPTY NAME!' );
				$this->logMarkup( $match );
			}
			$oContainer = $dom->createElement(
				'span',
				$this->makeMediaLink( array( $sTargetFile ) )
			);
			$oContainer->setAttribute( 'class', "ac-$sMacroName" );
			$match->parentNode->insertBefore( $oContainer, $match );
		}
	}

	/**
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processChildrenMacro($sender, $match, $dom, $xpath, &$replacement) {
		$iDepth = 1;
		//Looking for<ac:parameter ac:name="depth">2</ac:parameter>
		$oDepthParam = $xpath->query( './ac:parameter[@ac:name="depth"]', $match )->item(0);
		if( $oDepthParam instanceof DOMNode ) {
			$iDepth = (int) $oDepthParam->nodeValue;
		}

		//https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$oElement = $match->ownerDocument->createElement( 'div' );
		$oElement->setAttribute( 'class', 'subpagelist subpagelist-depth-'.$iDepth );
		$oElement->appendChild(
			$match->ownerDocument->createTextNode(
				'{{SubpageList|page='.$this->sCurrentPageTitle.'|depth='.$iDepth.'}}'
			)
		);
		$match->parentNode->insertBefore( $oElement, $match );
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processGliffyMacro($sender, $match, $dom, $xpath, &$replacement) {
		$oNameParam = $xpath->query( './ac:parameter[@ac:name="name"]', $match )->item(0);
		if( empty( $oNameParam->nodeValue ) ) {
			$this->log( "Gliffy: Missing name!" );
			$this->logMarkup( $match );
		}
		$replacement = $this->makeImageLink( array( "{$oNameParam->nodeValue}.png" ) );
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processWidgetMacro($sender, $match, $dom, $xpath, &$replacement) {
		$oParamEls = $xpath->query( './ac:parameter', $match );
		$params = array(
			'url' => ''
		);
		foreach( $oParamEls as $oParamEl ) {
			$params[$oParamEl->getAttribute('ac:name')] = $oParamEl->nodeValue;
		}
		$oContainer = $dom->createElement( 'div', $params['url'] );
		$oContainer->setAttribute( 'class', "ac-widget" );
		$oContainer->setAttribute( 'data-params', FormatJson::encode( $params ) );
		$match->parentNode->insertBefore( $oContainer, $match );
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processSectionMacro($sender, $match, $dom, $xpath, &$replacement) {
		$oNewContainer = $dom->createElement( 'div' );
		$oNewContainer->setAttribute( 'class', 'ac-section' );

		$match->parentNode->insertBefore( $oNewContainer, $match );

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
		//Move all content out of <ac::rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item(0);
			$oNewContainer->appendChild( $oChild );
		}
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processColumnMacro($sender, $match, $dom, $xpath, &$replacement) {
		$oNewContainer = $dom->createElement( 'div' );
		$oNewContainer->setAttribute( 'class', 'ac-column' );

		$match->parentNode->insertBefore( $oNewContainer, $match );

		$oParamEls = $xpath->query( './ac:parameter', $match );
		$params = array();

		foreach( $oParamEls as $oParamEl ) {
			$params[$oParamEl->getAttribute('ac:name')] = $oParamEl->nodeValue;
		}
		$oNewContainer->setAttribute( 'data-params', FormatJson::encode( $params ) );

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
		//Move all content out of <ac::rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item(0);
			$oNewContainer->appendChild( $oChild );
		}
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processTaskListMacro($sender, $match, $dom, $xpath, &$replacement) {
		$this->processTaskList( $sender, $match, $dom, $xpath );
	}

	/**
	 *
	<ac:task-list>
		<ac:task>
			<ac:task-id>29</ac:task-id>
			<ac:task-status>incomplete</ac:task-status>
			<ac:task-body><strong>Edit this home page</strong> - Click <em>Edit</em> ...</ac:task-body>
		</ac:task>
		<ac:task>
			<ac:task-id>30</ac:task-id>
			<ac:task-status>incomplete</ac:task-status>
			<ac:task-body><strong>Create your first page</strong> - Click the <em>Create</em> ...</ac:task-body>
		<ac:task>
	</ac:task-list>
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	private function processTaskList($sender, $match, $dom, $xpath) {
		$wikiText = [];
		$tasks = $match->getElementsByTagName( 'task' );

		$wikiText[] = '{{TaskList/Start}}###BREAK###';
		foreach( $tasks as $task ) {
			$elId = $task->getElementsByTagName( 'task-id' )->item( 0 );
			$elStatus = $task->getElementsByTagName( 'task-status' )->item( 0 );
			$elBody = $task->getElementsByTagName( 'task-body' )->item( 0 );

			$id = $elId instanceof DOMElement ? $elId->nodeValue : -1 ;
			$status = $elStatus instanceof DOMElement ? $elStatus->nodeValue : '' ;
			$body = $elBody instanceof DOMElement ? $dom->saveXML( $elBody ) : '' ;
			$body = str_replace( ['<ac:task-body>', '</ac:task-body>'], '', $body );

			$wikiText[] = <<<HERE
{{Task###BREAK###
 | id = $id###BREAK###
 | status = $status###BREAK###
 | body = $body###BREAK###
}}###BREAK###
HERE;
		}
		$wikiText[] = '{{TaskList/End}}###BREAK###';
		$wikiText = implode( "\n", $wikiText );

		$match->parentNode->replaceChild(
			$dom->createTextNode( $wikiText ),
			$match
		);
	}

	/**
	 * <ac:inline-comment-marker ac:ref="ca3f84d8-5618-4cdb-b8f6-b58f4e29864e">
	 *	Alternatives
	 * </ac:inline-comment-marker>
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	private function processInlineCommentMarker($sender, $match, $dom, $xpath) {
		$wikiText = "{{InlineComment|{$match->nodeValue}}}";
		$match->parentNode->replaceChild(
			$dom->createTextNode( $wikiText ),
			$match
		);
	}

	/**
	 * <ac:structured-macro ac:name="info" ac:schema-version="1" ac:macro-id="448329ba-06ad-4845-b3bf-2fd9a75c0d51">
	 *	<ac:parameter ac:name="title">/api/Device/devices</ac:parameter>
	 *	<ac:rich-text-body>
	 *		<p class="title">...</p>
	 *		<p>...</p>
	 *	</ac:rich-text-body>
	 * </ac:structured-macro>
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processInfoMacro($sender, $match, $dom, $xpath, &$replacement) {
		$oTitleParam = $xpath->query( './ac:parameter[@ac:name="title"]', $match )->item(0);

		$oNewContainer = $dom->createElement( 'div' );
		$oNewContainer->setAttribute( 'class', 'ac-info' );

		$match->parentNode->insertBefore( $oNewContainer, $match );

		if( $oTitleParam instanceof DOMElement ) {
			$oNewContainer->appendChild(
				$dom->createElement('h3', $oTitleParam->nodeValue )
			);
		}

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item(0);
		//Move all content out of <ac::rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item(0);
			$oNewContainer->appendChild( $oChild );
		}
	}

	/**
	 *
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 */
	public function postProcessDOM( $dom, $xpath ) {
		/*
		 * BlueSpice VisualEditor breaks on <div>'s with data attributes
		 * containing JSON
		 */
		$oElementsWithDataAttr = $xpath->query( '//*[@data-atlassian-layout]' );
		foreach( $oElementsWithDataAttr as $oElementWithDataAttr ) {
			$oElementWithDataAttr->setAttribute( 'data-atlassian-layout', null );
		}

		$this->notify('postProcessDOM', array( $dom, $xpath ) );
	}

	/**
	 *
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @param string $replacement
	 */
	private function processRecentlyUpdatedMacro( $sender, $match, $dom, $xpath, &$replacement ) {
		$sNsText = '';
		$aTitleParts = explode( ':', $this->sCurrentPageTitle, 2 );
		if( count( $aTitleParts ) === 2 ) {
			$sNsText = $aTitleParts[0];
		}
		$replacement = sprintf(
			'{{RecentlyUpdatedMacro|namespace=%s}}',
			$sNsText
		);
	}

	public function postprocessWikiText() {
		//On Windows the CR would be encoded as "&#xD;" in the MediaWiki-XML, which is ulgy and unnecessary
		$this->wikiText = str_replace( "\r", '', $this->wikiText );
		$this->wikiText = str_replace( "###BREAK###", "\n", $this->wikiText );
		$this->wikiText = str_replace( "\n {{", "\n{{", $this->wikiText );
		$this->wikiText = str_replace( "\n }}", "\n}}", $this->wikiText );
		$this->wikiText = str_replace( "\n- ", "\n* ", $this->wikiText );
		$this->wikiText = preg_replace_callback(
			array(
				"#&lt;headertabs /&gt;#si",
				"#&lt;subpages(.*?)/&gt;#si",
				"#&lt;img(.*?)/&gt;#s"
			),
			function( $aMatches ) {
				return html_entity_decode( $aMatches[0] );
			},
			$this->wikiText
		);

		$this->wikiText .= "\n <!-- From bodyContent {$this->rawFile->getBasename()} -->";
	}
}