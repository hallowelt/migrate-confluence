<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Label implements IField {
	public function getSyntax(): string {
		return 'label';
	}

	public function getType(): string {
		return 'LABEL';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', 'in', 'not in' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
