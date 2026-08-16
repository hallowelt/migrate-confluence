<?php

namespace HalloWelt\MigrateConfluence\Extractor\DataReader;

interface IExtractorDataReaderAware {

	/**
	 * @param IExtractorDataReader $dataReader
	 * @return void
	 */
	public function setDataReader( IExtractorDataReader $dataReader ): void;
}
