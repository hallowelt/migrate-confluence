<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

class DefaultPages extends ProcessorBase {

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

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}

			$file = $fileObj->getPathname();
			$namespacePrefix = basename( dirname( $file ) );
			$pageName = basename( $file );
			$wikiPageName = "$namespacePrefix:$pageName";
			$wikiText = file_get_contents( $file );

			$this->addRevision( $wikiPageName, $wikiText );
		}

		$this->writeOutputFile();
	}
}
