<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser;

class SemanticMediaWikiCQLParser extends CQLParser {
	/**
	 * Build a Semantic MediaWiki condition string from parsed CQL clauses.
	 *
	 * Only `label` and `space` currently generate direct SMW conditions.
	 * Other validated fields are intentionally ignored until mapping rules exist.
	 *
	 * @param array $queryClauses
	 * @param string|null $typeMode
	 * @return string
	 */
	protected function buildQueryFromClauses( array $queryClauses, ?string $typeMode ): string {
		$result = '';
		$hasClause = false;
		$spaceClauses = [];

		foreach ( $queryClauses as $queryClause ) {
			$clauses = $this->buildSemanticClauses( $queryClause['clause'] );
			if ( $clauses === [] ) {
				continue;
			}

			if ( $queryClause['clause']['field'] === 'space' ) {
				foreach ( $clauses as $clause ) {
					$spaceClauses[] = $clause;
				}
			}

			if ( $hasClause && $queryClause['joiner'] === 'or' ) {
				$result .= '|';
			}

			$result .= implode( '|', $clauses );
			$hasClause = true;
		}

		if ( $typeMode === self::TYPE_BLOG ) {
			foreach ( $spaceClauses as $spaceClause ) {
				$blogSpaceClause = $this->toBlogSpaceClause( $spaceClause );
				if ( $blogSpaceClause === '' ) {
					return '';
				}
				$result = str_replace( $spaceClause, $blogSpaceClause, $result );
			}
		}

		return $result;
	}

	/**
	 * Convert one parsed clause into one or many SMW clauses.
	 *
	 * @param array{field:string, operator:string, values:array<int,string>, leadingNot:bool} $clause
	 * @return array<int,string>
	 */
	protected function buildSemanticClauses( array $clause ): array {
		$field = $clause['field'];
		$operator = $clause['operator'];
		$leadingNot = $clause['leadingNot'];

		if ( $field !== 'label' && $field !== 'space' ) {
			return [];
		}

		if ( $operator === '=' || $operator === '!=' ) {
			$negated = ( $operator === '!=' ) !== $leadingNot;
			$single = $this->buildSingleSemanticClause( $field, $clause['values'][0], $negated );
			if ( $single === '' ) {
				return [];
			}

			return [ $single ];
		}

		if ( $operator === 'in' || $operator === 'not in' ) {
			$negated = ( $operator === 'not in' ) !== $leadingNot;
			$clauses = [];
			foreach ( $clause['values'] as $value ) {
				$single = $this->buildSingleSemanticClause( $field, $value, $negated );
				if ( $single === '' ) {
					return [];
				}
				$clauses[] = $single;
			}

			return $clauses;
		}

		return [];
	}

	/**
	 * Convert one field/value pair into a single SMW condition clause.
	 *
	 * @param string $field
	 * @param string $value
	 * @param bool $negated
	 * @return string
	 */
	protected function buildSingleSemanticClause( string $field, string $value, bool $negated ): string {
		if ( $field === 'label' ) {
			$label = ucfirst( trim( $value ) );
			if ( $label === '' ) {
				return '';
			}

			$prefix = $negated ? '!' : '';
			return '[[' . $prefix . 'Category:' . $label . ']]';
		}

		$spaceValue = trim( $value );
		if ( preg_match( '#^currentSpace\(\)$#i', $spaceValue ) ) {
			$spaceClause = '{{NAMESPACE}}';
		} else {
			$spaceClause = trim( $spaceValue, '"\'' );
		}

		if ( $spaceClause === '' ) {
			return '';
		}

		$prefix = $negated ? '!' : '';
		return '[[' . $prefix . $spaceClause . ':+]]';
	}

	/**
	 * Rewrite namespace subtree clauses for blog/blogpost type constraints.
	 *
	 * @param string $spaceClause
	 * @return string
	 */
	protected function toBlogSpaceClause( string $spaceClause ): string {
		if ( preg_match( '#^\[\[(!?)([^:\]]+):\+\]\]$#', $spaceClause, $m ) ) {
			$prefix = $m[1];
			$space = $m[2];
			if ( stripos( $space, 'Blog:' ) === 0 ) {
				return $spaceClause;
			}

			return '[[' . $prefix . 'Blog:' . $space . ':+]]';
		}

		return '';
	}
}
