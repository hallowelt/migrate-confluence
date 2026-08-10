<?php

namespace HalloWelt\MigrateConfluence\Utility\CQLParser\Fields;

use HalloWelt\MigrateConfluence\Utility\CQLParser\IField;

class UserFullname implements IField {
	public function getSyntax(): string {
		return 'user.fullname';
	}

	public function getType(): string {
		return 'USER';
	}

	public function getSupportedOperators(): array {
		return [ '~' ];
	}

	public function getSupportedFunctions(): array {
		return [];
	}
}
