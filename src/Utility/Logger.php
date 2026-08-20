<?php

namespace HalloWelt\MigrateConfluence\Utility;

use Monolog\Logger as MonologLogger;

class Logger {
	private ?MonologLogger $logger = null;

	public const DEBUG = MonologLogger::DEBUG;
	public const INFO = MonologLogger::INFO;
	public const NOTICE = MonologLogger::NOTICE;
	public const WARNING = MonologLogger::WARNING;
	public const ERROR = MonologLogger::ERROR;
	public const CRITICAL = MonologLogger::CRITICAL;
	public const ALERT = MonologLogger::ALERT;
	public const EMERGENCY = MonologLogger::EMERGENCY;

	/**
	 *
	 */
	public static function getInstance(): Logger {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new Logger();
		}
		return $instance;
	}

	/**
	 *
	 */
	private function __construct() {
		$this->logger = new MonologLogger( 'migrate-confluence' );
	}

	/**
	 * @param string $target
	 * @param int|string $level
	 * @param string $message
	 * @param array ...$args
	 */
	public function log( string $target, int|string $level, string $message, ...$args ): void {
		$this->logger->withName( $target )->log( $level, $message, ...$args );
	}
}
