<?php

namespace HalloWelt\MigrateConfluence\Composer;

interface IPageContentPostProcessor {

	/**
	 * @param string $pageTitle
	 * @param string $pageContent
	 * @return string
	 */
	public function postProcess( string $pageTitle, string $pageContent ): string;
}
