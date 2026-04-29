<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

/**
 * Preprocessor that reads ContentProperty objects linked to Comment objects
 * and collects the IDs of inline comments (identified by having an
 * "inline-comment" or "inline-marker-ref" property).
 */
class ContentProperties extends ProcessorBase {

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-inline-comment-ids',
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function doExecute(): void {
		$properties = [];
		$contentClass = null;

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'property' ) {
				// Capture the class attribute before processPropertyNodes consumes the element
				if ( $this->xmlReader->getAttribute( 'name' ) === 'content' ) {
					$contentClass = $this->xmlReader->getAttribute( 'class' );
				}
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( $contentClass !== 'Comment' ) {
			return;
		}

		$propName = $properties['name'] ?? null;
		if ( $propName !== 'inline-comment' && $propName !== 'inline-marker-ref' ) {
			return;
		}

		$commentId = isset( $properties['content'] ) ? (int)$properties['content'] : -1;
		if ( $commentId === -1 ) {
			return;
		}

		if ( !in_array( $commentId, $this->data['analyze-inline-comment-ids'] ) ) {
			$this->data['analyze-inline-comment-ids'][] = $commentId;
		}
	}
}
