<?php

namespace HalloWelt\MigrateConfluence\Utility;

class TitleValidityChecker {

	private const MAX_TITLE_LENGTH = 255;

	/**
	 * @param string $title
	 * @return bool
	 */
	public function hasValidEnding( string $title ): bool {
		if ( str_ends_with( $title, '_' ) || str_ends_with( $title, '~' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $title
	 * @return bool
	 */
	public function hasDoubleColon( string $title ): bool {
		if ( strpos( $title, ':' ) !== strrpos( $title, ':' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $namespace
	 * @return bool
	 */
	public function hasValidNamespace( string $namespace ): bool {
		if ( empty( $namespace ) ) {
			return false;
		}

		$matches = [];
		preg_match( '#^(\d*)([a-zA-Z0-9_]*)$#', $namespace, $matches );
		if ( empty( $matches ) || $matches[1] !== '' ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $title
	 * @return bool
	 */
	public function hasValidLength( string $title ): bool {
		return strlen( $title ) <= self::MAX_TITLE_LENGTH;
	}

}
