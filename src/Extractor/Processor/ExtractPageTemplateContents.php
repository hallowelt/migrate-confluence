<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

/**
 * Extract page template content and save as raw content for conversion.
 */
class ExtractPageTemplateContents extends ExtractSpaceDescriptionBodyContents {

	/**
	 * @return void
	 */
	public function execute(): void {
		foreach ( $this->workspaceDB->getPageTemplateContents() as $templateContent ) {
			$templateId = (int)$templateContent['template_id'];
			$content = $templateContent['content'] ?? '';
			if ( $content === '' ) {
				continue;
			}

			$bodyContentHTML = $this->normalizeBodyContentHTML( $content );
			$rawName = 'pt_' . (string)$templateId;
			$this->workspace->saveRawContent( $rawName, $bodyContentHTML );
		}
	}

}