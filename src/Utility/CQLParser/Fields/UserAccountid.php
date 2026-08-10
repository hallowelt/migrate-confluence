<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class UserAccountid implements IField {
	public function getSyntax(): string {
		return 'user.accountid';
	}

	public function getType(): string {
		return 'USER';
	}

	public function getSupportedOperators(): array {
		return [ '=', 'in' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
