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

			/* support folder structures like Template/SomeName/style.css to be
			 * converted to Template:SomeName/style.css */
			$namespacePrefix = str_replace( $basepath, '', $fileObj->getPath() );
			$firstSlash = strpos( $namespacePrefix, '/' );
			if ( $firstSlash !== false ) {
				$namespacePrefix = substr_replace( $namespacePrefix, ':', $firstSlash, 1 ) . '/';
			} else {
				$namespacePrefix .= ':';
			}
			/* strip off an optional .wikitext extension */
			$pageName = $fileObj->getBasename( '.wikitext' );
			$wikiPageName = "$namespacePrefix$pageName";
			$wikiText = file_get_contents( $fileObj->getPathname() );

			$this->addRevision( $wikiPageName, $wikiText );
		}

		$this->writeOutputFile();
	}
}
