<?php

namespace HalloWelt\MigrateConfluence\Command\Traits;

use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\HookHandler;
use HalloWelt\MigrateConfluence\Utility\PipeToDB;

trait SetupHooks {

	/**
	 * if given, load the configured PHP file relative to the config file to set up custom hooks
	 *
	 * @param array $config
	 * @param string|null $configFilePath
	 * @param DBLog|PipeToDB|null $logger logger for the DB log entry; when null, falls back to error_log
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	private function installCustomerHooks(
		// phpcs:ignore MediaWiki.Usage.NullableType.ExplicitNullableTypes -- null is already in the union
		array $config, ?string $configFilePath, DBLog|PipeToDB|null $logger = null
	): void {
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
			if ( !is_array( $customHandlers ) ) {
				throw new \RuntimeException(
					'Hook handler file ' . $config['config']['hook-handler'] . ' must return an array' );
			}
			HookHandler::setUp( $customHandlers );
			$this->logImplementedHooks( $customHandlersFile, $customHandlers, $logger );
		}
	}

	/**
	 * Write a log entry listing the hook file path and all registered hooks.
	 *
	 * @param string $filePath absolute path to the loaded hook handler file
	 * @param array $handlers the handler array returned by the file
	 * @param DBLog|PipeToDB|null $logger
	 */
	private function logImplementedHooks( string $filePath, array $handlers, DBLog|PipeToDB|null $logger ): void {
		$text = json_encode( [
			'file'    => $filePath,
			'filters' => array_keys( $handlers['filters'] ?? [] ),
			'actions' => array_keys( $handlers['actions'] ?? [] ),
		] );

		if ( $logger instanceof DBLog ) {
			$logger->addLogEntry( 'info', 'setup-hooks', static::class, $text );
		} elseif ( $logger instanceof PipeToDB ) {
			$logger->send( 'log', 'info', 'setup-hooks', static::class, $text );
		} else {
			error_log( $text );
		}
	}
}
