<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\IComposer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\DataReader\IComposerDataReader;
use HalloWelt\MigrateConfluence\Composer\DataReader\IComposerDataReaderAware;
use HalloWelt\MigrateConfluence\Composer\DataWriter\IComposerDataWriter;
use HalloWelt\MigrateConfluence\Composer\DataWriter\IComposerDataWriterAware;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPostComments;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPosts;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultFiles;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages;
use HalloWelt\MigrateConfluence\Composer\Processor\Files;
use HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents;
use HalloWelt\MigrateConfluence\Composer\Processor\PageComments;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use HalloWelt\MigrateConfluence\Composer\Processor\RequiredTemplates;
use HalloWelt\MigrateConfluence\Composer\Processor\Sidebar;
use HalloWelt\MigrateConfluence\Composer\Processor\Templates;
use HalloWelt\MigrateConfluence\Composer\Processor\Users;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\IWorkspaceAware;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Output\Output;

class ConfluenceComposer extends ComposerBase implements
	IOutputAwareInterface,
	IDestinationPathAware,
	IComposerDataReaderAware,
	IComposerDataWriterAware,
	IMigrationConfigAware,
	IWorkspaceAware
{

	/**
	 * @var Output|null
	 */
	private ?Output $output = null;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var string */
	private string $dest = '';

	/** @var IComposerDataReader */
	private IComposerDataReader $reader;

	/** @var IComposerDataWriter */
	private IComposerDataWriter $writer;

	private ?Workspace $workspace = null;

	/**
	 * @var ComposerSkipHelper
	 */
	private ComposerSkipHelper $skipHelper;

	/**
	 * @return IComposer
	 */
	public static function factory(): IComposer {
		return new static();
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	public function setDataReader( IComposerDataReader $reader ): void {
		$this->reader = $reader;
	}

	public function setDataWriter( IComposerDataWriter $writer ): void {
		$this->writer = $writer;
	}

	public function setMigrationConfig( MigrationConfig $migrationConfig ): void {
		$this->migrationConfig = $migrationConfig;
	}

	public function setWorkspace( Workspace $workspace ): void {
		$this->workspace = $workspace;
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
		if ( $this->output === null ) {
			throw new \RuntimeException( 'Output is not set' );
		}
		if ( !isset( $this->reader ) ) {
			throw new \RuntimeException( 'Data reader is not set' );
		}
		if ( !isset( $this->writer ) ) {
			throw new \RuntimeException( 'Data writer is not set' );
		}
		if ( !isset( $this->migrationConfig ) ) {
			throw new \RuntimeException( 'Migration config is not set' );
		}
		if ( $this->workspace === null ) {
			throw new \RuntimeException( 'Workspace is not set' );
		}

		$this->logMigrateConfluenceToolVersion();

		$this->skipHelper = new ComposerSkipHelper( $this->reader, $this->migrationConfig );

		// Run shared content processors
		$sharedProcessors = $this->initProcessorsForSharedContent(
			$builder, $this->skipHelper, new ComposerDeploymentInfo()
		);

		foreach ( $sharedProcessors as $processor ) {
			$processor->setSubDir( '_shared' );
			$processor->execute();
		}

		// Run space dependent processors for each space
		$wikiNames = $this->reader->getWikisConfigWikiNames();
		if ( $wikiNames === [] ) {
			// If no wikis are configured, we will process all spaces and group them by namespace
			$this->output->writeln( "Data is not assigned to any wikis." );

			$spaces = $this->reader->getSpaces();
			if ( $spaces === [] ) {
				$this->output->writeln( "No spaces found." );
			}

			$spacesMap = $this->buildSpacesMap( $spaces );
			$this->storeMigrationResult( $spacesMap, $builder );

		} else {
			// If wikis are configured, we will process spaces grouped by wiki name
			$this->output->writeln( "Data is assigned to some wikis." );
			$this->copySharedDirectoryToWikiDirectories( $wikiNames );

			foreach ( $wikiNames as $wikiName ) {
				$spaces = $this->reader->getWikisConfigSpacesForWikiName( $wikiName );
				if ( $spaces === [] ) {
					$this->output->writeln( "No spaces found for wiki '$wikiName'." );
					continue;
				}

				$spacesMap = $this->buildSpacesMap( $spaces );
				$this->storeMigrationResult( $spacesMap, $builder, $wikiName );

				$this->output->writeln( "Processing wiki '$wikiName' with " . count( $spaces ) . " spaces." );
			}
		}

		$this->writeUserReadableLog();
	}

	/**
	 * @param string[] $wikiNames
	 * @return void
	 */
	private function copySharedDirectoryToWikiDirectories( array $wikiNames ): void {
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
	private function copyDirectoryRecursively( string $sourcePath, string $targetPath ): void {
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
	private function deleteDirectoryRecursively( string $path ): void {
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
	 * @param array $spacesMap
	 * @param Builder $builder
	 * @param string $wikiName
	 * @return void
	 */
	private function storeMigrationResult( array $spacesMap, Builder $builder, string $wikiName = '' ): void {
		// Run processors for each namespace
		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				$this->output->writeln( "Skip namespace '$namespace' by configuration." );
				continue;
			}
			$deploymentInfo = new ComposerDeploymentInfo();
			$deploymentInfo->addNamespace( $namespace );

			$subdir = '';
			if ( $wikiName !== '' ) {
				$subdir = $wikiName . '/';
			}
			$subdir .= $namespace;

			$processors = $this->initProcessorsForSpaceContent( $builder, $this->skipHelper, $deploymentInfo );

			$spaceIds = array_keys( $spaces );
			foreach ( $processors as $processor ) {
				$processor->setSubDir( $subdir );
				if ( $processor instanceof ISpaceIdsDependentProcessor ) {
					$processor->setCurrentSpaceIds( $spaceIds );
				}
				if ( $processor instanceof ISpacesDependentProcessor ) {
					$processor->setCurrentSpaces( $spaces );
				}

				$processor->execute();
			}

			// Add enhanced sidebar to the namespace directory, not shared. It is a namespace-scoped feature.
			$sidebarProcessor = new Sidebar(
				$this->reader, $this->migrationConfig, $this->dest
			);
			if ( $wikiName !== '' ) {
				$sidebarProcessor->setSubDir( $wikiName );
			} else {
				$sidebarProcessor->setSubDir( $namespace );
			}
			if ( $sidebarProcessor instanceof ISpaceIdsDependentProcessor ) {
				$sidebarProcessor->setCurrentSpaceIds( $spaceIds );
			}
			if ( $sidebarProcessor instanceof ISpacesDependentProcessor ) {
				$sidebarProcessor->setCurrentSpaces( $spaces );
			}
			$sidebarProcessor->execute();

			$this->writeDeploymentLog( $namespace, $deploymentInfo, $wikiName );
			$this->writeSkippedPagesLog( $namespace, $deploymentInfo, $wikiName );

			$this->writeInvalidPagesLog( $spaceIds, $namespace, $wikiName );
			$this->writeInvalidBlogPostsLog( $spaceIds, $namespace, $wikiName );
			$this->writeInvalidAttachmentsLog( $spaceIds, $namespace, $wikiName );
			$this->writeInvalidPageTemplatesLog( $spaceIds, $namespace, $wikiName );

			$this->addImportHelper( $namespace, $wikiName );
		}
	}

	/**
	 * @param array $spaces
	 * @return array
	 */
	private function buildSpacesMap( array $spaces ): array {
		$map = [];
		foreach ( $spaces as $space ) {
			$spaceId = (int)$space['space_id'];
			$namespace = $space['namespace_prefix'] ?? 'NS_MAIN';

			if ( !isset( $map[$namespace] ) ) {
				$map[$namespace] = [];
			}
			$map[$namespace][$spaceId] = $space;
		}

		return $map;
	}

	/**
	 * @param Builder $builder
	 * @param ComposerSkipHelper $skipHelper
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @return array
	 */
	private function initProcessorsForSharedContent(
		Builder $builder, ComposerSkipHelper $skipHelper, ComposerDeploymentInfo $deploymentInfo
	): array {
		return [
			new DefaultFiles(
				$this->reader, $this->workspace, $this->output, $this->dest, $this->migrationConfig
			),
			new DefaultPages(
				$builder, $this->output, $this->dest, $this->migrationConfig
			),
			new RequiredTemplates(
				$this->reader, $builder, $this->output, $this->dest, $this->migrationConfig
			),
		];
	}

	/**
	 * @param Builder $builder
	 * @param ComposerSkipHelper $skipHelper
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @return array
	 */
	private function initProcessorsForSpaceContent(
		Builder $builder, ComposerSkipHelper $skipHelper, ComposerDeploymentInfo $deploymentInfo
	): array {
		return [
			new Files(
				$this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Pages(
				$builder, $this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new BlogPosts(
				$builder, $this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Templates(
				$builder, $this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new PageComments(
				$builder, $this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new BlogPostComments(
				$builder, $this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Users(
				$this->reader, $this->output, $this->dest
			),
			new InvalidContents(
				$builder, $this->reader, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig
			),
		];
	}

	/**
	 * @param string $namespace
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param string $wikiName
	 * @return void
	 */
	private function writeDeploymentLog(
		string $namespace, ComposerDeploymentInfo $deploymentInfo, string $wikiName = ''
	): void {
		$content = "# Namespaces\n\n";
		$namespaces = $deploymentInfo->getNamespaces();
		$content .= $this->makeListContent( $namespaces );

		$content .= "\n\n# File extensions\n\n";
		$fileExtensions = $deploymentInfo->getFileExtensions();
		$content .= $this->makeListContent( $fileExtensions );

		$logDir = $this->ensureNamespacePath( $namespace, $wikiName );
		file_put_contents( $logDir . "/deployment.txt", $content );
	}

	/**
	 * @param string $namespace
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param string $wikiName
	 * @return void
	 */
	private function writeSkippedPagesLog(
		string $namespace, ComposerDeploymentInfo $deploymentInfo, string $wikiName = ''
	): void {
		$skippedPages = $deploymentInfo->getSkippedPages();
		$content = $this->makeListContent( $skippedPages );

		$logDir = $this->ensureNamespacePath( $namespace, $wikiName );
		file_put_contents( $logDir . "/skipped_pages.log", $content );
	}

	/**
	 * @return void
	 */
	private function writeUserReadableLog(): void {
		$this->writeLogContent( 'error' );
		$this->writeLogContent( 'warning' );
		$this->writeLogContent( 'info' );
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private function makeListContent( array $data ): string {
		$content = '';
		foreach ( $data as $item ) {
			$content .= "$item\n";
		}
		return $content;
	}

	/**
	 * @param string $type
	 * @return void
	 */
	private function writeLogContent( string $type ): void {
		$data = $this->reader->getLogEntriesForStep( 'compose', $type );
		$content = '';
		foreach ( $data as $item ) {
			$content .= $item['caller'] . ': ' . $item['text'] . "\n";
		}
		file_put_contents( $this->dest . "/composer_{$type}.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $wikiName
	 *
	 * @return void
	 */
	private function writeInvalidPagesLog( array $spaceIds, string $namespace = '', string $wikiName = '' ): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->reader->getInvalidPages( (int)$spaceId ) );
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
		$logDir = $this->ensureNamespacePath( $namespace, $wikiName );
		file_put_contents( $logDir . "/invalid_pages.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $wikiName
	 *
	 * @return void
	 */
	private function writeInvalidBlogPostsLog( array $spaceIds, string $namespace = '', string $wikiName = '' ): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->reader->getInvalidBlogPosts( (int)$spaceId ) );
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
		$logDir = $this->ensureNamespacePath( $namespace, $wikiName );
		file_put_contents( $logDir . "/invalid_blog_posts.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $wikiName
	 *
	 * @return void
	 */
	private function writeInvalidPageTemplatesLog(
		array $spaceIds, string $namespace = '', string $wikiName = ''
	): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->reader->getInvalidPageTemplates( (int)$spaceId ) );
		}
		$content = "template_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['template_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureNamespacePath( $namespace, $wikiName );
		file_put_contents( $logDir . "/invalid_page_templates.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $wikiName
	 *
	 * @return void
	 */
	private function writeInvalidAttachmentsLog(
		array $spaceIds, string $namespace = '', string $wikiName = ''
	): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->reader->getInvalidAttachments( (int)$spaceId ) );
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
		$logDir = $this->ensureNamespacePath( $namespace, $wikiName );
		file_put_contents( $logDir . "/invalid_attachments.log", $content );
	}

	/**
	 * @param string $namespace
	 * @param string $wikiName
	 * @return string
	 */
	private function ensureNamespacePath( string $namespace, string $wikiName = '' ): string {
		$path = $this->dest . "/result";
		if ( $wikiName !== '' ) {
			$path .= "/$wikiName";
		}
		$path .= "/$namespace/log";
		if ( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * Add version information of the migrate confluence tool to the database
	 *
	 * @return void
	 */
	private function logMigrateConfluenceToolVersion(): void {
		$this->writer->addLogEntry(
			'info',
			'compose',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @param string $namespace
	 * @param string $wikiName
	 * @return void
	 */
	private function addImportHelper( string $namespace, string $wikiName = '' ): void {
		$sourcePaths = glob( __DIR__ . '/_shell/*' );
		if ( $sourcePaths === false || $sourcePaths === [] ) {
			return;
		}

		$targetDir = $this->dest . "/result";
		if ( $wikiName !== '' ) {
			$targetDir .= "/$wikiName";
			$sourcePath = __DIR__ . '/_shell/wikiimport.sh';
			$this->copyShellScript( $sourcePath, $targetDir . '/wikiimport.sh' );

			$sourcePath = __DIR__ . '/_shell/spaceimport.sh';
			$this->copyShellScript( $sourcePath, $targetDir . '/spaceimport.sh' );
		} else {
			$targetDir .= "/$namespace";
			$sourcePath = __DIR__ . '/_shell/spaceimport.sh';
			$this->copyShellScript( $sourcePath, $targetDir . '/spaceimport.sh' );
		}
	}

	/**
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @return void
	 */
	private function copyShellScript( string $sourcePath, string $targetPath ): void {
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
