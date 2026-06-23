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
		$wikiTitles = $this->dataLookup->getPageTemplateIdWikiTitleMap( $this->currentSpaceId );

		foreach ( $wikiTitles as $templateId => $pageTitle ) {
			if ( $this->skipHelper->skipWikiTitle( $pageTitle ) ) {
				$this->output->writeln( "Skip template '$pageTitle'" );
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}
			$this->output->writeln( "Processing template '$pageTitle' ..." );

			$namespace = $this->getNamespace( $pageTitle );

			$revisions = $this->dataLookup->getPageTemplateRevisionsForTemplateId( $templateId );

			foreach ( $revisions as $revision ) {
				$timestamp = $revision['revision_timestamp'];
				$templateContentIds = json_decode( $revision['template_content_ids'], true );

				$pageContent = '';
				foreach ( $templateContentIds as $templateContentId ) {
					if ( $templateContentId === '' ) {
						continue;
					}

					$this->output->writeln( "Getting '$templateContentId' template content..." );
					$pageContent .= $this->workspace->getConvertedContent( 'pt_' . $templateContentId ) . "\n";
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
}
