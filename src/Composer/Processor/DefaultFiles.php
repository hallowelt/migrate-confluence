<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DefaultFiles extends FileProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addDefaultFiles();
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'default-files';
	}

	/**
	 * Store default file binaries next to default-files.xml in a dedicated directory.
	 *
	 * @return string
	 */
	protected function getUploadPath(): string {
		return 'result/' . $this->subDir . '/default-images';
	}

	/**
	 * @return void
	 */
	private function addDefaultFiles(): void {
		$basepath = dirname( __DIR__ ) . '/_defaultfiles/';
		if ( !is_dir( $basepath ) ) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		$uploadPath = $this->getUploadPath();

		$registeredDefaultFiles = [];
		foreach ( $this->currentSpaceIds as $currentSpaceId ) {
			$registeredDefaultFiles = array_merge(
				$registeredDefaultFiles,
				$this->dataLookup->getRegisteredDefaultFilesForSpaceId( $currentSpaceId )
			);
		}
		$registeredDefaultFiles = array_unique( $registeredDefaultFiles );
		if ( $registeredDefaultFiles === [] ) {
			return;
		}

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$file = $fileObj->getPathname();
			$filename = basename( $file );

			if ( !in_array( $filename, $registeredDefaultFiles, true ) ) {
				// Add only files that are really used.
				continue;
			}

			$attachmentPageTitle = $filename;
			$data = file_get_contents( $file );

			$uploadFilePath = $this->workspace->saveUploadFile(
				$filename, $data, $uploadPath
			);

			$this->addFileRevision(
				$attachmentPageTitle,
				$this->getRelativeFilePath( $uploadFilePath ),
				'',
				''
			);
		}
		if ( $this->numOfRevisions > 0 ) {
			$this->writeOutputFile();
		}
	}
}
