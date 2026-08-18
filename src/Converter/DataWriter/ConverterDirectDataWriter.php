<?php

namespace HalloWelt\MigrateConfluence\Converter\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\AbstractDirectDataWriter;

class ConverterDirectDataWriter extends AbstractDirectDataWriter implements IConverterDataWriter {
	/**
	 * @param int $bodyContentId
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidBodyContent( int $bodyContentId, string $text ): void {
		$this->db->addInvalidBodyContent( $bodyContentId, $text );
	}

	/**
	 * @param int $templateId
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateContent( int $templateId, string $text ): void {
		$this->db->addInvalidPageTemplateContent( $templateId, $text );
	}

	/**
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 *
	 * @return bool
	 */
	public function addGliffy(
		?int $spaceId,
		string $confluenceTitle,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		return $this->db->addGliffy(
			$spaceId, $confluenceTitle, $originalAttachmentFilename, $targetAttachmentFilename
		);
	}

	/**
	 * @param int $spaceId
	 * @param string $defaultPageName
	 * @param string $defaultPageNamespace
	 * @return bool
	 */
	public function registerDefaultPage(
		int $spaceId, string $defaultPageName, string $defaultPageNamespace = 'Template'
	): bool {
		return $this->db->registerDefaultPage( $spaceId, $defaultPageName, $defaultPageNamespace );
	}
}
