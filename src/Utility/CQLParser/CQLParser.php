<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser;

use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Ancestor;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Content;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Contributor;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Created;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Creator;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Favorite;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Favourite;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Id;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Label;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\LastModified;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Macro;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Mention;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\PageStatus;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\ParentField;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Space;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Text;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Title;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Type;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\User;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\UserAccountid;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\UserFullname;
use HalloWelt\MigrateConfluence\Utility\CQLParser\Fields\Watcher;

abstract class CQLParser {
	private const OP_EQUALS = '=';
	private const OP_NOT_EQUALS = '!=';
	private const OP_IN = 'in';
	private const OP_NOT_IN = 'not in';
	protected const TYPE_PAGE = 'page';
	protected const TYPE_BLOG = 'blog';

	/**
	 * @return array<IField>
	 */
	public function getFields(): array {
		return [
			new Ancestor(),
			new Content(),
			new Contributor(),
			new Created(),
			new Creator(),
			new Favorite(),
			new Favourite(),
			new Id(),
			new Label(),
			new LastModified(),
			new Macro(),
			new Mention(),
			new PageStatus(),
			new ParentField(),
			new Space(),
			new Text(),
			new Title(),
			new Type(),
			new User(),
			new UserAccountid(),
			new UserFullname(),
			new Watcher()
		];
	}

	/**
	 * Parse a CQL expression and return a target-specific query string.
	 *
	 * The parser intentionally supports all documented fields through IField metadata,
	 * validates every clause against known fields/operators/functions, and then delegates
	 * target rendering to `buildQueryFromClauses()`.
	 *
	 * @param string $cql
	 * @return string
	 */
	public function parse( string $cql ): string {
		$cql = preg_replace( '#\border\s+by\b.*$#i', '', $cql );
		if ( !is_string( $cql ) ) {
			return '';
		}

		$parts = $this->splitCql( $cql );
		if ( $parts === [] ) {
			return '';
		}

		$fieldMap = $this->getFieldMap();
		$queryClauses = [];
		$typeMode = null;

		foreach ( $parts as $part ) {
			$parsedClause = $this->parseClause( $part['clause'] );
			if ( $parsedClause === null ) {
				return '';
			}

			$fieldName = strtolower( $parsedClause['field'] );
			if ( !isset( $fieldMap[$fieldName] ) ) {
				return '';
			}

			$field = $fieldMap[$fieldName];
			$operator = $parsedClause['operator'];
			if ( !$this->isOperatorSupported( $field, $operator ) ) {
				return '';
			}

			if ( !$this->areFunctionsSupported( $field, $parsedClause['values'] ) ) {
				return '';
			}

			if ( $fieldName === 'type' ) {
				$typeMode = $this->resolveTypeMode( $parsedClause, $typeMode );
				if ( $typeMode === null ) {
					return '';
				}
				continue;
			}

			$queryClauses[] = [
				'joiner' => $part['joiner'],
				'clause' => $parsedClause
			];
		}

		return $this->buildQueryFromClauses( $queryClauses, $typeMode );
	}

	/**
	 * Convert parsed/validated CQL clause metadata into a target query string.
	 *
	 * Derived classes implement this to produce a concrete query language.
	 *
	 * @param array $queryClauses
	 * @param string|null $typeMode
	 * @return string
	 */
	abstract protected function buildQueryFromClauses( array $queryClauses, ?string $typeMode ): string;

	/**
	 * Split CQL into top-level clause segments joined by AND/OR.
	 *
	 * Splitting is aware of quotes and parentheses so values such as
	 * `in ("A", "B")` remain intact.
	 *
	 * @param string $cql
	 * @return array<int, array{joiner:?string, clause:string}>
	 */
	private function splitCql( string $cql ): array {
		$parts = [];
		$current = '';
		$quote = '';
		$depth = 0;
		$offset = 0;
		$len = strlen( $cql );
		$pendingJoiner = null;

		while ( $offset < $len ) {
			$char = $cql[$offset];

			if ( $quote !== '' ) {
				$current .= $char;
				if ( $char === $quote ) {
					$quote = '';
				}
				$offset++;
				continue;
			}

			if ( $char === '"' || $char === '\'' ) {
				$quote = $char;
				$current .= $char;
				$offset++;
				continue;
			}

			if ( $char === '(' ) {
				$depth++;
				$current .= $char;
				$offset++;
				continue;
			}

			if ( $char === ')' ) {
				if ( $depth > 0 ) {
					$depth--;
				}
				$current .= $char;
				$offset++;
				continue;
			}

			if ( $depth === 0 && preg_match( '#\G\s+(and|or)\s+#Ai', $cql, $m, 0, $offset ) ) {
				$clause = trim( $current );
				if ( $clause === '' ) {
					return [];
				}

				$parts[] = [
					'joiner' => $pendingJoiner,
					'clause' => $clause
				];

				$pendingJoiner = strtolower( $m[1] );
				$current = '';
				$offset += strlen( $m[0] );
				continue;
			}

			$current .= $char;
			$offset++;
		}

		$finalClause = trim( $current );
		if ( $finalClause !== '' ) {
			$parts[] = [
				'joiner' => $pendingJoiner,
				'clause' => $finalClause
			];
		}

		return $parts;
	}

