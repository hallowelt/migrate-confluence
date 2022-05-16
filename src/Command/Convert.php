<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Convert as CommandConvert;
use HalloWelt\MediaWiki\Lib\Migration\IConverter;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Convert extends CommandConvert {

	/**
	 *
	 * @var string
	 */
	private $targetBasePath = '';

	protected function configure() {
		$config = parent::configure();

		/** @var InputDefinition */
		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'config',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the config yaml file'
			)
		);

		return $config;
	}

	/**
	 * @param array $config
	 * @return Convert
	 */
	public static function factory( $config ): Convert {
		return new static( $config );
	}

	protected function doProcessFile(): bool {
		$converterFactoryCallbacks = $this->config['converters'];
		$this->readConfigFile( $this->config );

		$this->makeTargetPathname();
		$this->ensureTargetPath();

		foreach ( $converterFactoryCallbacks as $key => $callback ) {
			$converter = call_user_func_array(
				$callback,
				[ $this->config, $this->workspace ]
			);
			if ( $converter instanceof IConverter === false ) {
				throw new Exception(
					"Factory callback for converter '$key' did not return an "
					. "IConverter object"
				);
			}
			if ( $converter instanceof IOutputAwareInterface ) {
				$converter->setOutput( $this->output );
			}
			$result = $converter->convert( $this->currentFile );
			file_put_contents( $this->targetPathname, $result );
		}
		return true;
	}

	private function makeTargetPathname() {
		$this->targetPathname = str_replace(
			$this->src,
			$this->targetBasePath,
			$this->currentFile->getPathname()
		);
		$this->targetPathname = preg_replace( '#\.mraw$#', '.wiki', $this->targetPathname );
	}

	private function ensureTargetPath() {
		$baseTargetPath = dirname( $this->targetPathname );
		if ( !file_exists( $baseTargetPath ) ) {
			mkdir( $baseTargetPath, 0755, true );
		}
	}

	/**
	 * @param array &$config
	 * @return void
	 */
	private function readConfigFile( &$config ): void {
		$filename = $this->input->getOption( 'config' );
		if ( is_file( $filename ) ) {
			$content = file_get_contents( $filename );
			if ( $content ) {
				try {
					$yaml = Yaml::parse( $content );
					$config = array_merge( $config, $yaml );
				}
				catch ( ParseException $e ) {
				}
			}
		}
	}
}
