<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
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
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use XMLReader;

class ConfluenceAnalyzer implements LoggerAwareInterface, IAnalyzer {

	/** @var LoggerInterface|NullLogger */
	private LoggerInterface|NullLogger $logger;

	/**
	 * @param IAnalyzeDataWriter $writer
	 * @param WorkspaceDB $workspaceDB
	 * @param OutputInterface $output
	 * @param MigrationConfig $config
	 */
	public function __construct(
		private readonly IAnalyzeDataWriter $writer,
		private readonly WorkspaceDB $workspaceDB,
		private readonly OutputInterface $output,
		private readonly MigrationConfig $config,
	) {
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 *
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param SplFileInfo $file
	 *
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		if ( $file->getFilename() !== 'entities.xml' ) {
			return true;
		}

		$sourcePath = $file->getPathname();

		$this->output->writeln( "\nProcessing: $sourcePath" );
		$this->output->writeln( "\nAnalyze data:" );

		$processors = $this->getProcessors( $file->getPath() );

		$this->workspaceDB->beginTransaction();
		$this->processExportDescriptor( $file );
		$this->processFile( $sourcePath, $processors );
		$this->workspaceDB->commitTransaction();

		return true;
	}

	/**
	 * @param SplFileInfo $entitiesFile
	 * @return void
	 */
	private function processExportDescriptor( SplFileInfo $entitiesFile ): void {
		$descriptorPath = $entitiesFile->getPath() . '/exportDescriptor.properties';
		if ( !file_exists( $descriptorPath ) ) {
			return;
		}

		$props = [];
		foreach ( file( $descriptorPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			if ( str_starts_with( $line, '#' ) ) {
				if ( empty( $props['_date'] ) ) {
					$props['_date'] = ltrim( $line, '#' );
				}
				continue;
			}
			[ $key, $value ] = explode( '=', $line, 2 ) + [ 1 => '' ];
			$props[trim( $key )] = trim( $value );
		}

		$this->workspaceDB->addExportProperties(
			$props['spaceKey'] ?? '',
			$props['source'] ?? '',
			$props['createdByVersionNumber'] ?? '',
			trim( $props['_date'] ?? '' ),
			$props['timezoneId'] ?? '',
			basename( $entitiesFile->getPath() ) . '/' . $entitiesFile->getFilename()
		);
	}

	/**
	 * @param string $sourceBasePath
	 *
	 * @return array
	 */
	private function getProcessors( string $sourceBasePath ): array {
		return [
			'BodyContent' => new BodyContents( $this->writer ),
			'Space' => new Spaces( $this->writer, $this->config ),
			'SpaceDescription' => new SpaceDescription( $this->writer, $this->config ),
			'Page' => new Page( $this->writer, $this->config ),
			'BlogPost' => new BlogPost( $this->writer, $this->config ),
			'Attachment' => new Attachments( $this->writer, $this->config, $sourceBasePath ),
			'Comment' => new Comments( $this->writer ),
			'Label' => new Label( $this->writer ),
			'Labelling' => new Labelling( $this->writer ),
			'ContentProperty' => new ContentProperty( $this->writer ),
			'ConfluenceUserImpl' => new Users( $this->writer ),
			'PageTemplate' => new PageTemplates( $this->writer, $this->workspaceDB ),
		];
	}

	/**
	 * @param array $processors
	 *
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
	 * @param string $filepath
	 * @param array $processors
	 *
	 * @return void
	 */
	private function processFile( string $filepath, array $processors ): void {
		$this->initProcessors( $processors );

		$xmlReader = new XMLReader();
		$xmlReader->open( $filepath );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
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
}
