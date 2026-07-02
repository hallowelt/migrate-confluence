<?php

namespace HalloWelt\MigrateConfluence\Analyzer\DataWriter;

use HalloWelt\MigrateConfluence\Utility\PipeToDB;

class AnalyzePipeDataWriter implements IAnalyzeDataWriter {

	public function __construct( private PipeToDB $pipe ) {
	}

	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$this->pipe->send( __FUNCTION__, $type, $step, $caller, $text );
	}

	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $prefix, int $homepageId, int $descriptionId
	): bool {
		$this->pipe->send( __FUNCTION__, $spaceId, $spaceKey, $spaceName, $prefix, $homepageId, $descriptionId );
		return true;
	}

	public function addSpaceDescription(
		int $spaceDescriptionId, string $contentStatus, string $version,
		int $originalVersionId, string $revisionTimestamp, array $bodyContentIds,
		array $labellingIds, array $properties, array $collection
	): bool {
		$this->pipe->send(
			__FUNCTION__,
			$spaceDescriptionId,
			$contentStatus,
			$version,
			$originalVersionId,
			$revisionTimestamp,
			$bodyContentIds,
			$labellingIds,
			$properties,
			$collection
		);
		return true;
	}

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
		$this->pipe->send(
			__FUNCTION__,
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
		);
		return true;
	}

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
		$this->pipe->send(
			__FUNCTION__,
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
		);
		return true;
	}

	public function addBodyContent(
		int $bodyContentId,
		int $contentId,
		string $class,
		array $properties
	): bool {
		$this->pipe->send( __FUNCTION__, $bodyContentId, $contentId, $class, $properties );
		return true;
	}

	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool {
		$this->pipe->send( __FUNCTION__, $bodyContentId, $body );
		return true;
	}

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
		$this->pipe->send(
			__FUNCTION__,
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
		);
		return true;
	}

	public function addComment(
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, array $bodyContentIds, string $created, string $modified, array $properties
	): bool {
		$this->pipe->send(
			__FUNCTION__,
			$commentId,
			$containerContentId,
			$class,
			$contentStatus,
			$userKey,
			$bodyContentIds,
			$created,
			$modified,
			$properties
		);
		return true;
	}

	public function addContentProperty(
		int $propertyId,
		string $propertyName,
		string $class,
		array $properties
	): bool {
		$this->pipe->send( __FUNCTION__, $propertyId, $propertyName, $class, $properties );
		return true;
	}

	public function addLabel(
		int $labelId, string $name, string $namespace, array $properties
	): bool {
		$this->pipe->send( __FUNCTION__, $labelId, $name, $namespace, $properties );
		return true;
	}

	public function addLabelling(
		int $labellingId, int $labelId, array $properties
	): bool {
		$this->pipe->send( __FUNCTION__, $labellingId, $labelId, $properties );
		return true;
	}

	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): bool {
		$this->pipe->send( __FUNCTION__, $userKey, $wikiUsername, $email, $properties );
		return true;
	}

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
		$this->pipe->send(
			__FUNCTION__,
			$templateId,
			$confluenceTitle,
			$spaceId,
			$wikiTitle,
			$revisionTimestamp,
			$version,
			$properties,
			$collection,
			$contentStatus
		);
		return true;
	}

	public function addPageTemplateContents(
		int $templateId,
		string $content,
	): bool {
		$this->pipe->send( __FUNCTION__, $templateId, $content );
		return true;
	}

	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void {
		$this->pipe->send( __FUNCTION__, $templateId, $wikiTitle, $text );
	}
}
