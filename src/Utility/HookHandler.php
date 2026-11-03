<?php

namespace HalloWelt\MigrateConfluence\Utility;

/**
 * simple hook system to allow client-based customization
 */
final class HookHandler {
	private static array $handlers = [
		'filters' => [],
		'actions' => [],
	];

	/**
	 * install client-provided handlers for hook system
	 *
	 * @param array{filters?: array<string, callable>, actions?: array<string, callable>} $handlers
	 * @throws \InvalidArgumentException
	 */
	public static function setUp( array $handlers ): void {
		foreach ( $handlers as $type => $typeHandlers ) {
			if ( !in_array( $type, [ 'filters', 'actions' ] ) ) {
				throw new \InvalidArgumentException( "Invalid hook type '$type' (must be 'filters' or 'actions')" );
			}
			foreach ( $typeHandlers as $hookName => $callback ) {
				if ( !is_callable( $callback ) ) {
					throw new \InvalidArgumentException( "Callback for hook '$type/$hookName' is not callable" );
				}
				self::$handlers[$type][$hookName] = $callback;
			}
		}
	}

	/**
	 * run callbacks at a specific time
	 *
	 * @param string $hookName name of the hook
	 * @param mixed ...$args arguments to pass to the action callback
	 * @return void
	 */
	public static function run( string $hookName, ...$args ): void {
		if ( array_key_exists( $hookName, self::$handlers['actions'] ) ) {
			call_user_func_array( self::$handlers['actions'][$hookName], $args );
		}
	}

	/**
	 * filter a value
	 *
	 * @param string $hookName name of the hook
	 * @param mixed $value the value to filter
	 * @param mixed ...$args additional arguments to pass to the filter callback
	 * @return mixed the filtered value
	 */
	public static function filter( string $hookName, $value, ...$args ): mixed {
		if ( array_key_exists( $hookName, self::$handlers['filters'] ) ) {
			return call_user_func_array(
				self::$handlers['filters'][$hookName],
				array_merge( [ $value ], $args )
			);
		}
		return $value;
	}
}
