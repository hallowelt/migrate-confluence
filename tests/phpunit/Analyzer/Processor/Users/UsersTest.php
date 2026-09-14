<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Users;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class UsersTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Users::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new Users( new AnalyzerDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/user.xml', 'ConfluenceUserImpl' );

		$users = $this->workspaceDB->getUsers();
		$this->assertCount( 1, $users, 'Expected exactly one user row.' );

		$user = $users[0];
		$this->assertSame( 'user-key-1', $user['user_key'], 'Unexpected user_key value.' );
		$this->assertSame( 'Johndoe', $user['wiki_user_name'], 'Unexpected wiki_user_name value.' );
		$this->assertSame( 'john@example.org', $user['email'], 'Unexpected email value.' );

		$properties = json_decode( $user['properties'], true );
		$this->assertSame( 'johndoe@example.org', $properties['lowerName'], 'Unexpected properties.lowerName value.' );
		$this->assertSame( 'user-key-1', $properties['key'], 'Unexpected properties.key value.' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Users::doExecute
	 */
	public function testPrePopulatedUsermapOverridesComputedUsername(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();
		// Simulate a --usermap CSV pre-population: real user_key is not known
		// yet, so the confluence username is used as a placeholder user_key.
		$this->workspaceDB->addUser( 'johndoe@example.org', 'NewAccountName', '', [], 'johndoe@example.org' );

		$processor = new Users( new AnalyzerDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/user.xml', 'ConfluenceUserImpl' );

		$users = $this->workspaceDB->getUsers();
		$this->assertCount( 1, $users, 'Placeholder row should be adopted, not duplicated.' );

		$user = $users[0];
		$this->assertSame( 'user-key-1', $user['user_key'], 'Placeholder row should be adopted to the real user_key.' );
		$this->assertSame(
			'NewAccountName', $user['wiki_user_name'], 'Pre-populated username should not be clobbered.'
		);
		$this->assertSame( 'john@example.org', $user['email'], 'Email from entities.xml should still be stored.' );
	}
}
