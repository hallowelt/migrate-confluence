<?php

namespace HalloWelt\MigrateConfluence\Database\DataWriter;

abstract class AbstractWriter implements IDataWriter {

	/**
	 * @param string $method
	 * @param array $args
	 *
	 * @return void
	 */
	abstract protected function dispatch( string $method, array $args ): mixed;

	/**
	 * @param string $type
	 * @param string $step
	 * @param string $caller
	 * @param string $text
	 *
	 * @return void
	 */
	public function addLogEntry(
		string $type,
		string $step,
		string $caller,
		string $text
	): void {
		$this->dispatch(
			__FUNCTION__,
			[
				$type,
				$step,
				$caller,
				$text
			]
		);
	}

	/**
	 * @param int $spaceId
	 * @param string $spaceKey
	 * @param string $spaceName
	 * @param string $prefix
	 * @param int $homepageId
	 * @param int $descriptionId
	 *
	 * @return bool
	 */
	public function addSpace(
		int $spaceId,
		string $spaceKey,
		string $spaceName,
		string $prefix,
		int $homepageId,
		int $descriptionId
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$spaceId,
				$spaceKey,
				$spaceName,
				$prefix,
				$homepageId,
				$descriptionId
			]
		);
	}

	/**
	 * @param int $spaceDescriptionId
	 * @param string $contentStatus
	 * @param string $version
	 * @param int $originalVersionId
	 * @param string $revisionTimestamp
	 * @param array $bodyContentIds
	 * @param array $labellingIds
	 * @param array $properties
	 * @param array $collection
	 *
	 * @return bool
	 */
	public function addSpaceDescription(
		int $spaceDescriptionId,
		string $contentStatus,
		string $version,
		int $originalVersionId,
		string $revisionTimestamp,
		array $bodyContentIds,
		array $labellingIds,
		array $properties,
		array $collection
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$spaceDescriptionId,
				$contentStatus,
				$version,
				$originalVersionId,
				$revisionTimestamp,
				$bodyContentIds,
				$labellingIds,
				$properties,
				$collection
			]
		);
	}

	/**
	 * @param int $pageId
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $contentStatus
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
	 * @param string $version
	 * @param int $originalVersionId
	 * @param int $parentPageId
	 * @param array $bodyContentIds
	 * @param array $historicalIds
	 * @param array $properties
	 * @param array $collection
	 *
	 * @return bool
	 */
	public function addPage(
		int $pageId,
		?int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $contentStatus,
		string $revisionTimestamp,
		string $lastModifier,
		string $version,
		int $originalVersionId,
		int $parentPageId,
		array $bodyContentIds,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$pageId,
				$spaceId,
				$confluenceTitle,
				$wikiTitle,
				$contentStatus,
				$revisionTimestamp,
				$lastModifier,
				$version,
				$originalVersionId,
				$parentPageId,
				$bodyContentIds,
				$historicalIds,
				$properties,
				$collection
			]

		);
	}

	/**
	 * @param int $pageId
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $contentStatus
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
	 * @param string $version
	 * @param int $originalVersionId
	 * @param array $bodyContentIds
	 * @param array $historicalIds
	 * @param array $properties
	 * @param array $collection
	 *
	 * @return bool
	 */
	public function addBlogPost(
		int $pageId,
		?int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $contentStatus,
		string $revisionTimestamp,
		string $lastModifier,
		string $version,
		int $originalVersionId,
		array $bodyContentIds,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$pageId,
				$spaceId,
				$confluenceTitle,
				$wikiTitle,
				$contentStatus,
				$revisionTimestamp,
				$lastModifier,
				$version,
				$originalVersionId,
				$bodyContentIds,
				$historicalIds,
				$properties,
				$collection
			]
		);
	}

	/**
	 * @param int $bodyContentId
	 * @param int $contentId
	 * @param string $class
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addBodyContent(
		int $bodyContentId,
		int $contentId,
		string $class,
		array $properties
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$bodyContentId,
				$contentId,
				$class,
				$properties
			]
		);
	}

	/**
	 * @param int $bodyContentId
	 * @param string $body
	 *
	 * @return bool
	 */
	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$bodyContentId,
				$body
			]
		);
	}

	/**
	 * @param int $attachmentId
	 * @param int|null $spaceId
	 * @param string $filename
	 * @param string $fileExtension
	 * @param int $containerContentId
	 * @param string $contentStatus
	 * @param string $version
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
	 * @param int $originalVersionId
	 * @param string $attachmentReference
	 * @param array $historicalIds
	 * @param array $properties
	 * @param array $collection
	 *
	 * @return bool
	 */
	public function addAttachment(
		int $attachmentId,
		?int $spaceId,
		string $filename,
		string $fileExtension,
		int $containerContentId,
		string $contentStatus,
		string $version,
		string $revisionTimestamp,
		string $lastModifier,
		int $originalVersionId,
		string $attachmentReference,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$attachmentId,
				$spaceId,
				$filename,
				$fileExtension,
				$containerContentId,
				$contentStatus,
				$version,
				$revisionTimestamp,
				$lastModifier,
				$originalVersionId,
				$attachmentReference,
				$historicalIds,
				$properties,
				$collection
			]
		);
	}

	/**
	 * @param int $commentId
	 * @param int $containerContentId
	 * @param string $class
	 * @param string $contentStatus
	 * @param string $userKey
	 * @param array $bodyContentIds
	 * @param string $created
	 * @param string $modified
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addComment(
		int $commentId,
		int $containerContentId,
		string $class,
		string $contentStatus,
		string $userKey,
		array $bodyContentIds,
		string $created,
		string $modified,
		array $properties
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$commentId,
				$containerContentId,
				$class,
				$contentStatus,
				$userKey,
				$bodyContentIds,
				$created,
				$modified,
				$properties
			]
		);
	}

	/**
	 * @param int $propertyId
	 * @param string $propertyName
	 * @param string $class
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addContentProperty(
		int $propertyId,
		string $propertyName,
		string $class,
		array $properties
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$propertyId,
				$propertyName,
				$class,
				$properties
			]
		);
	}

	/**
	 * @param int $labelId
	 * @param string $name
	 * @param string $namespace
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addLabel(
		int $labelId,
		string $name,
		string $namespace,
		array $properties
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$labelId,
				$name,
				$namespace,
				$properties
			]
		);
	}

	/**
	 * @param int $labellingId
	 * @param int $labelId
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addLabelling(
		int $labellingId,
		int $labelId,
		array $properties
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$labellingId,
				$labelId,
				$properties
			]
		);
	}

	/**
	 * @param string $userKey
	 * @param string $wikiUsername
	 * @param string $email
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$userKey,
				$wikiUsername,
				$email,
				$properties
			]
		);
	}

	/**
	 * @param int $templateId
	 * @param string $confluenceTitle
	 * @param int|null $spaceId
	 * @param string $wikiTitle
	 * @param string $revisionTimestamp
	 * @param string $version
	 * @param array $properties
	 * @param array $collection
	 * @param string $contentStatus
	 *
	 * @return bool
	 */
	public function addPageTemplate(
		int $templateId,
		string $confluenceTitle,
		?int $spaceId,
		string $wikiTitle = '',
		string $revisionTimestamp = '',
		string $version = '1',
		array $properties = [],
		array $collection = [],
		string $contentStatus = 'current'
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$templateId,
				$confluenceTitle,
				$spaceId,
				$wikiTitle,
				$revisionTimestamp,
				$version,
				$properties,
				$collection,
				$contentStatus
			]
		);
	}

	/**
	 * @param int $templateId
	 * @param string $content
	 *
	 * @return bool
	 */
	public function addPageTemplateContents(
		int $templateId,
		string $content,
	): bool {
		return $this->dispatch(
			__FUNCTION__,
			[
				$templateId,
				$content
			]
		);
	}

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @param string $text
	 *
	 * @return void
	 */
	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void {
		$this->dispatch(
			__FUNCTION__,
			[
				$templateId,
				$wikiTitle,
				$text
			]
		);
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
		return $this->dispatch(
			__FUNCTION__,
			[
				$spaceId,
				$confluenceTitle,
				$originalAttachmentFilename,
				$targetAttachmentFilename
			]
		);
	}

	public function addInvalidBodyContent( int $bodyContentId, string $text ): void {
		$this->dispatch(
			__FUNCTION__,
			[
				$bodyContentId,
				$text
			]
		);
	}

	public function addInvalidPageTemplateContent( int $templateId, string $text ): void {
		$this->dispatch(
			__FUNCTION__,
			[
				$templateId,
				$text
			]
		);
	}
}
