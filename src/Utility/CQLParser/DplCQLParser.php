<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser;

use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Label;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Space;

class DplCQLParser extends CQLParser {
	private const OR_SEPARATOR = '{{!}}';

	/**
	 * @return array<IField>
	 */
	public function getFields(): array {
		return [
			new Label(),
			new Space(),
		];
	}

	/**
	 * Build a DPL4 condition string from parsed CQL clauses.
	 *
	 * Only fields with direct DPL4 counterparts are mapped.
	 * Currently: `label`, `space`.
	 * Other validated fields are intentionally ignored until mapping rules exist.
	 *
	 * @param array $queryClauses
	 * @param string|null $typeMode
	 * @return string
	 */
	protected function buildQueryFromClauses( array $queryClauses, ?string $typeMode ): string {
		$result = [];
		$lastOrComposable = null;

		foreach ( $queryClauses as $queryClause ) {
			$clauses = $this->buildDplClauses( $queryClause['clause'], $typeMode );
			if ( $clauses === [] ) {
				continue;
			}

			if ( $queryClause['joiner'] === 'or' ) {
				if ( count( $clauses ) !== 1 || !$clauses[0]['orComposable'] || $lastOrComposable === null ) {
					return '';
				}

				if ( $lastOrComposable['key'] !== $clauses[0]['key'] ) {
					return '';
				}

				$result[$lastOrComposable['index']]['value'] .= self::OR_SEPARATOR . $clauses[0]['value'];
				continue;
			}

			foreach ( $clauses as $clause ) {
				$result[] = [
					'key' => $clause['key'],
					'value' => $clause['value']
				];
			}

			$lastClause = $clauses[count( $clauses ) - 1];
			if ( $lastClause['orComposable'] ) {
				$lastOrComposable = [
					'index' => count( $result ) - 1,
					'key' => $lastClause['key']
				];
			} else {
				$lastOrComposable = null;
			}
		}

		$queryParts = array_map(
			static fn ( array $clause ) => $clause['key'] . '=' . $clause['value'],
			$result
		);

		if ( $queryParts === [] ) {
			return '';
		}

		return implode( ',', $queryParts );
	}

	/**
	 * Convert one parsed clause into one or many DPL4 parameter fragments.
	 *
	 * @param array{field:string, operator:string, values:array<int,string>, leadingNot:bool} $clause
	 * @param string|null $typeMode
	 * @return array<int, array{key:string, value:string, orComposable:bool}>
	 */
	private function buildDplClauses( array $clause, ?string $typeMode ): array {
		$field = $clause['field'];
		$operator = $clause['operator'];
		$leadingNot = $clause['leadingNot'];

		if ( $field !== 'label' && $field !== 'space' ) {
			return [];
		}

		if ( $operator === '=' || $operator === '!=' ) {
			$negated = ( $operator === '!=' ) !== $leadingNot;
			$single = $this->buildSingleDplClause( $field, $clause['values'][0], $negated, $typeMode );
			if ( $single === null ) {
				return [];
			}

			return [ $single ];
		}

		if ( $operator === 'in' || $operator === 'not in' ) {
			$negated = ( $operator === 'not in' ) !== $leadingNot;

			if ( !$negated ) {
				$values = [];
				foreach ( $clause['values'] as $value ) {
					$single = $this->buildSingleDplClause( $field, $value, false, $typeMode );
					if ( $single === null ) {
						return [];
					}
					$values[] = $single['value'];
				}

				$key = $field === 'label' ? 'category' : 'namespace';
				return [
					[
						'key' => $key,
						'value' => implode( self::OR_SEPARATOR, $values ),
						'orComposable' => true,
					]
				];
			}

			$clauses = [];
			foreach ( $clause['values'] as $value ) {
				$single = $this->buildSingleDplClause( $field, $value, true, $typeMode );
				if ( $single === null ) {
					return [];
				}
				$clauses[] = $single;
			}

			return $clauses;
		}

		return [];
	}

	/**
	 * Convert one field/value pair into a single DPL4 parameter fragment.
	 *
	 * @param string $field
	 * @param string $value
	 * @param bool $negated
	 * @param string|null $typeMode
	 * @return array{key:string, value:string, orComposable:bool}|null
	 */
	private function buildSingleDplClause( string $field, string $value, bool $negated, ?string $typeMode ): ?array {
		if ( $field === 'label' ) {
			$label = ucfirst( trim( $value ) );
			if ( $label === '' ) {
				return null;
			}

			return [
				'key' => $negated ? 'notcategory' : 'category',
				'value' => $label,
				'orComposable' => !$negated,
			];
		}

		$spaceValue = trim( $value );
		if ( preg_match( '#^currentSpace\(\)$#i', $spaceValue ) ) {
			$namespace = '{{NAMESPACE}}';
		} else {
			$namespace = trim( $spaceValue, '"\'' );
		}

		if ( $namespace === '' ) {
			return null;
		}

		if ( $typeMode === self::TYPE_BLOG ) {
			$namespace = $this->toBlogNamespace( $namespace );
			if ( $namespace === '' ) {
				return null;
			}
		}

		return [
			'key' => $negated ? 'notnamespace' : 'namespace',
			'value' => $namespace,
			'orComposable' => !$negated,
		];
	}

	/**
	 * Rewrite one namespace for blog/blogpost type constraints.
	 *
	 * @param string $namespace
	 * @return string
	 */
	private function toBlogNamespace( string $namespace ): string {
		if ( stripos( $namespace, 'Blog:' ) === 0 ) {
			return $namespace;
		}

		return 'Blog:' . $namespace;
	}
}
