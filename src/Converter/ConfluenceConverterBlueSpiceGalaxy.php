<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\StatusMacro;

class ConfluenceConverterBlueSpiceGalaxy extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'bluespice-galaxy';

	/**
	 * @inheritDoc
	 */
	protected function getProcessors(): array {
		return array_merge( $this->getDefaultProcessors(), [
			new StatusMacro( $this->placeholderManager ),
			new ExcerptMacro( $this->placeholderManager ),
			new ExcerptIncludeMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->placeholderManager
			),
		] );
	}
}
