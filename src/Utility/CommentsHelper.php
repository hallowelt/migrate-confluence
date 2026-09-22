<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class CommentsHelper {

	/** @var Comment[] */
	private array $comments = [];

	/** @var Comment[] */
	private array $inlineComments = [];

	/** @var Comment[] */
	private array $pageComments = [];

	/** @var Comment[] */
	private array $blogPostComments = [];

	/** @var array */
	private array $commentIdToParentCommentIdMap = [];

	/** @var array */
	private array $commentIdToChildCommentIdMap = [];

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private readonly WorkspaceDB $workspaceDB
	) {
		$this->buildObjects();
		$this->sortObjects();
	}

	/**
	 * @return Comment[]
	 */
	public function getInlineComments(): array {
		return $this->inlineComments;
	}

	/**
	 * @return Comment[]
	 */
	public function getPageComments(): array {
		return $this->pageComments;
	}

	/**
	 * @return Comment[]
	 */
	public function getBlogPostComments(): array {
		return $this->blogPostComments;
	}

	/**
	 * @return array
	 */
	public function getCommentIdToParentCommentIdMap(): array {
		return $this->commentIdToParentCommentIdMap;
	}

	/**
	 * @return array
	 */
	public function getCommentIdToChildCommentIdMap(): array {
		return $this->commentIdToChildCommentIdMap;
	}

	/**
	 * @return void
	 */
	private function buildObjects(): void {
		$allComments = $this->workspaceDB->getComments();

		foreach ( $allComments as $commentData ) {
			if ( empty( $commentData ) ) {
				continue;
			}

			if ( empty( $commentData['comment_id'] ) ) {
				continue;
			}
			$commentId = (int)$commentData['comment_id'];

			if ( $commentData['content_status'] !== 'current' ) {
				// Do not process deleted or draft comments
				continue;
			}

			$containerId = null;
			if ( empty( $commentData['container_id'] ) ) {
				continue;
			}
			$containerId = (int)$commentData['container_id'];

			$contentType = '';
			if ( $this->workspaceDB->pageIdExists( $containerId ) ) {
				$contentType = 'page';
			} elseif ( $this->workspaceDB->blogPostIdExists( $containerId ) ) {
				$contentType = 'blog-post';
			}

			$commentProperties = [];
			if ( isset( $commentData['properties'] ) ) {
				$commentProperties = json_decode( $commentData['properties'], true );
			}

			$parentCommentId = null;
			if ( isset( $commentProperties['parent'] ) ) {
				// Comment is a answer to another comment
				$parentCommentId = (int)$commentProperties['parent'];
				$this->commentIdToParentCommentIdMap[$commentId] = $parentCommentId;
			}
			/*
			$childCommentId = null;
			if ( isset( $commentProperties['children'] ) ) {
				// Comment has a child comment
				$childCommentId = (int)$commentProperties['children'];
				$this->commentIdToChildCommentIdMap[$childCommentId] = $commentId;
			}
			*/

			$commentCollection = [];
			if ( isset( $commentData['collection'] ) ) {
				// The collections holds contentProperty IDs under 'contentProperties' key
				// which are related to id's in content_properties table in the database.
				$commentCollection = json_decode( $commentData['collection'], true );
			}

			$contentProperyIds = [];
			if ( isset( $commentCollection['contentProperties'] ) ) {
				$contentProperyIds = $commentCollection['contentProperties'];
			}

			$commentPropertiesData = [];
			if ( !empty( $contentProperyIds ) ) {
				foreach ( $contentProperyIds as $contentPropertyId ) {
					$id = (int)$contentPropertyId;
					$contentProperty = $this->workspaceDB->getContentPopertyById( $id );

					$propertyData = [];
					if ( isset( $contentProperty['properties'] ) ) {
						$propertyData = json_decode( $contentProperty['properties'], true );
					}

					$commentPropertiesData[$id] = $propertyData;
				}
			}

			$bodyContentIds = [];
			if ( isset( $commentData['body_content_ids'] ) ) {
				$bodyContentIds = json_decode( $commentData['body_content_ids'], true );
			}

			$this->comments[$commentId] = new Comment(
				$commentId,
				$parentCommentId,
				$containerId,
				$bodyContentIds,
				$commentPropertiesData,
				$contentType,
				$commentData['created'] ?? ''
			);
		}
	}

	/**
	 * @return void
	 */
	private function sortObjects(): void {
		foreach ( $this->comments as $commentId => $comment ) {
			if ( $comment->isInlineComment() ) {

				// This is a inline comment
				$this->inlineComments[$commentId] = $comment;
			} elseif ( $comment->getContentType() === 'page' ) {
				// This is a page comment related to a page
				$this->pageComments[$commentId] = $comment;
			} elseif ( $comment->getContentType() === 'blog-post' ) {
				// This is a page comment related to a blog_post
				$this->blogPostComments[$commentId] = $comment;
			}
		}
	}

}