	/**
	 * Parse a single clause into field/operator/values metadata.
	 *
	 * @param string $clause
	 * @return array{field:string, operator:string, values:array<int,string>, leadingNot:bool}|null
	 */
	private function parseClause( string $clause ): ?array {
		if ( !preg_match(
			'#^(?:(not)\s+)?([a-z][a-z0-9_.]*)\s*(not\s+in|!=|>=|<=|=|>|<|in|~|!~)\s*(.+)$#Ai',
			$clause,
			$m
		) ) {
			return null;
		}

		$leadingNot = !empty( $m[1] );
		$field = strtolower( $m[2] );
		$operator = strtolower( preg_replace( '#\s+#', ' ', $m[3] ) );
		$rawValue = trim( $m[4] );

		$values = $this->parseValues( $operator, $rawValue );
		if ( $values === [] ) {
			return null;
		}

		return [
			'field' => $field,
			'operator' => $operator,
			'values' => $values,
			'leadingNot' => $leadingNot
		];
	}

	/**
	 * @param string $operator
	 * @param string $rawValue
	 * @return array<int,string>
	 */
	private function parseValues( string $operator, string $rawValue ): array {
		if ( $operator === self::OP_IN || $operator === self::OP_NOT_IN ) {
			if ( preg_match( '#^\((.*)\)$#s', $rawValue, $m ) ) {
				$rawValue = $m[1];
			}

			$values = $this->splitCsvValues( $rawValue );
			return array_values( array_filter( $values, static fn ( $value ) => $value !== '' ) );
		}

		$value = $this->normalizeScalarValue( $rawValue );
		if ( $value === '' ) {
			return [];
		}

		return [ $value ];
	}

	/**
	 * @param string $valueList
	 * @return array<int,string>
	 */
	private function splitCsvValues( string $valueList ): array {
		$values = [];
		$current = '';
		$quote = '';
		$len = strlen( $valueList );

		for ( $i = 0; $i < $len; $i++ ) {
			$char = $valueList[$i];

			if ( $quote !== '' ) {
				$current .= $char;
				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( $char === '"' || $char === '\'' ) {
				$quote = $char;
				$current .= $char;
				continue;
			}

			if ( $char === ',' ) {
				$values[] = $this->normalizeScalarValue( $current );
				$current = '';
				continue;
			}

			$current .= $char;
		}

		$values[] = $this->normalizeScalarValue( $current );
		return $values;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	private function normalizeScalarValue( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		if ( ( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) ||
			( str_starts_with( $value, '\'' ) && str_ends_with( $value, '\'' ) ) ) {
			return substr( $value, 1, -1 );
		}

		return $value;
	}

	/**
	 * @return array<string, IField>
	 */
	private function getFieldMap(): array {
		$fieldMap = [];
		foreach ( $this->getFields() as $field ) {
			$fieldMap[strtolower( $field->getSyntax() )] = $field;
		}

		return $fieldMap;
	}

	/**
	 * @param IField $field
	 * @param string $operator
	 * @return bool
	 */
	private function isOperatorSupported( IField $field, string $operator ): bool {
		$supported = array_map(
			static fn ( string $op ) => strtolower( preg_replace( '#\s+#', ' ', trim( $op ) ) ),
			$field->getSupportedOperators()
		);

		return in_array( $operator, $supported, true );
	}

	/**
	 * @param IField $field
	 * @param array<int,string> $values
	 * @return bool
	 */
	private function areFunctionsSupported( IField $field, array $values ): bool {
		$supportedFunctions = array_map(
			static fn ( string $fn ) => strtolower( trim( $fn ) ),
			$field->getSupportedFunctions()
		);

		foreach ( $values as $value ) {
			if ( !preg_match( '#^[a-z][a-z0-9_]*\(\)$#i', $value ) ) {
				continue;
			}

			if ( !in_array( strtolower( $value ), $supportedFunctions, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array{field:string, operator:string, values:array<int,string>, leadingNot:bool} $clause
	 * @param string|null $currentTypeMode
	 * @return string|null
	 */
	private function resolveTypeMode( array $clause, ?string $currentTypeMode ): ?string {
		$operator = $clause['operator'];
		$leadingNot = $clause['leadingNot'];

		if ( $leadingNot || $operator === self::OP_NOT_EQUALS || $operator === self::OP_NOT_IN ) {
			return null;
		}

		if ( $operator !== self::OP_EQUALS && $operator !== self::OP_IN ) {
			return null;
		}

		$resolvedMode = null;
		foreach ( $clause['values'] as $value ) {
			$normalized = strtolower( trim( $value ) );
			if ( $normalized === self::TYPE_PAGE ) {
				$clauseMode = self::TYPE_PAGE;
			} elseif ( $normalized === self::TYPE_BLOG || $normalized === 'blogpost' ) {
				$clauseMode = self::TYPE_BLOG;
			} else {
				return null;
			}

			if ( $resolvedMode === null ) {
				$resolvedMode = $clauseMode;
			} elseif ( $resolvedMode !== $clauseMode ) {
				return null;
			}
		}

		if ( $resolvedMode === null ) {
			return null;
		}

		if ( $currentTypeMode === null ) {
			return $resolvedMode;
		}

		if ( $currentTypeMode !== $resolvedMode ) {
			return null;
		}

		return $currentTypeMode;
	}
}
