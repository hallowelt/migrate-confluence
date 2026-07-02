<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\WikiFileXmlBuilder;

use HalloWelt\MigrateConfluence\Utility\WikiUserXmlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\WikiUserXmlBuilderTest
 */
class WikiUserXmlBuilderTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\WikiUserXmlBuilderTest::buildAndSave
	 */
	public function testBuildAndSave(): void {
		$users = $this->getUserData();

		$builder = new WikiUserXmlBuilder();

		foreach ( $users as $wikiUsername => $propertiesJson ) {
			$builder->addUser( $wikiUsername, $propertiesJson );
		}

		$tmpPath = tempnam( sys_get_temp_dir(), 'wiki-user-xmlbuilder-' ) . '.xml';
		try {
			$builder->buildAndSave( $tmpPath );
			$actual = file_get_contents( $tmpPath );
			$expected = file_get_contents( __DIR__ . '/expected-file-xml.xml' );
			$this->assertEquals( $expected, $actual );
		} finally {
			unlink( $tmpPath );
		}
	}

	private function getUserData(): array {
		return [
			'Testuser_1' => [
				'email' => 'testuser1@example.com',
				'name' => 'tu1',
				'key' => '23413500cjvoierl08450',
			],
			'Testuser_2' => [
				'email' => 'testuser2@example.com',
				'name' => 'tu2',
				'key' => '413500cjvoafdjo',
			],
			'Testuser_3' => [
				'email' => 'testuser3@example.com',
				'name' => 'tu3',
				'key' => 'adf3245dlfjv09',
			],
			'Testuser_4' => [
				'email' => 'testuser4@example.com',
				'name' => 'tu4',
				'key' => '08vjiaf452fdfg',
			],
			'Testuser_5' => [
				'email' => 'testuser5@example.com',
				'name' => 'tu5',
				'key' => '870090855354gjo',
			],
			'Testuser_6' => [
				'email' => 'testuser6@example.com',
				'name' => 'tu6',
				'key' => '435ujvouij4ou4556f09g',
			],
		];
	}

}
