<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class LastModified implements IField {
	public function getSyntax(): string {
		return 'lastModified';
	}

	public function getType(): string {
		return 'DATE';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', '>', '>=', '<', '<=' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
