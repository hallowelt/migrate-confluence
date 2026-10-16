<?php

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;

require_once __DIR__ . '/../../vendor/autoload.php';

// Silence console log output during tests
Log::configure( [ 'handlers' => [] ] );
