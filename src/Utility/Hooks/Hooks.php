<?php

namespace HalloWelt\MigrateConfluence\Utility\Hooks;

/**
 * Base class for the hook points of one migration step.
 *
 * Every public static method of a child class is one hook. The hook name that
 * customers use in their hook file is the kebab-case form of the method name
 * (convertContentPre => convert-content-pre). Methods returning void are
 * actions, all others are filters.
 */
abstract class Hooks {

	/**
	 * Translates a hook method name into the name used in the hook handler file.
	 */
	public static function hookName( string $method ): string {
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $method ) );
	}

	/**
	 * @param string $method pass __FUNCTION__ of the calling hook method
	 * @param mixed $value
	 * @param mixed ...$args
	 * @return mixed
	 */
	protected static function filter( string $method, mixed $value, mixed ...$args ): mixed {
		return HookHandler::filter( self::hookName( $method ), $value, ...$args );
	}

	/**
	 * @param string $method pass __FUNCTION__ of the calling hook method
	 * @param mixed ...$args
	 */
	protected static function action( string $method, mixed ...$args ): void {
		HookHandler::run( self::hookName( $method ), ...$args );
	}
}
