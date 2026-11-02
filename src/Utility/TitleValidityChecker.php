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
		/* this regex comes from BlueSpiceNamespaceManager/src/Utils/NameChecker.php */
		preg_match( '/^[a-zA-Z_\\x80-\\xFF][a-zA-Z0-9_\\x80-\\xFF]{1,99}$/i', $namespace, $matches );
		if ( empty( $matches ) ) {
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
