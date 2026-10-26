<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility;

use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver;
use PHPUnit\Framework\TestCase;

class PageConstraintsResolverTest extends TestCase {

	private function user( string $key, string $ts = '20260309130633' ): array {
		return [ 'user' => $key, 'group' => '', 'timestamp' => $ts ];
	}

	private function group( string $name ): array {
		return [ 'user' => '', 'group' => $name, 'timestamp' => '20260101000000' ];
	}

	private function resolver( array $restrictions, array $parents = [], array $groups = [] ): PageConstraintsResolver {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		return new PageConstraintsResolver(
			$restrictions,
			$parents,
			static fn ( string $key ) => [ 'k1' => 'Alice', 'k2' => 'Bob' ][$key] ?? null,
			static fn ( string $group ) => $groups[$group] ?? null,
			new DBLog( $db )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testUnrestricted(): void {
		$this->assertSame(
			[ 'type' => 'none', 'timestamp' => '', 'targets' => [] ],
			$this->resolver( [] )->resolve( 1 )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testEditOnly(): void {
		$result = $this->resolver( [ 1 => [ 'edit' => [ $this->user( 'k1' ) ] ] ] )->resolve( 1 );
		$this->assertSame( 'edit', $result['type'] );
		$this->assertSame( '2026-03-09T13:06:33Z', $result['timestamp'] );
		$this->assertSame( [ [ 'kind' => 'user', 'name' => 'Alice', 'level' => 'edit' ] ], $result['targets'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testViewAndEdit(): void {
		$result = $this->resolver(
			[ 1 => [ 'view' => [ $this->user( 'k1' ), $this->group( 'devs' ) ], 'edit' => [ $this->user( 'k1' ) ] ] ],
			[],
			[ 'devs' => 'Developers' ]
		)->resolve( 1 );
		$this->assertSame( 'read_and_edit', $result['type'] );
		$this->assertSame(
			[
				[ 'kind' => 'user', 'name' => 'Alice', 'level' => 'edit' ],
				[ 'kind' => 'group', 'name' => 'Developers', 'level' => 'read' ],
			],
			$result['targets']
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testViewOnlyGivesEditToEveryone(): void {
		$result = $this->resolver( [ 1 => [ 'view' => [ $this->user( 'k1' ) ] ] ] )->resolve( 1 );
		$this->assertSame( 'read_and_edit', $result['type'] );
		$this->assertSame( 'edit', $result['targets'][0]['level'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testAncestorRestrictionIsIntersected(): void {
		$result = $this->resolver(
			[
				1 => [ 'view' => [ $this->user( 'k1' ), $this->user( 'k2' ) ] ],
				2 => [ 'view' => [ $this->user( 'k1' ) ] ],
			],
			[ 3 => 2, 2 => 1, 1 => -1 ]
		)->resolve( 3 );
		$this->assertSame( 'read_and_edit', $result['type'] );
		$this->assertCount( 1, $result['targets'] );
		$this->assertSame( 'Alice', $result['targets'][0]['name'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testUnknownUserAndEmptyListFailClosed(): void {
		$result = $this->resolver( [ 1 => [ 'view' => [ $this->user( 'nope' ) ] ] ] )->resolve( 1 );
		$this->assertSame( 'read_and_edit', $result['type'] );
		$this->assertSame( [], $result['targets'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testEditMemberWithoutViewGetsNoAccess(): void {
		$result = $this->resolver(
			[ 1 => [ 'view' => [ $this->user( 'k1' ) ], 'edit' => [ $this->user( 'k2' ) ] ] ]
		)->resolve( 1 );
		$this->assertSame( [ [ 'kind' => 'user', 'name' => 'Alice', 'level' => 'read' ] ], $result['targets'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver::resolve
	 */
	public function testParentCycleTerminates(): void {
		$result = $this->resolver( [], [ 1 => 2, 2 => 1 ] )->resolve( 1 );
		$this->assertSame( 'none', $result['type'] );
	}
}
