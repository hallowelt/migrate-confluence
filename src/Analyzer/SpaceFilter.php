<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Decides, during the main `analyze` pass, whether an object read from
 * `entities.xml` belongs to the space named in `exportDescriptor.properties`
 * or to a foreign space leaked into the export by the Confluence bug
 * described in doc/configuration.md (`filter-foreign-space-data`).
 *
 * Built once per entities.xml by SpaceFilterPrescanProcessor and then handed
 * to the normal Analyzer processors. A processor is not required to hold an
 * instance (constructors default to null = "no filtering").
 */
class SpaceFilter {

	/** @var array<int,bool> spaceId => true, for spaces matching the expected spaceKey */
	private array $allowedSpaceIds = [];

	/** @var array<int,int> contentId => owning spaceId (Page/BlogPost/PageTemplate/Attachment/SpaceDescription/Comment) */
	private array $contentSpaceMap = [];

	/** @var array<int,int> labellingId => owning spaceId */
	private array $labellingSpaceMap = [];

	/** @var array<string,int> entityType => number of objects filtered out */
	private array $skipCounts = [];

	/**
	 * @param IAnalyzeDataWriter $writer
	 * @param OutputInterface $output
	 */
	public function __construct(
		private readonly IAnalyzeDataWriter $writer,
		private readonly OutputInterface $output
	) {
	}

	/**
	 * @param array<int,bool> $allowedSpaceIds
	 * @param array<int,int> $contentSpaceMap
	 * @param array<int,int> $labellingSpaceMap
	 * @return void
	 */
	public function configure( array $allowedSpaceIds, array $contentSpaceMap, array $labellingSpaceMap ): void {
		$this->allowedSpaceIds = $allowedSpaceIds;
		$this->contentSpaceMap = $contentSpaceMap;
		$this->labellingSpaceMap = $labellingSpaceMap;
	}

	/**
	 * For objects that carry their space id directly: Space, Page, BlogPost,
	 * PageTemplate, Attachment.
	 *
	 * @param string $entityType
	 * @param int $entityId
	 * @param int|null $spaceId
	 * @return bool
	 */
	public function isSpaceAllowed( string $entityType, int $entityId, ?int $spaceId ): bool {
		if ( $spaceId === null || isset( $this->allowedSpaceIds[$spaceId] ) ) {
			return true;
		}

		$this->deny( $entityType, $entityId, $spaceId, 'space id does not match the expected space' );
		return false;
	}

	/**
	 * For objects that only reference a content id: Comment (containerContent),
	 * BodyContents/ContentProperty (content), SpaceDescription (its own id,
	 * resolved via the owning Space's "description" property).
	 *
	 * Ownership that cannot be resolved is kept (allow-and-log), since the
	 * risk of silently dropping legitimate content outweighs the risk of
	 * missing a rare leftover leaked object.
	 *
	 * @param string $entityType
	 * @param int $entityId
	 * @param int|null $contentId
	 * @return bool
	 */
	public function isContentAllowed( string $entityType, int $entityId, ?int $contentId ): bool {
		if ( $contentId === null ) {
			return true;
		}

		if ( !isset( $this->contentSpaceMap[$contentId] ) ) {
			$this->writer->addLogEntry(
				'warning',
				'analyze',
				__CLASS__,
				"$entityType (ID:$entityId): could not resolve the owning space of content ID $contentId;"
					. ' keeping the object.'
			);
			return true;
		}

		$spaceId = $this->contentSpaceMap[$contentId];
		if ( isset( $this->allowedSpaceIds[$spaceId] ) ) {
			return true;
		}

		$this->deny( $entityType, $entityId, $spaceId, "belongs to content ID $contentId of a foreign space" );
		return false;
	}

	/**
	 * For Labelling objects, whose ownership is only known from the
	 * "labellings" collection of the content object that references them.
	 *
	 * @param int $labellingId
	 * @return bool
	 */
	public function isLabellingAllowed( int $labellingId ): bool {
		if ( !isset( $this->labellingSpaceMap[$labellingId] ) ) {
			return true;
		}

		$spaceId = $this->labellingSpaceMap[$labellingId];
		if ( isset( $this->allowedSpaceIds[$spaceId] ) ) {
			return true;
		}

		$this->deny( 'Labelling', $labellingId, $spaceId, 'labellable content belongs to a foreign space' );
		return false;
	}

	/**
	 * @param string $entityType
	 * @param int $entityId
	 * @param int $foreignSpaceId
	 * @param string $reason
	 * @return void
	 */
	private function deny( string $entityType, int $entityId, int $foreignSpaceId, string $reason ): void {
		$this->writer->addFilteredObject( $entityType, $entityId, $foreignSpaceId, $reason );
		$this->writer->addLogEntry(
			'warning',
			'analyze',
			__CLASS__,
			"Filtered out $entityType (ID:$entityId): $reason (foreign space ID $foreignSpaceId)."
		);
		$this->skipCounts[$entityType] = ( $this->skipCounts[$entityType] ?? 0 ) + 1;
	}

	/**
	 * Prints one summary line per filtered entity type, instead of spamming
	 * the console with one line per skipped object.
	 *
	 * @return void
	 */
	public function writeSummary(): void {
		foreach ( $this->skipCounts as $entityType => $count ) {
			$this->output->writeln( "Foreign-space filter: skipped $count $entityType object(s)." );
		}
	}
}
