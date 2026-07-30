<?php

namespace HalloWelt\MigrateConfluence\Converter\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\IDataWriter;

interface IConverterDataWriter extends IDataWriter {

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
	): bool;


	/**
	 * @param int $bodyContentId
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidBodyContent( int $bodyContentId, string $text ): void;

	/**
	 * @param int $templateId
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateContent( int $templateId, string $text ): void;
}
