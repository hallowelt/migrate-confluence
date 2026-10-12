<?php

namespace HalloWelt\MigrateConfluence\Converter;

use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ExcerptMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\StatusMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptMacro;

class ConfluenceConverterBlueSpiceGalaxy extends ConfluenceConverterBase {
	protected const PROFILE_NAME = 'bluespice-galaxy';

	/**
	 * @inheritDoc
	 */
	protected function getProcessors(): array {
		$processors = $this->getDefaultProcessors();

		array_splice( $processors, ConfluenceConverterBase::PROFILE_AWARE_PROCESSORS_POSITION, 0, [
			new StatusMacro( $this->placeholderManager ),
			new ExcerptMacro( $this->placeholderManager ),
			new ExcerptIncludeMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->placeholderManager
			),
			new TableExcerptMacro( $this->placeholderManager ),
			new TableExcerptIncludeMacro(
				$this->dataLookup,
				$this->currentSpace,
				$this->placeholderManager,
				$this->confluencePageTitle
			),
		] );

		return $processors;
	}
}
