<?php

namespace HalloWelt\MigrateConfluence\Utility;

class TocMacroUsage {

	/** @var bool */
	private bool $usage = false;

	/**
	 * @return void
	 */
	public function tocIsUsed(): void {
		$this->usage = true;
	}

	/**
	 * @return bool
	 */
	public function getStatus(): bool {
		return $this->usage;
	}
}
