<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class UserLinkProcessor extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'user';
	}

}
