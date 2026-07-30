<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\AbstractDirectDataWriter;

class ExtractorDirectDataWriter extends AbstractDirectDataWriter implements IExtractorDataWriter {

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidPageTemplateTitle( $templateId, $wikiTitle, $text );
	}
}
