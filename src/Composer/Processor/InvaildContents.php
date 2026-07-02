<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class InvaildContents extends ContentProcessorBase {

	/** @var int[] */
	private array $invalidPageIds = [];

	/** @var int[] */
	private array $invalidBlogPostIds = [];

	/**
	 * @param Builder $builder
	 * @param DBComposerDataLookup $dataLookup
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		protected Builder $builder,
		protected DBComposerDataLookup $dataLookup,
		protected Workspace $workspace,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $builder, $output, $dest, $migrationConfig );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addInvalidPages();
		$this->addInvalidBlogPosts();
		$this->addInvalidPageTemplates();
		$this->addInvalidComments();

		$this->writeOutputFile();
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'invalid-contents';
	}

	/**
	 * @return void
	 */
	private function addInvalidPages(): void {
		$invalidPages = $this->collectBySpaceIdsAppend(
			fn ( int $spaceId ): array => $this->dataLookup->getInvalidPages( $spaceId ),
			fn (): array => $this->dataLookup->getInvalidPages()
		);

		foreach ( $invalidPages as $invalidPage ) {
			$pageId = (int)$invalidPage['page_id'];
			$wikiTitle = (string)( $invalidPage['wiki_title'] ?? '' );
			if ( $wikiTitle === '' ) {
				continue;
			}

			$this->invalidPageIds[$pageId] = $pageId;
			$this->output->writeln( "Processing skipped invalid page '$wikiTitle' ..." );

			$spaceId = $this->dataLookup->getSpaceIdForPageId( $pageId );
			$spaceDescriptions = [];
			$homepageId = null;
			if ( $spaceId !== null ) {
				$spaceDescriptions = $this->dataLookup->getSpaceDescriptionRevisionsForSpaceId( $spaceId );
				$homepageId = $this->dataLookup->getSpaceHomepageIdForSpaceId( $spaceId );
			}

			if ( $homepageId !== null && $pageId === $homepageId ) {
				$this->output->writeln(
					"Page '$wikiTitle' is a homepage, adding space description to page content if applicable..."
				);
			}

			$revisions = $this->dataLookup->getPageRevisionsForPageId( $pageId );
			foreach ( $revisions as $revision ) {
				$timestamp = (string)( $revision['revision_timestamp'] ?? '' );
				if ( !$this->hasValidContentIdsJson( (string)( $revision['body_content_ids'] ?? '' ) ) ) {
					continue;
				}
				$pageContent = $this->buildConvertedContentFromIdsJson(
					$this->workspace,
					(string)( $revision['body_content_ids'] ?? '' )
				);

				if ( $homepageId !== null ) {
					$pageContent .= $this->addSpaceDescriptionToMainPage(
						$pageId,
						$homepageId,
						$timestamp,
						$spaceDescriptions
					);
				}

				$this->addRevision( $wikiTitle, $pageContent, $timestamp );
			}
		}
	}

	/**
	 * @return void
	 */
	private function addInvalidBlogPosts(): void {
		$invalidBlogPosts = $this->collectBySpaceIdsAppend(
			fn ( int $spaceId ): array => $this->dataLookup->getInvalidBlogPosts( $spaceId ),
			fn (): array => $this->dataLookup->getInvalidBlogPosts()
		);

		foreach ( $invalidBlogPosts as $invalidBlogPost ) {
			$blogPostId = (int)$invalidBlogPost['page_id'];
			$wikiTitle = (string)( $invalidBlogPost['wiki_title'] ?? '' );
			if ( $wikiTitle === '' ) {
				continue;
			}

			$this->invalidBlogPostIds[$blogPostId] = $blogPostId;
			$this->output->writeln( "Processing skipped invalid blog post '$wikiTitle' ..." );

			$revisions = $this->dataLookup->getBlogPostRevisionsForBlogPostId( $blogPostId );
			foreach ( $revisions as $revision ) {
				$timestamp = (string)( $revision['revision_timestamp'] ?? '' );
				if ( !$this->hasValidContentIdsJson( (string)( $revision['body_content_ids'] ?? '' ) ) ) {
					continue;
				}
				$pageContent = $this->buildConvertedContentFromIdsJson(
					$this->workspace,
					(string)( $revision['body_content_ids'] ?? '' )
				);
				$this->addRevision( $wikiTitle, $pageContent, $timestamp, '', 'blog_post' );
			}
		}
	}

	/**
	 * @return void
	 */
	private function addInvalidComments(): void {
		if ( empty( $this->invalidPageIds ) && empty( $this->invalidBlogPostIds ) ) {
			return;
		}

		$comments = $this->collectBySpaceIdsAppend(
			fn ( int $spaceId ): array => array_merge(
				$this->dataLookup->getCommentsForPages( $spaceId ),
				$this->dataLookup->getCommentsForBlogPosts( $spaceId )
			),
			fn (): array => array_merge(
				$this->dataLookup->getCommentsForPages(),
				$this->dataLookup->getCommentsForBlogPosts()
			)
		);

		if ( empty( $comments ) ) {
			return;
		}

		$userkeyToUsernameMap = $this->buildUserkeyToUsernameMap( $this->dataLookup );
		$talkTitleToComments = [];

		foreach ( $comments as $comment ) {
			$containerId = (int)( $comment['container_id'] ?? 0 );
			$contentClass = (string)( $comment['content_class'] ?? '' );
			$wikiTitle = (string)( $comment['wiki_title'] ?? '' );
			if ( $wikiTitle === '' || $containerId === 0 ) {
				continue;
			}

			if ( !$this->isInvalidCommentContainer( $contentClass, $containerId ) ) {
				continue;
			}

			$bodyContentIds = json_decode( (string)( $comment['body_content_ids'] ?? '' ), true );
			if ( !is_array( $bodyContentIds ) ) {
				continue;
			}

			$bodyContentId = $bodyContentIds[0] ?? null;
			if ( $bodyContentId === null ) {
				continue;
			}

			$wikitext = trim( $this->workspace->getConvertedContent( (int)$bodyContentId ) );
			if ( $wikitext === '' ) {
				continue;
			}

			$creatorKey = (string)( $comment['user_key'] ?? '' );
			$username = $userkeyToUsernameMap[$creatorKey] ?? $creatorKey;

			$talkTitle = $this->buildTalkTitle( $wikiTitle );
			if ( !isset( $talkTitleToComments[$talkTitle] ) ) {
				$talkTitleToComments[$talkTitle] = [];
			}
			$index = count( $talkTitleToComments[$talkTitle] ) + 1;
			$talkTitleToComments[$talkTitle][$index] = [
				'type' => 'comment',
				'author' => $username,
				'created' => $comment['created'] ?? '',
				'modified' => $comment['modified'] ?? '',
				'title' => '',
				'block' => null,
				'wikitext' => $wikitext,
			];
		}

		foreach ( $talkTitleToComments as $talkTitle => $commentsData ) {
			$this->output->writeln( "Adding skipped invalid comments for Talk page '$talkTitle'..." );

			$slotText = json_encode( $commentsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
			$this->addRevision(
				$talkTitle,
				'',
				'',
				'',
				'wikitext',
				'text/x-wiki',
				[
					'role' => 'cs-comments',
					'model' => 'json',
					'format' => 'application/json',
					'text' => $slotText
				]
			);
		}
	}

	/**
	 * @return void
	 */
	private function addInvalidPageTemplates(): void {
		$invalidPageTemplates = $this->collectBySpaceIdsAppend(
			fn ( int $spaceId ): array => $this->dataLookup->getInvalidPageTemplates( $spaceId ),
			fn (): array => $this->dataLookup->getInvalidPageTemplates()
		);

		foreach ( $invalidPageTemplates as $invalidPageTemplate ) {
			$templateId = (int)$invalidPageTemplate['template_id'];
			$wikiTitle = (string)( $invalidPageTemplate['wiki_title'] ?? '' );
			if ( $wikiTitle === '' ) {
				continue;
			}

			$this->output->writeln( "Processing skipped invalid template '$wikiTitle' ..." );

			$revisions = $this->dataLookup->getPageTemplateRevisionsForTemplateId( $templateId );
			foreach ( $revisions as $revision ) {
				$timestamp = (string)( $revision['revision_timestamp'] ?? '' );
				if ( !$this->hasValidContentIdsJson( (string)( $revision['template_content_ids'] ?? '' ) ) ) {
					continue;
				}
				$pageContent = $this->buildConvertedContentFromIdsJson(
					$this->workspace,
					(string)( $revision['template_content_ids'] ?? '' ),
					'template content',
					'pt_'
				);
				$this->addRevision( $wikiTitle, $pageContent, $timestamp );
			}
		}
	}

	/**
	 * @param string $contentClass
	 * @param int $containerId
	 * @return bool
	 */
	private function isInvalidCommentContainer( string $contentClass, int $containerId ): bool {
		if ( $contentClass === 'Page' ) {
			return isset( $this->invalidPageIds[$containerId] );
		}

		if ( $contentClass === 'BlogPost' ) {
			return isset( $this->invalidBlogPostIds[$containerId] );
		}

		return false;
	}
}
