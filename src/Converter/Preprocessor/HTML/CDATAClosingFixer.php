<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\HTML;

use HalloWelt\MigrateConfluence\Converter\IHtmlPreprocessor;

class CDATAClosingFixer implements IHtmlPreprocessor {

	/** @var string */
	private string $pattern = '#(<ac:plain-text-body>)(.*?)(</ac:plain-text-body>)#si';

	/** @var string */
	private string $linkBodyPattern = '#(<ac:plain-text-link-body>)(.*?)(</ac:plain-text-link-body>)#si';

	/** @var string */
	private string $cdataCloser = " [[Category:Broken_CDATA]] ]]>";

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
		$confluenceHTML = preg_replace_callback(
			$this->linkBodyPattern,
			function ( $matches ) {
				return $this->preprocessLinkBody( $matches );
			},
			$confluenceHTML
		);
		return $confluenceHTML;
	}

	/**
	 * Confluence exports a link label ending in "]" with a mangled CDATA
	 * closer "] ]>" instead of "]]>"; restores the closer.
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	private function preprocessLinkBody( array $matches ): string {
		$startTag = $matches[1];
		$content = $matches[2];
		$endTag = $matches[3];
		if ( !str_starts_with( $content, '<![CDATA[' ) || str_ends_with( $content, ']]>' ) ) {
			return $matches[0];
		}
		if ( str_ends_with( $content, '] ]>' ) ) {
			$content = substr( $content, 0, -4 ) . ']]>';
		} else {
			$content .= ' ]]>';
		}
		return $startTag . $content . $endTag;
	}

	/**
	 * @param array $matches
	 *
	 * @return string
	 */
	private function preprocessPlainTextBody( array $matches ): string {
		$startTag = $matches[1];
		$content = $matches[2];
		$endTag = $matches[3];

		if ( $this->startsWith( $content, '<![CDATA[' ) ) {
			if ( !$this->endsWith( $content, ']]>' ) ) {
				$content .= $this->cdataCloser;
			}
		}

		return $startTag . $content . $endTag;
	}

	/**
	 * @param string $text
	 * @param string $prefix
	 *
	 * @return bool
	 */
	private function startsWith( string $text, string $prefix ): bool {
		return strpos( $text, $prefix ) === 0;
	}

	/**
	 * @param string $content
	 * @param string $suffix
	 *
	 * @return bool
	 */
	private function endsWith( string $content, string $suffix ): bool {
		$contentLength = mb_strlen( $content );
		$suffixLength = mb_strlen( $suffix );
		$startIdx = $contentLength - $suffixLength;

		$realSuffix = mb_substr( $content, $startIdx, $suffixLength );
		return $realSuffix === $suffix;
	}
}
