<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

class Templates extends ProcessorBase {

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'templates';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addDefaultPages();
		$wikiTitles = $this->dataLookup->getPageTemplateIdTargetTitleMap();

		foreach ( $wikiTitles as $templateId => $pageTitle ) {
			$this->output->writeln( "Processing template '$pageTitle'..." );

			if ( $this->skipTitle( $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}

			$namespace = $this->getNamespace( $pageTitle );

			$revisions = $this->dataLookup->getPageTemplateRevisionsForTemplateId( $templateId );

			foreach ( $revisions as $revision ) {
				$timestamp = $revision['revision_timestamp'];
				$bodyContentIds = json_decode( $revision['body_content_ids'], true );

				$pageContent = '';
				foreach ( $bodyContentIds as $bodyContentId ) {
					if ( $bodyContentId === '' ) {
						continue;
					}

					$this->output->writeln( "Getting '$bodyContentId' body content..." );
					$pageContent .= $this->workspace->getConvertedContent( $bodyContentId ) . "\n";
				}

				$this->addRevision(
					$pageTitle,
					$pageContent,
					$timestamp,
					''
				);
			}

			$this->deploymentInfo->addNamespace( $namespace );
		}

		$this->writeOutputFile();
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
	}
}
