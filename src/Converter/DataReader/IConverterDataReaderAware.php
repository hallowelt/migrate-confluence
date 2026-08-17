<?php

namespace HalloWelt\MigrateConfluence\Converter\DataReader;

/**
 * Provides the converter read-side dependency.
 */
interface IConverterDataReaderAware {

	/**
	 * @param IConverterDataReader $reader
	 *
	 * @return void
	 */
	public function setDataReader( IConverterDataReader $reader ): void;
}
