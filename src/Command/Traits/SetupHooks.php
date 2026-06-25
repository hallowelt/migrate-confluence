<?php

namespace HalloWelt\MigrateConfluence\Command\Traits;

use HalloWelt\MigrateConfluence\Utility\HookHandler;

trait SetupHooks {

	/**
	 * if given, load the configured PHP file relative to the config file to set up custom hooks
	 *
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	private function installCustomerHooks( array $config, ?string $configFilePath ): void {
		if ( !$configFilePath || !is_file( $configFilePath ) ) {
			return;
		}

		$configPath = dirname( $configFilePath );

		if ( isset( $config['config']['hook-handler'] ) && is_string( $config['config']['hook-handler'] ) ) {
			$customHandlersFile = $configPath . DIRECTORY_SEPARATOR . $config['config']['hook-handler'];
			if ( !is_file( realpath( $customHandlersFile ) ) ) {
				throw new \RuntimeException( 'Hook handler file not found: ' . $config['config']['hook-handler'] );
			}
			$customHandlers = require_once $customHandlersFile;
			error_log( 'Loaded custom hook handler file: ' . $customHandlersFile );
			if ( !is_array( $customHandlers ) ) {
				throw new \RuntimeException(
					'Hook handler file ' . $config['config']['hook-handler'] . ' must return an array' );
			}
			HookHandler::setUp( $customHandlers );
		}
	}
}
