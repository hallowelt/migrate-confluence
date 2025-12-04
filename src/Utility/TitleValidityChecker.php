<?php

namespace HalloWelt\MigrateConfluence\Utility;

class TitleValidityChecker {

	/**
	 * @param string $title
	 * @return boolean
	 */
	public function validate( string $title ): bool {
		if ( !$this->hasValidEnding( $title ) ) {
			return false;
		}

		if ( str_contains( $title, ':' ) ) {
			if ( $this->hasDoubleCollon( $title ) ) {
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
	 * @return boolean
	 */
	public function hasValidEnding( string $title ): bool {
		if ( str_ends_with( $title, '_' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $title
	 * @return boolean
	 */
	public function hasDoubleCollon( string $title ): bool {
		if ( strpos( $title, ':' ) !== strrpos( $title, ':' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $namespace
	 * @return boolean
	 */
	public function hasValidNamespace( string $namespace ): bool {
		$matches = [];
		preg_match( '#(\d*)([a-zA-Z0-9_]*)#', $namespace, $matches );
		if ( empty( $matches ) || $matches[1] !== '' ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $title
	 * @return boolean
	 */
	public function hasValidLength( string $title ): bool {
		if ( strlen( $title ) > 255 ) {
			return false;
		}
		return true;
	}

}