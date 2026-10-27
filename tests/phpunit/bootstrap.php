<?php

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use Symfony\Component\Console\Output\NullOutput;

require_once __DIR__ . '/../../vendor/autoload.php';

// Silence console log output during tests
Log::setConsoleOutput( new NullOutput() );
