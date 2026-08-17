<?php

namespace HalloWelt\MigrateConfluence\Converter\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\AbstractPipeDataWriter;

class ConverterPipeDataWriter extends AbstractPipeDataWriter implements IConverterDataWriter {
	/**
	 * @param int $bodyContentId
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidBodyContent( int $bodyContentId, string $text ): void {
		$this->send( __FUNCTION__, $bodyContentId, $text );
	}

	/**
	 * @param int $templateId
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateContent( int $templateId, string $text ): void {
		$this->send( __FUNCTION__, $templateId, $text );
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
		$this->send( __FUNCTION__, $spaceId, $confluenceTitle, $originalAttachmentFilename, $targetAttachmentFilename );
		return true;
	}

	/**
	 * @param integer $spaceId
	 * @param string $defaultPageName
	 * @param string $defaultPageNamespace
	 * @return boolean
	 */
	public function registerDefaultPage(
		int $spaceId, string $defaultPageName, string $defaultPageNamespace = 'Template'
	): bool {
		$this->send( __FUNCTION__, $spaceId, $defaultPageName, $defaultPageNamespace );
		return true;
	}
}
