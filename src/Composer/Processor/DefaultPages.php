<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use SplFileInfo;
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
		$files = $this->getDefaultPageFiles();
		$directoriesWithWikitext = $this->getDirectoriesWithWikitext( array_keys( $files ) );

		$registeredDefaultPages = [];
		foreach ( $this->currentSpaceIds as $currentSpaceId ) {
			$registeredDefaultPagesForSpace = $this->dataLookup->getRegisteredDefaultPagesForSpaceId( $currentSpaceId );
			foreach ( $registeredDefaultPagesForSpace as $namespace => $pageNames ) {
				$registeredDefaultPages[$namespace] = array_values( array_unique( array_merge(
					$registeredDefaultPages[$namespace] ?? [],
					$pageNames
				) ) );
			}
		}

		foreach ( $files as $relativeFilePath => $basepath ) {
			$directoryKey = $basepath . dirname( $relativeFilePath );
			$defaultPage = $this->getDefaultPageForFile(
				new SplFileInfo( $basepath . $relativeFilePath ),
				$basepath,
				isset( $directoriesWithWikitext[dirname( $relativeFilePath )] ) ? [ $directoryKey => true ] : []
			);
			$defaultPageNamespace = $defaultPage['namespace'];
			$defaultPageName = $defaultPage['registered_name'];
			$wikiPageName = $defaultPage['wiki_title'];

			if ( !isset( $registeredDefaultPages[$defaultPageNamespace] )
				|| !in_array( $defaultPageName, $registeredDefaultPages[$defaultPageNamespace], true )
			) {
				// Add only default pages that are really used.
				continue;
			}

			$wikiText = file_get_contents( $basepath . $relativeFilePath );

			$this->addRevision( $wikiPageName, $wikiText );
		}

		$this->writeOutputFile();
	}

	/**
	 * Collects the default page files; files below `_defaultpages_profiles/<profile>` replace
	 * the files of the same relative path below `_defaultpages` for that output profile.
	 *
	 * @return array<string,string> Relative path => base directory the file was found in
	 */
	private function getDefaultPageFiles(): array {
		$files = $this->scanDirectory( dirname( __DIR__ ) . '/_defaultpages/' );

		$profilePath = dirname( __DIR__ ) . '/_defaultpages_profiles/' . $this->migrationConfig->getProfile() . '/';
		if ( is_dir( $profilePath ) ) {
			$files = array_merge( $files, $this->scanDirectory( $profilePath ) );
		}

		ksort( $files );
		return $files;
	}

	/**
	 * @param string $basepath
	 * @return array<string,string> Relative path => base directory
	 */
	private function scanDirectory( string $basepath ): array {
		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $basepath, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$files[substr( $fileObj->getPathname(), strlen( $basepath ) )] = $basepath;
		}

		return $files;
	}

	/**
	 * Find directories where the default page body is stored in a dedicated `wikitext` file.
	 * Sibling files in these directories are emitted as subpages of the same registered page.
	 *
	 * @param string[] $relativePaths
	 * @return array<string,bool>
	 */
	private function getDirectoriesWithWikitext( array $relativePaths ): array {
		$directories = [];
		foreach ( $relativePaths as $relativePath ) {
			if ( basename( $relativePath ) === 'wikitext' ) {
				$directories[dirname( $relativePath )] = true;
			}
		}

		return $directories;
	}

	/**
	 * Translate one file below `_defaultpages` into the DB registry key and output wiki title.
	 *
	 * Directory convention:
	 * - The first directory is the namespace, e.g. `Template`.
	 * - A file directly below the namespace is a default page, e.g. `Template/TM` => `Template:TM`.
	 * - A directory with a `wikitext` file is a default page, e.g. `Template/Folder/wikitext` => `Template:Folder`.
	 * - Sibling files in that directory are subpages of the registered default page,
	 *   e.g. `Template/Folder/style.css` => `Template:Folder/Style.css`.
	 *
	 * @param SplFileInfo $fileObj
	 * @param string $basepath
	 * @param array<string,bool> $directoriesWithWikitext
	 * @return array{namespace:string,registered_name:string,wiki_title:string}
	 */
	private function getDefaultPageForFile(
		SplFileInfo $fileObj, string $basepath, array $directoriesWithWikitext
	): array {
		$relativeFilePath = str_replace( $basepath, '', $fileObj->getPathname() );
		$pathParts = explode( '/', $relativeFilePath );
		$namespace = array_shift( $pathParts ) ?? '';
		$basename = $fileObj->getBasename();

		if ( $basename === 'wikitext' ) {
			// Case 1: Template/Folder/wikitext defines the page Template:Folder.
			array_pop( $pathParts );
			$registeredName = implode( '/', $pathParts );
			$wikiPageName = ucfirst( $registeredName );
		} elseif ( isset( $directoriesWithWikitext[$fileObj->getPath()] ) && count( $pathParts ) > 1 ) {
			// Case 2: Template/Folder/style.css defines the subpage Template:Folder/Style.css.
			$registeredName = $pathParts[0];
			$subpageTitle = implode( '/', array_slice( $pathParts, 1 ) );
			$wikiPageName = ucfirst( $registeredName ) . '/' . ucfirst( $subpageTitle );
		} else {
			// Case 3: Template/TM defines the page Template:TM.
			$registeredName = implode( '/', $pathParts );
			$wikiPageName = ucfirst( $registeredName );
		}

		return [
			'namespace' => $namespace,
			'registered_name' => $registeredName,
			'wiki_title' => $namespace === '' ? $wikiPageName : "$namespace:$wikiPageName",
		];
	}
}
