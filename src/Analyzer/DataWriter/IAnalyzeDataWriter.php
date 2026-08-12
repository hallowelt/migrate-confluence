<?php

namespace HalloWelt\MigrateConfluence\Analyzer\DataWriter;

use HalloWelt\MigrateConfluence\Database\DataWriter\IDataWriter;

interface IAnalyzeDataWriter extends IDataWriter {

	/**
	 * @param int $spaceId
	 * @param string $spaceKey
	 * @param string $spaceName
	 * @param string $namespacePrefix
	 * @param string $interwikiPrefix
	 * @param string $rootPage
	 * @param int $homepageId
	 * @param int $descriptionId
	 * @return bool
	 */
	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $namespacePrefix, string $interwikiPrefix, string $rootPage, int $homepageId, int $descriptionId
	): bool;

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
		int $spaceDescriptionId, string $contentStatus, string $version,
		int $originalVersionId, string $revisionTimestamp, array $bodyContentIds,
		array $labellingIds, array $properties, array $collection
	): bool;

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
	): bool;

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
	): bool;

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
	): bool;

	/**
	 * @param int $bodyContentId
	 * @param string $body
	 *
	 * @return bool
	 */
	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool;

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
	): bool;

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
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, array $bodyContentIds, string $created, string $modified, array $properties
	): bool;

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
	): bool;

	/**
	 * @param int $labelId
	 * @param string $name
	 * @param string $namespace
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addLabel(
		int $labelId, string $name, string $namespace, array $properties
	): bool;

	/**
	 * @param int $labellingId
	 * @param int $labelId
	 * @param array $properties
	 *
	 * @return bool
	 */
	public function addLabelling(
		int $labellingId, int $labelId, array $properties
	): bool;

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
	): bool;

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
	): bool;

	/**
	 * @param int $templateId
	 * @param string $content
	 *
	 * @return bool
	 */
	public function addPageTemplateContents(
		int $templateId,
		string $content,
	): bool;

	/**
	 * @param string $spaceKey
	 * @param string $source
	 * @param string $confluenceVersion
	 * @param string $exportDate
	 * @param string $timezoneId
	 * @param string $entitiesXmlPath
	 *
	 * @return void
	 */
	public function addExportProperties(
		string $spaceKey, string $source,
		string $confluenceVersion, string $exportDate,
		string $timezoneId, string $entitiesXmlPath
	): void;
}
