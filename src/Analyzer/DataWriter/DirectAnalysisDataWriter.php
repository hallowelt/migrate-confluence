<?php

namespace HalloWelt\MigrateConfluence\Analyzer\DataWriter;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class DirectAnalysisDataWriter implements IAnalysisDataWriter {

	public function __construct( private WorkspaceDB $db ) {
	}

	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$this->db->addLogEntry( $type, $step, $caller, $text );
	}

	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $prefix, int $homepageId, int $descriptionId
	): bool {
		return $this->db->addSpace( $spaceId, $spaceKey, $spaceName, $prefix, $homepageId, $descriptionId );
	}

	public function addSpaceDescription(
		int $spaceDescriptionId, string $contentStatus, string $version,
		int $originalVersionId, string $revisionTimestamp, array $bodyContentIds,
		array $labellingIds, array $properties, array $collection
	): bool {
		return $this->db->addSpaceDescription(
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
		return $this->db->addPage(
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
		return $this->db->addBlogPost(
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
	}

	public function addBodyContent(
		int $bodyContentId,
		int $contentId,
		string $class,
		array $properties
	): bool {
		return $this->db->addBodyContent( $bodyContentId, $contentId, $class, $properties );
	}

	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool {
		return $this->db->addBodyContentBody( $bodyContentId, $body );
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
		return $this->db->addAttachment(
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
	}

	public function addComment(
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, array $bodyContentIds, string $created, string $modified, array $properties
	): bool {
		return $this->db->addComment(
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
	}

	public function addContentProperty(
		int $propertyId,
		string $propertyName,
		string $class,
		array $properties
	): bool {
		return $this->db->addContentProperty( $propertyId, $propertyName, $class, $properties );
	}

	public function addLabel(
		int $labelId, string $name, string $namespace, array $properties
	): bool {
		return $this->db->addLabel( $labelId, $name, $namespace, $properties );
	}

	public function addLabelling(
		int $labellingId, int $labelId, array $properties
	): bool {
		return $this->db->addLabelling( $labellingId, $labelId, $properties );
	}

	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): bool {
		return $this->db->addUser( $userKey, $wikiUsername, $email, $properties );
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
		return $this->db->addPageTemplate(
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
	}

	public function addPageTemplateContents(
		int $templateId,
		string $content,
	): bool {
		return $this->db->addPageTemplateContents( $templateId, $content );
	}

	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void {
		$this->db->addInvalidPageTemplateTitle( $templateId, $wikiTitle, $text );
	}
}
