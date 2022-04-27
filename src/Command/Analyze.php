<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Analyze as CommandAnalyze;
use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\IUserInteraction;

class Analyze extends CommandAnalyze {

	/**
	 * @param array $config
	 * @return Analyze
	 */
	public static function factory( $config ): Analyze {
		return new static( $config );
	}

	/**
	 * @return bool
	 */
	protected function doProcessFile(): bool {
		$analyzerFactoryCallbacks = $this->config['analyzers'];
		foreach ( $analyzerFactoryCallbacks as $key => $callback ) {
			$analyzer = call_user_func_array(
				$callback,
				[ $this->config, $this->workspace, $this->buckets ]
			);
			if ( $analyzer instanceof IAnalyzer === false ) {
				throw new Exception(
					"Factory callback for analyzer '$key' did not return an "
					. "IAnalyzer object"
				);
			}
			if ( $analyzer instanceof IOutputAwareInterface ) {
				$analyzer->setOutput( $this->output );
			}
			if ( $analyzer instanceof IUserInteraction ) {
				$analyzer->setQuestionHelper( $this->getHelper( 'question' ) );
				$analyzer->setOutput( $this->output );
				$analyzer->setInput( $this->input );
			}

			$result = $analyzer->analyze( $this->currentFile );
			// TODO: Evaluate result
		}
		return true;
	}

}
