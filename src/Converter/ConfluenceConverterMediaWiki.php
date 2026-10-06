<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;

class ConfluenceConverterMediaWiki extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'mediawiki';

	/**
	 * @inheritDoc
	 */
	protected function getProcessors(): array {
		$processors = $this->getDefaultProcessors();

		array_splice( $processors, ConfluenceConverterBase::PROFILE_AWARE_PROCESSORS_POSITION, 0, [
			new StatusMacro( $this->writer, $this->currentSpace ),
			new ExcerptMacro( $this->writer, $this->currentSpace ),
			new ExcerptIncludeMacro( $this->writer, $this->dataLookup, $this->currentSpace ),
		] );

		return $processors;
	}
}
