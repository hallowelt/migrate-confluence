<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use XMLReader;

class Users extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'global-userkey-to-username-map',
			'users'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$userId = '';
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'key' ) {
					$userId = $this->getCDATAValue();
				} else {
					$userId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( !isset( $properties['lowerName'] ) || $properties['lowerName'] === ''  ) {
			$this->output->writeln( "\033[31m User $userId has no username\033[39m" );
			return;
		}

		$mediaWikiUsername = $this->makeMWUserName( $properties['lowerName'] );

		if ( !isset( $properties['email'] ) ) {
			$properties['email'] = '';
		}

		$this->data['global-userkey-to-username-map'][$userId] = $mediaWikiUsername;
		$this->data['users'][$mediaWikiUsername] = $properties;

		$this->output->writeln( "Add user '$mediaWikiUsername' (ID:$userId)" );

		/*
		$this->buckets->addData(
			'global-userkey-to-username-map',
			$objectNodeKey,
			$mediaWikiUsername,
			false
		);

		$this->customBuckets->addData(
			'users',
			$mediaWikiUsername,
			[
				'email' => $email === null ? '' : $email
			],
			false,
			true
		);
		*/
	}

	/**
	 * @param string $userName
	 * @return string
	 */
	private function makeMWUserName( $userName ) {
		// Email adresses are no valid MW usernames. We just use the first part
		// While this could lead to collisions it is very unlikly
		$usernameParts = explode( '@', $userName, 2 );
		$newUsername = $usernameParts[0];
		$newUsername = ucfirst( strtolower( $newUsername ) );

		// A MW username must always be avalid page title
		$titleBuilder = new GenericTitleBuilder( [] );
		$titleBuilder->appendTitleSegment( $newUsername );

		return $titleBuilder->build();
	}
}
