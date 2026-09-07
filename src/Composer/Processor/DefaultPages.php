<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class DefaultPages extends ProcessorBase {

	/**
	 * @param Builder $builder
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 * @param DBComposerDataLookup $dataLookup
	 */
	public function __construct(
		protected Builder $builder,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig,
		protected DBComposerDataLookup $dataLookup,
	) {
		parent::__construct( $builder, $output, $dest, $migrationConfig );
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'default-pages';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addDefaultPages();
	}

	private function addDefaultPages(): void {
		$basepath = dirname( __DIR__ ) . '/_defaultpages/';

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $basepath ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		$registeredDefaultPages = [];
		foreach ( $this->currentSpaceIds as $currentSpaceId ) {
			$registeredDefaultPages = array_merge(
				$registeredDefaultPages,
				$this->dataLookup->getRegisteredDefaultPagesForSpaceId( $currentSpaceId )
			);
		}

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}

			/* support folder structures like Template/SomeName/style.css to be
			 * converted to Template:SomeName/style.css */
			$namespacePrefix = str_replace( $basepath, '', $fileObj->getPath() );
			$firstSlash = strpos( $namespacePrefix, '/' );
			if ( $firstSlash !== false ) {
				$namespacePrefix = substr_replace( $namespacePrefix, ':', $firstSlash, 1 ) . '/';
			} elseif ( $namespacePrefix !== '' ) {
				$namespacePrefix .= ':';
			}
			$pageName = $fileObj->getBasename();
			if (
					$namespacePrefix && str_ends_with( $namespacePrefix, '/' ) &&
					$fileObj->getBasename() === 'wikitext' ) {
				/* Template:SomeName/wikitext => Template:SomeName to allow better structuring of source files */
				$namespacePrefix = rtrim( $namespacePrefix, '/' );
				$pageName = '';
			}

			if ( !isset( $registeredDefaultPages[$namespacePrefix] )
				|| !in_array( $pageName, $registeredDefaultPages[$namespacePrefix], true )
			) {
				// Add only default pages that are really used.
				continue;
			}

			$wikiPageName = "$namespacePrefix$pageName";
			$wikiText = file_get_contents( $fileObj->getPathname() );

			$this->addRevision( $wikiPageName, $wikiText );
		}

		$this->writeOutputFile();
	}
}
