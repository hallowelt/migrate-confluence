<?php

namespace HalloWelt\MigrateConfluence\Composer\DataWriter;

interface IComposerDataWriterAware {

	/**
	 * @param IComposerDataWriter $writer
	 * @return void
	 */
	public function setDataWriter( IComposerDataWriter $writer ): void;
}
