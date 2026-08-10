<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class PageStatus implements IField {
	public function getSyntax(): string {
		return 'pageStatus';
	}

	public function getType(): string {
		return 'STATUS';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', 'in', 'not in' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
