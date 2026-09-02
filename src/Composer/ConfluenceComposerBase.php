<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\DataReader\ComposerDataReader;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPostComments;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPosts;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultFiles;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages;
use HalloWelt\MigrateConfluence\Composer\Processor\Files;
use HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents;
use HalloWelt\MigrateConfluence\Composer\Processor\PageComments;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use HalloWelt\MigrateConfluence\Composer\Processor\Templates;
use HalloWelt\MigrateConfluence\Composer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Output\Output;

abstract class ConfluenceComposerBase extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/** @var MigrationConfig */
	protected MigrationConfig $migrationConfig;

	/** @var string */
	protected string $dest = '';

	protected ComposerDataReader $dataLookup;

	/** @var ComposerSkipHelper */
	protected ComposerSkipHelper $skipHelper;

	/** @var WorkspaceDB|null */
	protected ?WorkspaceDB $workspaceDB = null;

	/** @var DBLog|null */
	protected ?DBLog $dbLog = null;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		if ( isset( $config['config'] ) ) {
			$this->migrationConfig = new MigrationConfig( $config['config'] );
		} else {
			$this->migrationConfig = new MigrationConfig( [] );
		}

		$this->workspace = $workspace;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	/**
	 * @inheritDoc
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ): void {
		$this->workspaceDB = WorkspaceDB::open( $this->dest );
		$this->dataLookup = new ComposerDataReader( $this->workspaceDB );
		$this->dbLog = new DBLog( $this->workspaceDB );
		$this->logMigrateConfluenceToolVersion( $this->dbLog );
		$this->skipHelper = new ComposerSkipHelper( $this->dataLookup, $this->migrationConfig );

		$this->doBuildXML( $builder );
	}

	abstract protected function doBuildXML( Builder $builder ): void;

	/**
	 * @param string[] $wikiNames
	 * @return void
	 */
	protected function copySharedDirectoryToWikiDirectories( array $wikiNames ): void {
		$sharedPath = $this->dest . '/result/_shared';
		if ( !is_dir( $sharedPath ) ) {
			return;
		}

		foreach ( $wikiNames as $wikiName ) {
			$wikiSharedPath = $this->dest . '/result/' . $wikiName . '/_shared';
			$this->copyDirectoryRecursively( $sharedPath, $wikiSharedPath );
		}

		$this->deleteDirectoryRecursively( $sharedPath );
	}

	/**
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @return void
	 */
	protected function copyDirectoryRecursively( string $sourcePath, string $targetPath ): void {
		if ( !is_dir( $targetPath ) && !mkdir( $targetPath, 0755, true ) && !is_dir( $targetPath ) ) {
			throw new \RuntimeException( 'Failed to create target directory: ' . $targetPath );
		}

		$sourceItems = scandir( $sourcePath );
		if ( $sourceItems === false ) {
			throw new \RuntimeException( 'Failed to read source directory: ' . $sourcePath );
		}

		foreach ( $sourceItems as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$currentSourcePath = $sourcePath . '/' . $item;
			$currentTargetPath = $targetPath . '/' . $item;

			if ( is_dir( $currentSourcePath ) ) {
				$this->copyDirectoryRecursively( $currentSourcePath, $currentTargetPath );
				continue;
			}

			if ( !copy( $currentSourcePath, $currentTargetPath ) ) {
				throw new \RuntimeException( 'Failed to copy shared file: ' . $currentSourcePath );
			}

			$sourcePerms = fileperms( $currentSourcePath );
			if ( $sourcePerms !== false ) {
				chmod( $currentTargetPath, $sourcePerms & 0777 );
			}
		}
	}

	/**
	 * @param string $path
	 * @return void
	 */
	protected function deleteDirectoryRecursively( string $path ): void {
		$items = scandir( $path );
		if ( $items === false ) {
			throw new \RuntimeException( 'Failed to read directory for deletion: ' . $path );
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$currentPath = $path . '/' . $item;
			if ( is_dir( $currentPath ) ) {
				$this->deleteDirectoryRecursively( $currentPath );
			} else {
				if ( !unlink( $currentPath ) ) {
					throw new \RuntimeException( 'Failed to remove file: ' . $currentPath );
				}
			}
		}

		if ( !rmdir( $path ) ) {
			throw new \RuntimeException( 'Failed to remove directory: ' . $path );
		}
	}

	/**
	 * @param array $spaces
	 * @return array
	 */
	protected function buildSpacesMap( array $spaces ): array {
		$map = [];
		foreach ( $spaces as $space ) {
			$spaceId = (int)$space['space_id'];
			$namespace = empty( $space['namespace_prefix'] ) ? 'NS_MAIN' : $space['namespace_prefix'];

			if ( !isset( $map[$namespace] ) ) {
				$map[$namespace] = [];
			}
			$map[$namespace][$spaceId] = $space;
		}

		return $map;
	}

	/**
	 * @param Builder $builder
	 * @return array
	 */
	protected function initProcessorsForSharedContent(
		Builder $builder
	): array {
		return [
			new DefaultFiles(
				$this->dataLookup, $this->workspace, $this->output, $this->dest, $this->migrationConfig
			),
			new DefaultPages(
				$builder, $this->output, $this->dest, $this->migrationConfig
			),
		];
	}

	/**
	 * @param Builder $builder
	 * @return array
	 */
	protected function initProcessorsForSpaceContent(
		Builder $builder, ComposerDeploymentInfo $deploymentInfo
	): array {
		return [
			new Files(
				$this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new Pages(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new BlogPosts(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new Templates(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new PageComments(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new BlogPostComments(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new Users(
				$this->dataLookup, $this->output, $this->dest
			),
			new InvalidContents(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig
			),
		];
	}

	/**
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param string $subDir
	 * @return void
	 */
	protected function writeDeploymentLog(
		ComposerDeploymentInfo $deploymentInfo, string $subDir
	): void {
		$content = "# Namespaces\n\n";
		$namespaces = $deploymentInfo->getNamespaces();
		$content .= $this->makeListContent( $namespaces );

		$content .= "\n\n# File extensions\n\n";
		$fileExtensions = $deploymentInfo->getFileExtensions();
		$content .= $this->makeListContent( $fileExtensions );

		$logDir = $this->ensureDeploymentInfoPath( $subDir );
		file_put_contents( $logDir . "/deployment.txt", $content );
	}

	/**
	 * @param string $namespace
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param string $subDir
	 * @return void
	 */
	protected function writeSkippedPagesLog(
		string $namespace, ComposerDeploymentInfo $deploymentInfo, string $subDir = ''
	): void {
		$skippedPages = $deploymentInfo->getSkippedPages();
		$content = $this->makeListContent( $skippedPages );

		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/skipped_pages.log", $content );
	}

	/**
	 * @param DBLog $dbLog
	 * @return void
	 */
	protected function writeUserReadableDBLog( DBLog $dbLog ): void {
		$this->writeDBLogContent( $dbLog, 'error' );
		$this->writeDBLogContent( $dbLog, 'warning' );
		$this->writeDBLogContent( $dbLog, 'info' );
	}

	/**
	 * @param array $data
	 * @return string
	 */
	protected function makeListContent( array $data ): string {
		$content = '';
		foreach ( $data as $item ) {
			$content .= "$item\n";
		}
		return $content;
	}

	/**
	 * @param DBLog $dbLog
	 * @param string $type
	 * @return void
	 */
	protected function writeDBLogContent( DBLog $dbLog, string $type ): void {
		$data = $dbLog->getLogEntriesForStep( 'compose', $type );
		$content = '';
		foreach ( $data as $item ) {
			$content .= $item['caller'] . ': ' . $item['text'] . "\n";
		}
		file_put_contents( $this->dest . "/composer_{$type}.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidPagesLog( array $spaceIds, string $namespace = '', string $subDir = '' ): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidPages( (int)$spaceId ) );
		}
		$content = "page_id;space_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['page_id'] . ';';
			$line .= $item['space_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_pages.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidBlogPostsLog( array $spaceIds, string $namespace = '', string $subDir = '' ): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidBlogPosts( (int)$spaceId ) );
		}
		$content = "blog_post_id;space_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['blog_post_id'] . ';';
			$line .= $item['space_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_blog_posts.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidPageTemplatesLog(
		array $spaceIds, string $namespace = '', string $subDir = ''
	): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidPageTemplates( (int)$spaceId ) );
		}
		$content = "template_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['template_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_page_templates.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidAttachmentsLog(
		array $spaceIds, string $namespace = '', string $subDir = ''
	): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidAttachments( (int)$spaceId ) );
		}
		$content = "attachment_id;page_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['attachment_id'] . ';';
			$line .= $item['page_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_attachments.log", $content );
	}

	/**
	 * @param string $subDir
	 * @return string
	 */
	protected function ensureLogPath( string $subDir ): string {
		$path = $this->dest . "/result";
		$path .= "/$subDir/log";
		if ( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * @param string $subDir
	 * @return string
	 */
	protected function ensureDeploymentInfoPath( string $subDir ): string {
		$path = $this->dest . "/result";
		$path .= "/$subDir";
		if ( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * Add version information of the migrate confluence tool to the database
	 *
	 * @param DBLog $dbLog
	 * @return void
	 */
	protected function logMigrateConfluenceToolVersion( DBLog $dbLog ): void {
		$dbLog->addLogEntry(
			'info',
			'compose',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @param string $subDir
	 * @return void
	 */
	protected function addSpaceImportHelper( string $subDir = '' ): void {
		$sourcePaths = glob( __DIR__ . '/_shell/*' );
		if ( $sourcePaths === false || $sourcePaths === [] ) {
			return;
		}

		if ( $subDir !== '' ) {
			$sourcePath = __DIR__ . '/_shell/spaceimport.sh';
			$targetDir = $this->dest . "/result/$subDir";
			$this->copyShellScript( $sourcePath, $targetDir . '/spaceimport.sh' );
		}
	}

	/**
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @return void
	 */
	protected function copyShellScript( string $sourcePath, string $targetPath ): void {
		if ( !file_exists( $sourcePath ) ) {
			throw new \RuntimeException( 'Could not find shell script: ' . $sourcePath );
		}

		if ( !copy( $sourcePath, $targetPath ) ) {
			throw new \RuntimeException( 'Failed to copy shell script: ' . $sourcePath );
		}

		$sourcePerms = fileperms( $sourcePath );
		if ( $sourcePerms !== false ) {
			chmod( $targetPath, $sourcePerms & 0777 );
		}
	}
}
