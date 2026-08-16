<?php

namespace HalloWelt\MigrateConfluence\Composer\DataReader;

interface IComposerDataReaderAware {

	/**
	 * @param IComposerDataReader $reader
	 * @return void
	 */
	public function setDataReader( IComposerDataReader $reader ): void;
}
