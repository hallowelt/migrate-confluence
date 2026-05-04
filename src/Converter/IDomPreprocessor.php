<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;

interface IDomPreprocessor {

	/**
	 * @param DOMDocument $dom
	 *
	 * @return void
	 */
	public function preprocess( DOMDocument $dom ): void;
}
