<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Created implements IField {
	public function getSyntax(): string {
		return 'created';
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
