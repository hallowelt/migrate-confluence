<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser;

interface IField {
	/**
	 * @return string
	 */
	public function getSyntax(): string;

	/**
	 * @return string
	 */
	public function getType(): string;

	/**
	 * @return array
	 */
	public function getSupportedOperators(): array;

	/**
	 * @return array
	 */
	public function getSupportedFunctions(): array;
}
