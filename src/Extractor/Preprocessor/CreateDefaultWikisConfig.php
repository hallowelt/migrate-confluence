<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

class CreateDefaultWikisConfig extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		if ( !$this->workspaceDB->isWikisConfigEmpty() ) {
			return;
		}

		foreach ( $this->workspaceDB->getSpaces() as $space ) {
			if ( empty( $space['space_key'] ) ) {
				continue;
			}

			$spaceKey = (string)$space['space_key'];
			$this->workspaceDB->addWikisConfig(
				$spaceKey,
				'wiki',
				(string)( $space['namespace_prefix'] ?? $spaceKey ),
				(string)( $space['root_page'] ?? '' )
			);
		}
	}
}
