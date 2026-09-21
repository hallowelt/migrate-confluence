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
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
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

		$this->writer->beginTransaction();
		try {
			$expectedSpaceKey = $this->processExportDescriptor( $file );

			$spaceFilter = null;
			if ( $this->config->getFilterForeignSpaceData() ) {
				$spaceFilter = $this->buildSpaceFilter( $file, $expectedSpaceKey );
			}

			$processors = $this->getProcessors( $file->getPath(), $spaceFilter );
			$this->processFile( $sourcePath, $processors );
			$spaceFilter?->writeSummary();
			$this->writer->commitTransaction();
		} catch ( \Throwable $e ) {
			$this->writer->rollbackTransaction();
			throw $e;
		}

		return true;
	}

	/**
	 * @param SplFileInfo $entitiesFile
	 * @return string The expected spaceKey from exportDescriptor.properties, or '' if absent
	 */
	private function processExportDescriptor( SplFileInfo $entitiesFile ): string {
		$descriptorPath = $entitiesFile->getPath() . '/exportDescriptor.properties';
		if ( !file_exists( $descriptorPath ) ) {
			return '';
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

		return $props['spaceKey'] ?? '';
	}

	/**
	 * Runs SpaceFilterPrescanProcessor over entities.xml and turns its result
	 * into a configured SpaceFilter. Only called when `filter-foreign-space-data`
	 * is enabled. See doc/configuration.md.
	 *
	 * Returns null (no filtering, matching the disabled default) if the expected
	 * space cannot be determined - never a SpaceFilter with an empty allow-list,
	 * which would deny every space instead of passing everything through.
	 *
	 * @param SplFileInfo $entitiesFile
	 * @param string $expectedSpaceKey
	 * @return SpaceFilter|null
	 */
	private function buildSpaceFilter( SplFileInfo $entitiesFile, string $expectedSpaceKey ): ?SpaceFilter {
		if ( trim( $expectedSpaceKey ) === '' ) {
			$this->writer->addLogEntry(
				'serious-error',
				'analyze',
				__CLASS__,
				'filter-foreign-space-data is enabled, but exportDescriptor.properties has no spaceKey.'
					. ' Foreign-space filtering is skipped for this file.'
			);
			return null;
		}

		$scanner = new SpaceFilterPrescanProcessor();
		$scanner->setOutput( $this->output );
		$scanner->setLogger( $this->logger );

		$xmlReader = new XMLReader();
		$xmlReader->open( $entitiesFile->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}
			$scanner->execute( $xmlReader );
			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$allowedSpaceIds = [];
		foreach ( $scanner->getSpaceKeys() as $spaceId => $spaceKey ) {
			if ( $spaceKey === $expectedSpaceKey ) {
				$allowedSpaceIds[$spaceId] = true;
			}
		}

		if ( $allowedSpaceIds === [] ) {
			$this->writer->addLogEntry(
				'serious-error',
				'analyze',
				__CLASS__,
				"filter-foreign-space-data is enabled, but no Space object in entities.xml matches the expected"
					. " spaceKey '$expectedSpaceKey' from exportDescriptor.properties."
					. ' Foreign-space filtering is skipped for this file to avoid discarding everything.'
			);
			return null;
		}

		$contentSpaceMap = $scanner->getDirectSpaceOwners();

		// Resolve (possibly nested) Comment -> containerContent chains against the
		// already-known content owners. Bounded: comment reply nesting is never deep.
		$commentParents = $scanner->getCommentParents();
		for ( $i = 0; $i < 10; $i++ ) {
			$changed = false;
			foreach ( $commentParents as $commentId => $parentId ) {
				if ( isset( $contentSpaceMap[$commentId] ) ) {
					continue;
				}
				if ( isset( $contentSpaceMap[$parentId] ) ) {
					$contentSpaceMap[$commentId] = $contentSpaceMap[$parentId];
					$changed = true;
				}
			}
			if ( !$changed ) {
				break;
			}
		}

		$labellingSpaceMap = [];
		foreach ( $scanner->getLabellingsOf() as $contentId => $labellingIds ) {
			if ( !isset( $contentSpaceMap[$contentId] ) ) {
				continue;
			}
			foreach ( $labellingIds as $labellingId ) {
				$labellingSpaceMap[$labellingId] = $contentSpaceMap[$contentId];
			}
		}

		$spaceFilter = new SpaceFilter( $this->writer, $this->output );
		$spaceFilter->configure( $allowedSpaceIds, $contentSpaceMap, $labellingSpaceMap );

		return $spaceFilter;
	}

	/**
	 * @param string $sourceBasePath
	 * @param SpaceFilter|null $spaceFilter
	 *
	 * @return array
	 */
	private function getProcessors( string $sourceBasePath, ?SpaceFilter $spaceFilter = null ): array {
		return [
			'BodyContent' => new BodyContents( $this->writer, $spaceFilter ),
			'Space' => new Spaces( $this->writer, $this->wikis, $spaceFilter ),
			'SpaceDescription' => new SpaceDescription( $this->writer, $this->config, $spaceFilter ),
			'Page' => new Page( $this->writer, $this->config, $spaceFilter ),
			'BlogPost' => new BlogPost( $this->writer, $this->config, $spaceFilter ),
			'Attachment' => new Attachments( $this->writer, $this->config, $sourceBasePath, $spaceFilter ),
			'Comment' => new Comments( $this->writer, $spaceFilter ),
			'Label' => new Label( $this->writer ),
			'Labelling' => new Labelling( $this->writer, $spaceFilter ),
			'ContentProperty' => new ContentProperty( $this->writer, $spaceFilter ),
			'ConfluenceUserImpl' => new Users( $this->writer ),
			'PageTemplate' => new PageTemplates( $this->writer, $spaceFilter ),
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
