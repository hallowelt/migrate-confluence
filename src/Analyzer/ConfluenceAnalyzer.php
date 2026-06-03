<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Label;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Labelling;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Page;
use HalloWelt\MigrateConfluence\Analyzer\Processor\PageTemplates;
use HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase
	implements LoggerAwareInterface, IOutputAwareInterface, IDestinationPathAware
{
	/** @var string */
	private string $dest = '';

	/** @var LoggerInterface|NullLogger */
	private LoggerInterface|NullLogger $logger;

	/** @var Output|null */
	private ?Output $output = null;

	/** @var SplFileInfo */
	private SplFileInfo $file;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var DBLog */
	private DBLog $dbLog;

	/**
	 *
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->logger = new NullLogger();
	}

	/**
	 * @param string $dest
	 * @return void
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @return void
	 */
	private function initWorkspaceDB(): void {
		$this->workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );
	}

	/**
	 * @return void
	 */
	private function initDBLog(): void {
		$this->dbLog = new DBLog( $this->workspaceDB );
		$this->dbLog->addLogEntry(
			'info',
			'analyze',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @return void
	 */
	private function initMigrationConfig(): void {
		$advancedConfig = [];
		if ( isset( $this->config['config'] ) ) {
			$advancedConfig = $this->config['config'];
		}
		$this->migrationConfig = new MigrationConfig( $advancedConfig );
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		$this->file = $file;
		if ( $this->file->getFilename() !== 'entities.xml' ) {
			return true;
		}

		$this->initMigrationConfig();
		$this->initWorkspaceDB();
		$this->initDBLog();

		$result = parent::analyze( $file );

		return $result;
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
		return [
			'BodyContent' => new BodyContents( $this->workspaceDB ),
			'Space' => new Spaces( $this->workspaceDB, $this->migrationConfig ),
			'SpaceDescription' => new SpaceDescription( $this->workspaceDB, $this->migrationConfig ),
			'Page' => new Page( $this->workspaceDB, $this->migrationConfig ),
			'BlogPost' => new BlogPost( $this->workspaceDB, $this->migrationConfig ),
			'Attachment' => new Attachments( $this->workspaceDB, $this->migrationConfig, $this->file->getPath() ),
			'Comment' => new Comments( $this->workspaceDB ),
			'Label' => new Label( $this->workspaceDB ),
			'Labelling' => new Labelling( $this->workspaceDB ),
			'ContentProperty' => new ContentProperty( $this->workspaceDB ),
			'ConfluenceUserImpl' => new Users( $this->workspaceDB ),
			'PageTemplate' => new PageTemplates( $this->workspaceDB ),
		];
	}

	/**
	 * @param array $processors
	 * @return void
	 */
	private function initProcessors( array $processors ): void {
		foreach ( $processors as $processor ) {
			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->setOutput( $this->output );
				$processor->setLogger( $this->logger );
			}
		}
	}

	/**
	 * @param array $processors
	 * @return void
	 */
	private function processFile( array $processors ): void {
		$this->initProcessors( $processors );

		$xmlReader = new XMLReader();
		$xmlReader->open( $this->file->getPathname() );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( isset( $processors[$class] ) ) {
				$processor = $processors[$class];
			}

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doAnalyze( SplFileInfo $file ): bool {
		$this->output->writeln( "\nAnalyze data:" );
		$processors = $this->getProcessors();

		$this->workspaceDB->beginTransaction();
		$this->processFile( $processors );

		$this->workspaceDB->commitTransaction();
		return true;
	}
}
