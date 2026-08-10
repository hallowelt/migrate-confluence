<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Id implements IField {
	public function getSyntax(): string {
		return 'id';
	}

	public function getType(): string {
		return 'CONTENT';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', 'in', 'not in' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
