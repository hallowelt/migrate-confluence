<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Title implements IField {
	public function getSyntax(): string {
		return 'title';
	}

	public function getType(): string {
		return 'TEXT';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', '~', '!~' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
