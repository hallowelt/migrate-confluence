<?php

namespace HalloWelt\MigrateConfluence\Converter;

interface IStructuredMacroProcessorTest {

	/**
	 *
	 * @return string
	 */
	public function getInput(): string;

	/**
	 *
	 * @return string
	 */
	public function getExpectedOutput(): string;
}
