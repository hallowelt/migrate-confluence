<?php

namespace HalloWelt\MigrateConfluence\Converter\DataWriter;

interface IConverterDataWriterAware {
	public function setDataWriter( IConverterDataWriter $writer ): void;
}
