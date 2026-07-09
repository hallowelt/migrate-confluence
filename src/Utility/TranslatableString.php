<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * stub class for later implementation
 *
 * Currently we use it only to mark strings for translation.
 *
 * @todo provide translations
 * @todo provide languages (may be different for each wiki!)
 */
class TranslatableString {
	public function __construct( private readonly string $string ) {
	}

	public function __toString(): string {
		return $this->string;
	}
}
