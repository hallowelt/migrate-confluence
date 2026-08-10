<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Space implements IField {
	public function getSyntax(): string {
		return 'space';
	}

	public function getType(): string {
		return 'SPACE';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', 'in', 'not in' ];
	}

	public function getSupportedFunctions(): array {
		return [ 'currentSpace()' ];
	}
}
