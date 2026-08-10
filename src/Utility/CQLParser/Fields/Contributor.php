<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class Contributor implements IField {
	public function getSyntax(): string {
		return 'contributor';
	}

	public function getType(): string {
		return 'USER';
	}

	public function getSupportedOperators(): array {
		return [ '=', '!=', 'in', 'not in' ];
	}

	public function getSupportedFunctions(): array {
		return [ 'currentUser()' ];
	}
}
