<?php

namespace HalloWelt\MigrateConfluence\Utility;

class ComposerDeploymentInfo {

	/** @var array */
	private array $fileExtensions = [];

	/** @var array */
	private array $namespaces = [];

	/** @var array */
	private array $skippedPages = [];

	/**
	 * @param string $extension
	 * @return void
	 */
	public function addFileExtension( string $extension ): void {
		if ( !in_array( $extension, $this->fileExtensions ) ) {
			$this->fileExtensions[] = $extension;
		}
	}

	/**
	 * @return array
	 */
	public function getFileExtensions(): array {
		return $this->fileExtensions;
	}

	/**
	 * @param string $namespace
	 * @return void
	 */
	public function addNamespace( string $namespace ): void {
		if ( !in_array( $namespace, $this->namespaces ) ) {
			$this->namespaces[] = $namespace;
		}
	}

	/**
	 * @return array
	 */
	public function getNamespaces(): array {
		return $this->namespaces;
	}

	/**
	 * @param string $title
	 * @return void
	 */
	public function addSkippedPage( string $title ): void {
		if ( !in_array( $title, $this->skippedPages ) ) {
			$this->skippedPages[] = $title;
		}
	}

	/**
	 * @return array
	 */
	public function getSkippedPages(): array {
		return $this->skippedPages;
	}
}