<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DateTimeZone;
use Exception;
use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermission;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermissionSet;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Label;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Labelling;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Page;
use HalloWelt\MigrateConfluence\Analyzer\Processor\PageTemplates;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ProcessorBase;
use HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use InvalidArgumentException;
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
	 * @param OutputInterface $output
	 * @param MigrationConfig $config
	 * @param WikisConfig $wikis
	 */
	public function __construct(
		private readonly IAnalyzeDataWriter $writer,
		private readonly OutputInterface $output,
		private readonly MigrationConfig $config,
		private readonly WikisConfig $wikis,
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

		$this->writer->beginTransaction();
		try {
			$descriptorInfo = $this->processExportDescriptor( $file );
			$this->applySourceTimezone( $processors, $descriptorInfo['timezoneId'] );
			$this->processFile( $sourcePath, $processors );
			$this->writer->commitTransaction();
		} catch ( \Throwable $e ) {
			$this->writer->rollbackTransaction();
			throw $e;
		}

		return true;
	}

	/**
	 * Applies the zone of the export dates: config option, then descriptor, then UTC.
	 *
	 * @param array $processors
	 * @param string $descriptorTimezone
	 * @return void
	 */
	private function applySourceTimezone( array $processors, string $descriptorTimezone ): void {
		$configured = $this->config->getSourceTimezone();
		try {
			$timezone = new DateTimeZone( $configured !== '' ? $configured : ( $descriptorTimezone ?: 'UTC' ) );
		} catch ( Exception $e ) {
			if ( $configured !== '' ) {
				throw new InvalidArgumentException( "Invalid 'source-timezone' in config: '$configured'" );
			}
			$this->output->writeln( "Unknown timezoneId '$descriptorTimezone' in export, assuming UTC" );
			$timezone = new DateTimeZone( 'UTC' );
		}
		$this->output->writeln( "Interpreting export dates as {$timezone->getName()}" );

		foreach ( $processors as $processor ) {
			if ( $processor instanceof ProcessorBase ) {
				$processor->setSourceTimezone( $timezone );
			}
		}
	}

	/**
	 * @param SplFileInfo $entitiesFile
	 * @return array{timezoneId: string} Info from the descriptor; values are empty if unknown
	 */
	private function processExportDescriptor( SplFileInfo $entitiesFile ): array {
		$descriptorPath = $entitiesFile->getPath() . '/exportDescriptor.properties';
		if ( !file_exists( $descriptorPath ) ) {
			return [ 'timezoneId' => '' ];
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

		$this->writer->addExportProperties(
			$props['spaceKey'] ?? '',
			$props['source'] ?? '',
			$props['createdByVersionNumber'] ?? '',
			trim( $props['_date'] ?? '' ),
			$props['timezoneId'] ?? '',
			basename( $entitiesFile->getPath() ) . '/' . $entitiesFile->getFilename()
		);

		return [ 'timezoneId' => $props['timezoneId'] ?? '' ];
	}

	/**
	 * @param string $sourceBasePath
	 *
	 * @return array
	 */
	private function getProcessors( string $sourceBasePath ): array {
		return [
			'BodyContent' => new BodyContents( $this->writer ),
			'Space' => new Spaces( $this->writer, $this->wikis ),
			'SpaceDescription' => new SpaceDescription( $this->writer, $this->config ),
			'Page' => new Page( $this->writer, $this->config ),
			'BlogPost' => new BlogPost( $this->writer, $this->config ),
			'Attachment' => new Attachments( $this->writer, $this->config, $sourceBasePath ),
			'Comment' => new Comments( $this->writer ),
			'Label' => new Label( $this->writer ),
			'Labelling' => new Labelling( $this->writer ),
			'ContentProperty' => new ContentProperty( $this->writer ),
			'ContentPermissionSet' => new ContentPermissionSet( $this->writer ),
			'ContentPermission' => new ContentPermission( $this->writer ),
			'ConfluenceUserImpl' => new Users( $this->writer ),
			'PageTemplate' => new PageTemplates( $this->writer ),
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
