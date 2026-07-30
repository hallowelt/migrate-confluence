<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\IDataWriter;

interface IExtractorDataWriter extends IDataWriter {
	
	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void;
}
