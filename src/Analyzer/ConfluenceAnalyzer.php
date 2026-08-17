<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriterAware;
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
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\IWikisConfigAware;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase implements
	IOutputAwareInterface,
	IAnalyzeDataWriterAware,
	IMigrationConfigAware,
	IWikisConfigAware
{

	/** @var OutputInterface|null */
	private ?OutputInterface $output = null;

	/** @var IAnalyzeDataWriter|null */
	private ?IAnalyzeDataWriter $dataWriter = null;

	/** @var MigrationConfig|null */
	private ?MigrationConfig $migrationConfig = null;

	/** @var WikisConfig|null */
	private ?WikisConfig $wikisConfig = null;

	/**
	 * Factory used by command-level analyzer callback configuration.
	 *
	 * @return IAnalyzer
	 */
	public static function factory(): IAnalyzer {
		return new static();
	}

	/**
	 * @param OutputInterface $output
	 * @return void
	 */
	public function setOutput( OutputInterface $output ): void {
		$this->output = $output;
	}

	/**
	 * @param IAnalyzeDataWriter $writer
	 * @return void
	 */
	public function setDataWriter( IAnalyzeDataWriter $writer ): void {
		$this->dataWriter = $writer;
	}

	/**
	 * @param MigrationConfig $migrationConfig
	 * @return void
	 */
	public function setMigrationConfig( MigrationConfig $migrationConfig ): void {
		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @param WikisConfig $wikisConfig
	 * @return void
	 */
	public function setWikisConfig( WikisConfig $wikisConfig ): void {
		$this->wikisConfig = $wikisConfig;
	}

	/**
	 * @param SplFileInfo $file
	 *
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		if ( $this->output === null ) {
			throw new RuntimeException( 'OutputInterface not set' );
		}
		if ( $this->dataWriter === null ) {
			throw new RuntimeException( 'DataWriter not set' );
		}
		if ( $this->migrationConfig === null ) {
			throw new RuntimeException( 'MigrationConfig not set' );
		}
		if ( $this->wikisConfig === null ) {
			throw new RuntimeException( 'WikisConfig not set' );
		}
		if ( !$file->isFile() ) {
			throw new RuntimeException( "File does not exist: {$file->getPathname()}" );
		}
		if ( !$file->isReadable() ) {
			throw new RuntimeException( "File is not readable: {$file->getPathname()}" );
		}

		if ( $file->getFilename() !== 'entities.xml' ) {
			return true;
		}

		$sourcePath = $file->getPathname();

		$this->output->writeln( "\nProcessing: $sourcePath" );
		$this->output->writeln( "\nAnalyze data:" );

		$processors = $this->getProcessors( $file->getPath() );

		$this->dataWriter->beginTransaction();
		try {
			$this->processExportDescriptor( $file );
			$this->processFile( $sourcePath, $processors );
			$this->dataWriter->commitTransaction();
		} catch ( \Throwable $e ) {
			$this->dataWriter->rollbackTransaction();
			throw $e;
		}

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

		$this->dataWriter->addExportProperties(
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
			'BodyContent' => new BodyContents( $this->dataWriter ),
			'Space' => new Spaces( $this->dataWriter, $this->wikisConfig ),
			'SpaceDescription' => new SpaceDescription( $this->dataWriter, $this->migrationConfig ),
			'Page' => new Page( $this->dataWriter, $this->migrationConfig ),
			'BlogPost' => new BlogPost( $this->dataWriter, $this->migrationConfig ),
			'Attachment' => new Attachments( $this->dataWriter, $this->migrationConfig, $sourceBasePath ),
			'Comment' => new Comments( $this->dataWriter ),
			'Label' => new Label( $this->dataWriter ),
			'Labelling' => new Labelling( $this->dataWriter ),
			'ContentProperty' => new ContentProperty( $this->dataWriter ),
			'ConfluenceUserImpl' => new Users( $this->dataWriter ),
			'PageTemplate' => new PageTemplates( $this->dataWriter ),
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
