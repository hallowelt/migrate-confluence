<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreCode implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		var_dump( $wikiText );
		$wikiText = preg_replace_callback(
			'#<pre class="PRESERVESYNTAXHIGHLIGHT"(.*?)>(.*?)</pre>#si', function( $matches ) {
				$attribs = $this->getAttributes( $matches[1] );

				if ( isset( $attribs['data-broken-macro'] ) ) {
					$code = '[[Category:' . $attribs['data-broken-macro'] . ']]';
					unset( $attribs['data-broken-macro'] );
					$params = $this->buildAttributes( $attribs );
					return '<syntaxhighlight' . $params . '></syntaxhighlight>' . $code;
				} else {
					$code = base64_decode( $matches[2] );
				}

				return '<syntaxhighlight' . $matches[1] . '>' . $code . '</syntaxhighlight>';
			},
			$wikiText
		);

		return $wikiText;
	}

	private function getAttributes( $params ) {
		$matches = [];
		preg_match_all( '#\s(.*?)="(.*?)"#', $params, $matches );

		$attribs = [];
		for ( $index = 0; $index < count( $matches[1] ); $index++ ) {
			$name = $matches[1][$index];
			$attribs[$name] = $matches[2][$index];
		}

		return $attribs;
	}

	private function buildAttributes( $attribs ) {
		$params = '';
		foreach ( $attribs as $key => $value ) {
			$params .= ' ' . $key . '="' . $value . '"';
		}
		return $params;
	}
}
