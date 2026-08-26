<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\BluespiceGalaxy\RestoreStatusMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BluespiceGalaxy\StatusMacro;

class ConfluenceConverterBlueSpiceGalaxy extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'bluespice-galaxy';

	/**
	 * @inheritDoc
	 */
	protected function getProcessors(): array {
		$processors = $this->getDefaultProcessors();
		$processors[] = new StatusMacro();
		return $processors;
	}

	/**
	 * @inheritDoc
	 */
	protected function getPostProcessors(): array {
		$processors = $this->getDefaultPostProcessors();
		$processors[] = new RestoreStatusMacro();
		return $processors;
	}
}
