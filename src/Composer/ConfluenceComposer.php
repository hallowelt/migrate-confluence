<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use Symfony\Component\Console\Output\Output;

class ConfluenceComposer extends ComposerBase implements IOutputAwareInterface {

	/**
	 * @var DataBuckets
	 */
	private $dataBuckets;

	/**
	 * @var Output
	 */
	private $output = null;

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

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	public function buildXML( Builder $builder ) {
		$pagesRevisions = $this->dataBuckets->getBucketData( 'title-revisions' );
		$filesMap = $this->dataBuckets->getBucketData( 'files' );
		$pageAttachmentsMap = $this->dataBuckets->getBucketData( 'title-attachments' );

		foreach ( $pagesRevisions as $pageTitle => $pageRevision ) {
			$this->output->writeln( "\nProcessing: $pageTitle\n" );

			$pageRevisionData = explode( '@', $pageRevision[0] );

			$timestamp = explode( '-', $pageRevisionData[1] )[1];

			$bodyContentIds = $pageRevisionData[0];
			$bodyContentIdsArr = explode( '/', $bodyContentIds );

			$pageContent = "";
			foreach ( $bodyContentIdsArr as $bodyContentId ) {
				$this->output->writeln( "Getting '$bodyContentId' body content..." );

				$pageContent .= $this->workspace->getConvertedContent( $bodyContentId ) . "\n";
			}

			$builder->addRevision( $pageTitle, $pageContent, $timestamp );

			// Append attachments
			if ( !empty( $pageAttachmentsMap[$pageTitle] ) ) {
				$this->output->writeln( "\nPage has attachments. Adding them...\n" );

				$attachments = $pageAttachmentsMap[$pageTitle];
				foreach ( $attachments as $attachment ) {
					$this->output->writeln( "Attachment: $attachment" );
					if ( isset( $filesMap[$attachment] ) ) {
						$filePath = $filesMap[$attachment][0];
						$attachmentContent = file_get_contents( $filePath );

						$this->workspace->saveUploadFile( $attachment, $attachmentContent );
					} else {
						$this->output->writeln( "Attachment file was not found!" );
					}
				}
			}
		}
	}

}
