<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase;
use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;

class ConfluenceConverterBlueSpiceClassic extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'bluespice-classic';

	protected function getProcessors() {
		$processors = $this->getDefaultProcessors();
		$processors[] = new StatusMacro();
		return $processors;
	}
}
