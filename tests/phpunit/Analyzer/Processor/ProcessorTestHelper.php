<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\Output;
use XMLReader;

trait ProcessorTestHelper {

	private function makeOutput(): Output {
		return new class extends Output {
			public function doWrite( string $message, bool $newline ): void {
			}
		};
	}

	private function executeProcessorForClass(
		IAnalyzerProcessor $processor,
		string $xmlFile,
		string $className
	): void {
		$processor->setOutput( $this->makeOutput() );
		$processor->setLogger( new NullLogger() );

		$xmlReader = new XMLReader();
		$xmlReader->open( $xmlFile );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}

			if ( $xmlReader->getAttribute( 'class' ) !== $className ) {
				$read = $xmlReader->next();
				continue;
			}

			$processor->execute( $xmlReader );
			$read = $xmlReader->next();
		}

		$xmlReader->close();
	}
}
