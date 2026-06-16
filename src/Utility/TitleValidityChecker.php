<?php

namespace HalloWelt\MigrateConfluence\Utility;

class TitleValidityChecker {

	private const MAX_TITLE_LENGTH = 255;

	/**
	 * @param string $title
	 * @return bool
	 */
	public function validate( string $title ): bool {
		if ( !$this->hasValidEnding( $title ) ) {
			return false;
		}

		if ( $this->containsInvalidChar( $title ) ) {
			return false;
		}

		if ( str_contains( $title, ':' ) ) {
			if ( $this->hasDoubleColon( $title ) ) {
				return false;
			}

			$namespace = substr( $title, 0, strpos( $title, ':' ) );
			$text = substr( $title, strpos( $title, ':' ) + 1 );

			if ( !$this->hasValidNamespace( $namespace ) ) {
				return false;
			}

			if ( !$this->hasValidLength( $text ) ) {
				return false;
			}
		} else {
			if ( !$this->hasValidLength( $title ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $title
	 * @return bool
	 */
	public function containsInvalidChar( string $title ): bool {
		return str_contains( $title, '~' );
	}

	/**
	 * @param string $title
	 * @return bool
	 */
	public function hasValidEnding( string $title ): bool {
		return !str_ends_with( $title, '_' ) && !str_ends_with( $title, '~' );
	}

	/**
	 * @param string $title
	 * @return bool
	 */
	public function hasDoubleColon( string $title ): bool {
		return strpos( $title, ':' ) !== strrpos( $title, ':' );
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
		return !empty( $matches ) && $matches[1] === '';
	}

	/**
	 * @param string $title
	 * @return bool
	 */
	public function hasValidLength( string $title ): bool {
		return strlen( $title ) <= self::MAX_TITLE_LENGTH;
	}

}
