<?php

namespace HalloWelt\MigrateConfluence\Converter;

interface IPostprocessor {

	/**
	 *
	 * @param string $wikiText
	 * @return string
	 */
	public function postprocess( string $wikiText ): string;
}
