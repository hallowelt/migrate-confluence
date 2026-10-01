<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\CommentsHelper;

/**
 */
class PrepareComments extends ProcessorBase {

	/** @var array */
	private array $commentIdToParentCommentId = [];

	/** @var array */
	private array $commentIdToChildCommentId = [];

	/**
	 * @return void
	 * @throws Exception
	 */
	public function execute(): void {
		$commentsHelper = new CommentsHelper( $this->workspaceDB );

		$this->commentIdToParentCommentId = $commentsHelper->getCommentIdToParentCommentIdMap();

		$this->addInlineComments( $commentsHelper );
		$this->addPageComments( $commentsHelper );
		$this->addBlogPostComments( $commentsHelper );
	}

	/**
	 * @param CommentsHelper $commentsHelper
	 * @return void
	 */
	public function addPageComments( CommentsHelper $commentsHelper ): void {
		$pageComments = $commentsHelper->getPageComments();

		foreach ( $pageComments as $id => $comment ) {
			$this->workspaceDB->addPageComment(
				$id,
				$comment->getContainerId(),
				''
			);
		}
	}

	/**
	 * @param CommentsHelper $commentsHelper
	 * @return void
	 */
	public function addBlogPostComments( CommentsHelper $commentsHelper ): void {
		$blogPostComments = $commentsHelper->getBlogPostComments();

		foreach ( $blogPostComments as $id => $comment ) {
			$this->workspaceDB->addBlogPostComment(
				$id,
				$comment->getContainerId(),
				''
			);
		}
	}

	/**
	 * @param CommentsHelper $commentsHelper
	 * @return void
	 */
	private function addInlineComments( CommentsHelper $commentsHelper ): void {
		$inlineComments = $commentsHelper->getInlineComments();

		$bodyContents = [];
		foreach ( $inlineComments as $id => $comment ) {
			$bodyContentId = $comment->getBodyContentIds();
			$content = $this->workspaceDB->getBodyContentBodiesForBodyContentId(
				$bodyContentId
			);
			$bodyContents[$id] = $content;
		}

		$comments = [];
		foreach ( $inlineComments as $id => $comment ) {
			$commentId = $id;

			$parentId = $this->commentIdToParentCommentId[$id] ?? '';

			$originalText = $comment->getPropertyValueFor(
				'inline-original-selection',
				'stringValue'
			);

			$commentRef = $comment->getPropertyValueFor( 'inline-marker-ref', 'stringValue' );

			// If a inline comment does not have a ref it is a answer to another comment.
			// In that case we do not add status because only the parent status is relevant.
			$status = null;
			if ( !empty( $commentRef ) ) {
				$status = $comment->getPropertyValueFor( 'status', 'stringValue' );
				if ( empty( $status ) ) {
					// Active comments do not have a status, deleted are not part of the export
					// and resolved have status resolved.

					$status = 'active';
				}
			}

			$commentText = '';
			foreach ( $bodyContents[$id] ?? [] as $body ) {
				$commentText .= $body;
			}

			$comments[] = [
				'commentId' => (int)$commentId,
				'parentId' => (int)$parentId,
				'container_id' => $comment->getContainerId(),
				'commentRef' => $commentRef,
				'originalText' => $originalText,
				'commentText' => $commentText,
				'created' => (int)$comment->getCreationTimestamp(),
				'status' => $status
			];
		}

		foreach ( $comments as $comment ) {
			$this->workspaceDB->addInlineComments(
				$comment['commentId'],
				$comment['parentId'],
				$comment['container_id'],
				$comment['commentRef'],
				$comment['originalText'],
				$comment['commentText'],
				$comment['created'],
				$comment['status']
			);
		}
	}
}
