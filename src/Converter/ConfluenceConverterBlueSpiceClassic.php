<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;

class ConfluenceConverterBlueSpiceClassic extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'bluespice-classic';

	/**
	 * @inheritDoc
	 */
	protected function getProcessors(): array {
		$processors = $this->getDefaultProcessors();
		$processors[] = new StatusMacro();
		return $processors;
	}
}
