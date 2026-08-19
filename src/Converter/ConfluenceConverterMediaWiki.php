<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\ConfluenceConverterBase;
use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;

class ConfluenceConverterMediaWiki extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'mediawiki';

	protected function getProcessors() {
		$processors = $this->getDefaultProcessors();
		$processors[] = new StatusMacro();
		return $processors;
	}
}
