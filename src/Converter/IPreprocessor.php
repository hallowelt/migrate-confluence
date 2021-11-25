<?php

namespace HalloWelt\MigrateConfluence\Converter;

interface IPreprocessor {

	/**
	 *
	 * @param string $confluenceHTML
	 * @return string
	 */
	public function preprocess( string $confluenceHTML ): string;
}
