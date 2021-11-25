<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\IPreprocessor;

class CDATAClosingFixer implements IPreprocessor {

	private $pattern = '#(<ac:plain-text-body>)(.*?)(</ac:plain-text-body>)#si';

	private $cdataCloser = " [[Category:Broken_CDATA]] ]]>";

	/**
	 * @inheritDoc
	 */
	public function preprocess( string $confluenceHTML ): string {
		$confluenceHTML = preg_replace_callback(
			$this->pattern,
			function ( $matches ) {
				return $this->preprocessPlainTextBody( $matches );
			},
			$confluenceHTML
		);
		return $confluenceHTML;
	}

	/**
	 *
	 * @param array $matches
	 * @return string
	 */
	private function preprocessPlainTextBody( $matches ) {
		$startTag = $matches[1];
		$content = $matches[2];
		$endTag = $matches[3];

		if ( $this->startsWith( $content, '<![CDATA[[' ) ) {
			if ( !$this->endsWith( $content, ']]>' ) ) {
				$content .= $this->cdataCloser;
			}
		}

		return $startTag . $content . $endTag;
	}

	/**
	 *
	 * @param string $text
	 * @param string $prefix
	 * @return bool
	 */
	private function startsWith( $text, $prefix ) {
		return strpos( $text, $prefix ) === 0;
	}

	/**
	 *
	 * @param string $content
	 * @param string $suffix
	 * @return bool
	 */
	private function endsWith( $content, $suffix ) {
		$contentLength = mb_strlen( $content );
		$suffixLength = mb_strlen( $suffix );
		$startIdx = $contentLength - $suffixLength;

		$realSuffix = mb_substr( $content, $startIdx, $suffixLength );
		return $realSuffix === $suffix;
	}
}
