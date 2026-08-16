<?php

namespace HalloWelt\MigrateConfluence\Analyzer\DataWriter;

interface IAnalyzeDataWriterAware {

	/**
	 * @param IAnalyzeDataWriter $dataWriter
	 * @return void
	 */
	public function setDataWriter( IAnalyzeDataWriter $dataWriter ): void;
}
