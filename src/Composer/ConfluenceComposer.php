<?php


namespace HalloWelt\MigrateConfluence\Composer;


use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;

class ConfluenceComposer extends ComposerBase {

	/**
	 * @var DataBuckets
	 */
	private $dataBuckets;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->dataBuckets = new DataBuckets( [
			'title-attachments',
			'title-revisions',
			'files'
		] );

		$this->dataBuckets->loadFromWorkspace( $this->workspace );
	}

	public function buildXML( Builder $builder ) {
		$pagesRevisions = $this->dataBuckets->getBucketData( 'title-revisions' );
		$filesMap = $this->dataBuckets->getBucketData( 'files' );
		$pageAttachmentsMap = $this->dataBuckets->getBucketData( 'title-attachments' );

		foreach( $pagesRevisions as $pageTitle => $pageRevision ) {
			$pageRevisionData = explode( '@', $pageRevision[0] );

			$bodyContentId = $pageRevisionData[0];
			$timestamp = explode( '-', $pageRevisionData[1] )[1];

			$pageContent = $this->workspace->getConvertedContent( $bodyContentId );

			$builder->addRevision( $pageTitle, $pageContent, $timestamp );

			// Append attachments
			if( !empty( $pageAttachmentsMap[$pageTitle] ) ) {
				$attachments = $pageAttachmentsMap[$pageTitle];
				foreach( $attachments as $attachment ) {
					if( isset( $filesMap[$attachment] ) ) {
						$filePath = $filesMap[$attachment][0];
						$attachmentContent = file_get_contents( $filePath );

						$this->workspace->saveUploadFile( $attachment, $attachmentContent );
					}
				}
			}
		}
	}

}