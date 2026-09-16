<?php

namespace HalloWelt\MigrateConfluence\Utility;

class Comment {

	/** @var array */
	private $propertyNames = [];

	/**
	 * @param int $commentId
	 * @param int|null $parentCommentId
	 * @param int $containerId
	 * @param array $bodyContentIds
	 * @param array $commentPropertiesData
	 * @param string $contentType
	 */
	public function __construct(
		private readonly int $commentId,
		private readonly ?int $parentCommentId,
		private readonly int $containerId,
		private readonly array $bodyContentIds,
		private readonly array $commentPropertiesData,
		private readonly string $contentType,
		private readonly string $created
	 ) {
		foreach ( $commentPropertiesData as $contentPropertyId => $contentPropertyData ) {
			if ( !empty( $contentPropertyData['name'] )
				&& !in_array( $contentPropertyData['name'], $this->propertyNames, true )
			) {
				$this->propertyNames[] = $contentPropertyData['name'];
			}
		}
	}

	/**
	 * @return int
	 */
	public function getId(): int {
		return $this->commentId;
	}

	/**
	 * @return int
	 */
	public function getContainerId(): int {
		return $this->containerId;
	}

	/**
	 * @return array
	 */
	public function getBodyContentIds(): array {
		return $this->bodyContentIds;
	}

	/**
	 * @return int|null
	 */
	public function getParentCommentId(): ?int {
		return $this->parentCommentId;
	}

	/**
	 * @return string
	 */
	public function getContentType(): string {
		return $this->contentType;
	}

	/**
	 * @return string
	 */
	public function getCreationTimestamp(): string {
		return $this->created;
	}

	/**
	 * @return bool
	 */
	public function isInlineComment(): bool {
		if ( in_array( 'inline-comment', $this->propertyNames, true ) ) {
			$value = $this->getPropertyValueFor( 'inline-comment', 'stringValue' );
			if ( $value === 'true' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function getCommentPropertiesData(): array {
		return $this->commentPropertiesData;
	}

	/**
	 * @param string $propertyName
	 * @param string $valueType
	 * @return mixed
	 */
	public function getPropertyValueFor( string $propertyName, string $valueType ): mixed {
		foreach ( $this->commentPropertiesData as $contentPropertyId => $contentPropertyData ) {
			if ( isset( $contentPropertyData['name'] )
				&& $contentPropertyData['name'] === $propertyName
			) {
				return $contentPropertyData[$valueType] ?? null;
			}
		}
		return null;
	}

}
