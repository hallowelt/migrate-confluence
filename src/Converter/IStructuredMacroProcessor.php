<?php

namespace HalloWelt\MigrateConfluence\Converter;

use DOMDocument;

interface IStructuredMacroProcessor {

	/**
	 *
	 * @return string
	 */
	public function getMacroName(): string;

	/**
	 *
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function process( DOMDocument $dom ): void;
}
