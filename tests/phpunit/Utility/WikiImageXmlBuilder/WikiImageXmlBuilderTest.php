<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\TitleValidityChecker;

use HalloWelt\MigrateConfluence\Utility\WikiImageXmlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\WikiImageXmlBuilder
 */
class WikiImageXmlBuilderTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\WikiImageXmlBuilder::buildAndSave
	 */
	public function testBuildAndSave(): void {
		$fileRevisions = $this->getFileData();

		$builder = new WikiImageXmlBuilder();
		$builder->init();

		foreach ( $fileRevisions as $fileTitle => $revisionData ) {
			$builder->addFileRevision(
				$fileTitle,
				$revisionData['data'],
				$revisionData['timestamp'],
				$revisionData['contributor']
			);
		}

		$tmpPath = tempnam( sys_get_temp_dir(), 'wiki-file-xmlbuilder-' ) . '.xml';
		$builder->buildAndSave( $tmpPath );

		$actual = file_get_contents( $tmpPath );
		$expected = file_get_contents( __DIR__ . '/expected-file-xml.xml' );

		$this->assertEquals( $expected, $actual );
	}

	private function getFileData(): array {
		return [
			'dummy_1.png' => [
				'timestamp' => '20260524123159',
				'contributor' => 'SomeUser',
				'data' => './images/dummy_1.png',
			],
			'dummy_1.png' => [
				'timestamp' => '20260524123305',
				'contributor' => 'SomeUser',
				'data' => './images/dummy_1.png',
			],
			'dummy_1.png' => [
				'timestamp' => '20260524123708',
				'contributor' => 'SomeOtherUser',
				'data' => './images/dummy_1.png',
			],
			'dummy_2.jpg' => [
				'timestamp' => '20260524133305',
				'contributor' => 'TestUser',
				'data' => './images/dummy_2.jpg',
			],
			'dummy_2.jpg' => [
				'timestamp' => '20260524133708',
				'contributor' => 'TestUser',
				'data' => './images/dummy_2.jpg',
			]
		];
	}

}
