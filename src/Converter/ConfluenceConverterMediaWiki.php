<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;

class ConfluenceConverterMediaWiki extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'mediawiki';

	/**
	 * @inheritDoc
	 */
	protected function getProcessors(): array {
		$processors = $this->getDefaultProcessors();
		$processors[] = new StatusMacro( $this->writer, $this->currentSpace );
		return $processors;
	}
}
