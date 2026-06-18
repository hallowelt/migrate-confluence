<?php

namespace HalloWelt\MigrateConfluence\Utility;

class ComposerSkipHelper {

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private DBComposerDataLookup $dataLookup,
		private MigrationConfig $migrationConfig
	) {
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function skipPage( string $wikiTitle ): bool {
		if ( $this->dataLookup->isPageInvalid( $wikiTitle ) ) {
			return true;
		}

		return $this->skipWikiTitleByConfiguration( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function skipBlogPost( string $wikiTitle ): bool {
		if ( $this->dataLookup->isBlogPostInvalid( $wikiTitle ) ) {
			return true;
		}

		return $this->skipWikiTitleByConfiguration( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function skipTemplate( string $wikiTitle ): bool {
		if ( $this->dataLookup->isPageTemplateInvalid( $wikiTitle ) ) {
			return true;
		}

		return $this->skipWikiTitleByConfiguration( $wikiTitle );
	}

	/**
	 * @param string $wikiTitle
	 * @return bool
	 */
	public function skipWikiTitle( string $wikiTitle ): bool {
		// Blog page title
		if ( str_starts_with( $wikiTitle, 'Blog:' ) ) {
			return $this->skipBlogPost( $wikiTitle );
		}

		// Template page title
		if ( str_starts_with( $wikiTitle, 'Template:' ) ) {
			return $this->skipTemplate( $wikiTitle );
		}

		// Content page title
		return $this->skipPage( $wikiTitle );
	}

	/**
	 * Skip wiki title by configuration
	 *
	 * @param string $wikiTitle
	 * @return bool
	 */
	private function skipWikiTitleByConfiguration( string $wikiTitle ): bool {
		$namespace = 'NS_MAIN';
		if ( str_contains( $wikiTitle, ':' ) ) {
			$namespace = substr( $wikiTitle, 0, strpos( $wikiTitle, ':' ) );
		}

		if ( $this->skipNamespaceByConfiguration( $namespace ) ) {
			return true;
		}

		if ( in_array( $wikiTitle, $this->migrationConfig->getComposerSkipTitles() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Skip wiki namespace by configuration
	 * Main namespace can be skipped with NS_MAIN
	 *
	 * @param string $namespace
	 * @return bool
	 */
	private function skipNamespaceByConfiguration( string $namespace ): bool {
		if ( in_array( $namespace, $this->migrationConfig->getComposerSkipNamespaces() ) ) {
			return true;
		}
		return false;
	}
}
