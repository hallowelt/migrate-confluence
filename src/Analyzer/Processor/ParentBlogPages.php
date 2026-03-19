<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

class ParentBlogPages extends ParentPages {

	/**
	 * @inheritDoc
	 */
	protected function getParentIdMapKey(): string {
		return 'analyze-blogpost-id-to-parent-page-id-map';
	}

	/**
	 * @inheritDoc
	 */
	protected function getConfluenceTitleMapKey(): string {
		return 'analyze-blogpost-id-to-confluence-title-map';
	}

}
