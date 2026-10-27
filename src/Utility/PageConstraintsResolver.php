<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DateTimeImmutable;
use DateTimeZone;
use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;

/**
 * Computes the effective page restrictions of a content object, including those of its ancestors.
 *
 * Confluence requires a user to pass the restrictions of all ancestors. The wiki format has no inheritance
 * (inherit="0"), so the effective lists are intersected. Without group membership data this is exact for
 * a single restricting level and an approximation for several levels.
 */
class PageConstraintsResolver {

	/** @var string[] */
	private array $warnedGroups = [];

	/**
	 * @param array<int, array{view: ?array, edit: ?array}> $restrictions Per content id the permission
	 *   lists ("view"/"edit"; null if that operation is not restricted). Each permission is an array with
	 *   the keys "user", "group" and "timestamp" (YmdHis).
	 * @param array<int, int> $parents Content id to parent content id
	 * @param callable $userLookup Takes a Confluence user key, returns the wiki user name or null
	 * @param callable $groupLookup Takes a Confluence group name, returns the wiki group name or null
	 */
	public function __construct(
		private readonly array $restrictions,
		private readonly array $parents,
		private $userLookup,
		private $groupLookup
	) {
	}

	/**
	 * @param int $contentId
	 * @return array{type: string, timestamp: string, targets: array}
	 */
	public function resolve( int $contentId ): array {
		$view = null;
		$edit = null;
		$newest = '';
		$seen = [];
		$id = $contentId;
		while ( $id > 0 && !isset( $seen[$id] ) ) {
			$seen[$id] = true;
			foreach ( $this->restrictions[$id] ?? [] as $operation => $list ) {
				if ( $list === null ) {
					continue;
				}
				$identities = [];
				foreach ( $list as $permission ) {
					$newest = max( $newest, $permission['timestamp'] ?? '' );
					$identity = $this->identify( $permission, $contentId );
					if ( $identity !== null ) {
						$identities[$identity] = true;
					}
				}
				if ( $operation === 'view' ) {
					$view = $this->intersect( $view, $identities, $contentId, $id );
				} else {
					$edit = $this->intersect( $edit, $identities, $contentId, $id );
				}
			}
			$id = (int)( $this->parents[$id] ?? 0 );
		}

		$timestamp = $this->toIso( $newest );
		if ( $view === null && $edit === null ) {
			return [ 'type' => 'none', 'timestamp' => '', 'targets' => [] ];
		}

		if ( $view !== null && $edit !== null ) {
			$lost = array_diff_key( $edit, $view );
			if ( $lost ) {
				$this->log(
					'warning', $contentId,
					'Edit restriction names ' . implode( ', ', array_keys( $lost ) )
					. ' who may not view the page; they get no access.'
				);
			}
			$edit = array_intersect_key( $edit, $view );
		}

		$targets = [];
		if ( $view !== null ) {
			foreach ( array_keys( $view ) as $identity ) {
				$canEdit = $edit === null || isset( $edit[$identity] );
				$targets[] = $this->buildTarget( $identity, $canEdit ? 'edit' : 'read' );
			}
			$type = 'read_and_edit';
		} else {
			foreach ( array_keys( $edit ) as $identity ) {
				$targets[] = $this->buildTarget( $identity, 'edit' );
			}
			$type = 'edit';
		}

		return [ 'type' => $type, 'timestamp' => $timestamp, 'targets' => $targets ];
	}

	/**
	 * @param array $permission
	 * @param int $contentId
	 * @return string|null "user:<wiki name>" or "group:<wiki group>"; null if it cannot be resolved
	 */
	private function identify( array $permission, int $contentId ): ?string {
		$userKey = (string)( $permission['user'] ?? '' );
		$group = (string)( $permission['group'] ?? '' );
		if ( $userKey !== '' ) {
			$name = ( $this->userLookup )( $userKey );
			if ( $name === null || $name === '' ) {
				$this->log(
					'warning', $contentId,
					"Restriction names unknown user key '$userKey'; dropped, access may be narrower than in Confluence."
				);
				return null;
			}
			return 'user:' . $name;
		}
		if ( $group !== '' ) {
			$wikiGroup = ( $this->groupLookup )( $group );
			if ( $wikiGroup === null && !isset( $this->warnedGroups[$group] ) ) {
				$this->warnedGroups[$group] = true;
				$this->log( 'warning', $contentId, "Group '$group' has no entry in the group map; name kept as is." );
			}
			return 'group:' . ( $wikiGroup ?? $group );
		}
		$this->log( 'warning', $contentId, 'Restriction without user or group; it grants access to nobody.' );
		return null;
	}

	/**
	 * @param array|null $current
	 * @param array $level
	 * @param int $contentId
	 * @param int $levelId
	 * @return array
	 */
	private function intersect( ?array $current, array $level, int $contentId, int $levelId ): array {
		if ( $current === null ) {
			return $level;
		}
		$lost = array_diff_key( $current + $level, array_intersect_key( $current, $level ) );
		if ( $lost ) {
			$this->log(
				'warning', $contentId,
				"Restrictions of content $levelId and its descendants differ; dropped "
				. implode( ', ', array_keys( $lost ) ) . '. Group membership is unknown, so this may be inexact.'
			);
		}
		return array_intersect_key( $current, $level );
	}

	/**
	 * @param string $identity
	 * @param string $level
	 * @return array{kind: string, name: string, level: string}
	 */
	private function buildTarget( string $identity, string $level ): array {
		[ $kind, $name ] = explode( ':', $identity, 2 );
		return [ 'kind' => $kind, 'name' => $name, 'level' => $level ];
	}

	/**
	 * @param string $timestamp
	 * @return string
	 */
	private function toIso( string $timestamp ): string {
		$date = DateTimeImmutable::createFromFormat( 'YmdHis', $timestamp, new DateTimeZone( 'UTC' ) );
		return $date ? $date->format( 'Y-m-d\TH:i:s\Z' ) : '';
	}

	/**
	 * @param string $type
	 * @param int $contentId
	 * @param string $text
	 */
	private function log( string $type, int $contentId, string $text ): void {
		Log::to( 'db' )->$type( "Content $contentId: $text" );
	}
}
