<?php

namespace HalloWelt\MigrateConfluence\Analyzer\DataWriter;

interface IAnalyzeDataWriter {

	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void;

	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $prefix, int $homepageId, int $descriptionId
	): bool;

	public function addSpaceDescription(
		int $spaceDescriptionId, string $contentStatus, string $version,
		int $originalVersionId, string $revisionTimestamp, array $bodyContentIds,
		array $labellingIds, array $properties, array $collection
	): bool;

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
	): bool;

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
	): bool;

	public function addBodyContent(
		int $bodyContentId,
		int $contentId,
		string $class,
		array $properties
	): bool;

	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool;

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
	): bool;

	public function addComment(
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, array $bodyContentIds, string $created, string $modified, array $properties
	): bool;

	public function addContentProperty(
		int $propertyId,
		string $propertyName,
		string $class,
		array $properties
	): bool;

	public function addLabel(
		int $labelId, string $name, string $namespace, array $properties
	): bool;

	public function addLabelling(
		int $labellingId, int $labelId, array $properties
	): bool;

	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): bool;

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
	): bool;

	public function addPageTemplateContents(
		int $templateId,
		string $content,
	): bool;

	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void;
}
