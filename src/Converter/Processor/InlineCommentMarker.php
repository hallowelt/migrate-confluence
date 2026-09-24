<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

/**
 *
 */
class InlineCommentMarker extends ConversionHelper implements IProcessor {

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpaceId
	 * @param DBConversionDataLookup $dataLookup
	 */
	public function __construct(
		private IConverterDataWriter $writer,
		private int $currentSpaceId,
		private DBConversionDataLookup $dataLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$processorNodes = $dom->getElementsByTagName( 'inline-comment-marker' );

		if ( $processorNodes->count() < 1 ) {
			return;
		}

		$macroNodes = [];
		foreach ( $processorNodes as $processorNode ) {
			$macroNodes[] = $processorNode;
		}

		foreach ( $macroNodes as $macroNode ) {
			$attributes = [];
			$attributeNames = $macroNode->getAttributeNames();
			foreach ( $attributeNames as $attributeName ) {
				$attributes[$attributeName] = $macroNode->getAttribute( $attributeName );
			}

			$ref = $attributes['ac:ref'] ?? '';

			if ( empty( $ref ) ) {
				continue;
			}

			$comment = $this->dataLookup->getInlineCommentsForMarkerRef( $ref );

			if ( empty( $comment ) ) {
				// Deleted comments had not been stored in database
				continue;
			}

			$originalText = $comment['original_text'] ?? '';
			if ( $originalText === '' ) {
				continue;
			}

			$replacement = '{{InlineComment|text=' . $this->escapeTemplateParamValue( $originalText );

			$commentText = $this->escapeTemplateParamValue(
				(string)( $comment['comment_text'] ?? '' )
			);
			$replacement .= "|comment=$commentText";

			$answersWikitext = $this->buildAnswersWikitext(
				$comment['children'] ?? []
			);

			$replacement .= "|answers=$answersWikitext";

			if ( isset( $comment['status'] ) && in_array( $comment['status'], [ 'active', 'resolved' ] ) ) {
				$status = $comment['status'];
				$replacement .= "|status=$status";
			}

			$replacement .= '}}';

			$this->writer->registerDefaultPage(
				$this->currentSpaceId,
				'InlineComment'
			);

			$macroNode->parentNode->replaceChild(
				$this->createTextNode(
					$macroNode->ownerDocument,
					$replacement,
					__METHOD__
				),
				$macroNode
			);
		}
	}

	/**
	 * Builds one HTML "<div>" per reply body, from oldest to newest, escaping pipes so they don't break
	 * the enclosing template call.
	 *
	 * @param array<int,array> $answers Replies keyed by their creation timestamp.
	 * @return string
	 */
	private function buildAnswersWikitext( array $answers ): string {
		ksort( $answers, SORT_NUMERIC );

		if ( empty( $answers ) ) {
			return '';
		}

		$items = [];
		foreach ( $answers as $answer ) {
			if ( !is_array( $answer ) || empty( $answer['comment_text'] ) ) {
				continue;
			}

			$escaped = $this->escapeTemplateParamValue( (string)$answer['comment_text'] );
			$items[] = '<div class="inline-comment-item">' . $escaped . '</div>';
		}
		return implode( '', $items );
	}

	/**
	 * Escapes the parameter separator "|"; "=" is safe because all parameters are passed by name.
	 *
	 * @param string $value
	 * @return string
	 */
	private function escapeTemplateParamValue( string $value ): string {
		return str_replace( [ "\n", '|' ], [ ' ', '{{!}}' ], trim( $value ) );
	}
}
