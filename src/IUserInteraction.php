<?php

namespace HalloWelt\MigrateConfluence;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

interface IUserInteraction {

	/**
	 * @param QuestionHelper $questionHelper
	 * @return void
	 */
	public function setQuestionHelper( QuestionHelper $questionHelper ): void;

	/**
	 * @param Input $input
	 * @return void
	 */
	public function setInput( Input $input ): void;

	/**
	 * @param Output $output
	 * @return void
	 */
	public function setOutput( Output $output ): void;
}
