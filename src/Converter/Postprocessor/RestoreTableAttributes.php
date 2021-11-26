<?php

namespace HalloWelt\MigrateConfluence\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\IPostprocessor;

class RestoreTableAttributes implements IPostprocessor {

	/**
	 * @inheritDoc
	 */
	public function postprocess( string $wikiText ): string {
		$newWikiText = [];
		$lines = explode( "\n", $wikiText );
		$inPreservedTableAttributesTableRow = false;
		$tableStart = false;
		$linesAfterTableStart = [];
		$hasTableAttributes = false;
		foreach ( $lines as $line ) {
			$trimmedLine = trim( $line );
			if ( $inPreservedTableAttributesTableRow && $trimmedLine === '|-' ) {
				$inPreservedTableAttributesTableRow = false;
				continue;
			}
			if ( $inPreservedTableAttributesTableRow ) {
				continue;
			}
			if ( $trimmedLine === '{|' ) {
				$tableStart = true;
				continue;
			}
			if ( $trimmedLine === '|}' ) {
				$tableStart = false;
			}
			if ( $tableStart ) {
				if ( $this->isPreservedTableAttributesLine( $trimmedLine ) ) {
					$tableStart = false;
					$inPreservedTableAttributesTableRow = true;
					$preserverdAttributes =
						$this->extractPreservedTableAttributes( $trimmedLine );
						$preserverdAttributes = trim( $preserverdAttributes );
					$newWikiText[] = "{| $preserverdAttributes";
					$hasTableAttributes = true;
					continue;
				}

				$linesAfterTableStart[] = $line;
				continue;
			}
			if ( !$tableStart ) {
				if ( !empty( $linesAfterTableStart ) && !$hasTableAttributes ) {
					$newWikiText[] = '{|';
				}
				foreach ( $linesAfterTableStart as $lineAfterTableStart ) {
					$newWikiText[] = $lineAfterTableStart;
				}
				$linesAfterTableStart = [];
			}
			$newWikiText[] = $line;
		}
		return implode( "\n", $newWikiText );
	}

	private function isPreservedTableAttributesLine( $line ) {
		return preg_match( "/\|.*?<span.*?>###PRESERVEDTABLEATTRIBUTES###<\/span>/", $line ) === 1;
	}

	private function extractPreservedTableAttributes( $line ) {
		return preg_replace(
			"/\|.*?<span(.*?)>###PRESERVEDTABLEATTRIBUTES###<\/span>/",
			'$1',
			$line
		);
	}
}