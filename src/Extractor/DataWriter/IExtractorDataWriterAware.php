<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataWriter;

interface IExtractorDataWriterAware {

	/**
	 * @param IExtractorDataWriter $dataWriter
	 * @return void
	 */
	public function setDataWriter( IExtractorDataWriter $dataWriter ): void;
}
