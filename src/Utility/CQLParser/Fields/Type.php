<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Type implements IField {
	public function getSyntax(): string {
		return 'type';
	}

	public function getType(): string {
		return 'CONTENT_TYPE';
	}

	public function getSupportedOperators(): array {
		return [ '=', 'in' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
