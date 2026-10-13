<?php

namespace HalloWelt\MigrateConfluence\Command\Traits;

use HalloWelt\MigrateConfluence\Utility\HookHandler;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Psr\Log\LoggerInterface;

trait SetupHooks {

	/**
	 * if given, load the configured PHP file relative to the config file to set up custom hooks
	 *
	 * @param array|MigrationConfig $config
	 * @param string|null $configFilePath
	 * @param LoggerInterface|null $logger logger for the DB log entry; when null, falls back to error_log
	 * @throws \RuntimeException
	 * @throws \InvalidArgumentException
	 */
	private function installCustomerHooks(
		array|MigrationConfig $config, ?string $configFilePath, ?LoggerInterface $logger = null
	): void {
		if ( !$configFilePath || !is_file( $configFilePath ) ) {
			return;
		}

		$configPath = dirname( $configFilePath );

		if ( $config instanceof MigrationConfig ) {
			$hookHandler = $config->getHookHandler();
		} else {
			$hookHandler = $config['config']['hook-handler'] ?? null;
		}

		if ( is_string( $hookHandler ) && $hookHandler !== '' ) {
			if ( !str_starts_with( $hookHandler, '/' ) ) {
				$hookHandler = $configPath . DIRECTORY_SEPARATOR . $hookHandler;
			}
			$customHandlersFile = realpath( $hookHandler );
			if ( !is_file( $customHandlersFile ) ) {
				throw new \RuntimeException( 'Hook handler file not found: ' . $hookHandler );
			}
			$customHandlers = require_once $customHandlersFile;
			if ( !is_array( $customHandlers ) ) {
				throw new \RuntimeException(
					'Hook handler file ' . $hookHandler . ' must return an array' );
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
	 * @param ?LoggerInterface $logger
	 */
	private function logImplementedHooks( string $filePath, array $handlers, ?LoggerInterface $logger ): void {
		$text = json_encode( [
			'file'    => $filePath,
			'filters' => array_keys( $handlers['filters'] ?? [] ),
			'actions' => array_keys( $handlers['actions'] ?? [] ),
		] );

		if ( $logger ) {
			$logger->info( $text );
		} else {
			error_log( $text );
		}
	}
}
