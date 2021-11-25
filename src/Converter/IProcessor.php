<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;

interface IProcessor {

	/**
	 *
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function process( DOMDocument $dom ): void;
}
