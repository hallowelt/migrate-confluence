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
	 * @return void
	 */
	private function addDefaultFiles(): void {
		$basepath = dirname( __DIR__ ) . '/_defaultfiles/';
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		$uploadPath = $this->getUploadPath();

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$file = $fileObj->getPathname();
			var_dump( $file );
			$filename = basename( $file );
			$attachmentPageTitle = $filename;
			$data = file_get_contents( $file );

			$uploadFilePath = $this->workspace->saveUploadFile(
				$filename, $data, $uploadPath
			);

			// XML containing files is supported by MediaWiki dumpBackup but can not be imported
			$this->builder->addFileRevision(
				$attachmentPageTitle,
				$this->getRelativeFilePath( $uploadFilePath ),
				'',
				''
			);
		}
		$this->writeOutputFile();
	}
}
