<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Analyze as CommandAnalyze;
use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\IUserInteraction;
use Symfony\Component\Console\Question\Question;

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
		var_dump( $this->config );
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

			$gerritUserQuestion = new Question( 'Question test: ', '' );
			$gerritUserQuestion->setValidator( function ( $answer ) {
			$answer = trim( $answer );
			if ( empty( $answer ) ) { throw  new \RuntimeException( "Required!" );
			}
			return $answer;
			} );

			$result = $analyzer->analyze( $this->currentFile );
			// TODO: Evaluate result
		}
		return true;
	}

}
