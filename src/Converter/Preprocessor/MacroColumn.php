<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\IPreprocessor;

class MacroColumn implements IPreprocessor {

	/**
	 * @inheritDoc
	 */
	public function preprocess( string $confluenceHTML ): string {
		return $confluenceHTML;
	}
}
