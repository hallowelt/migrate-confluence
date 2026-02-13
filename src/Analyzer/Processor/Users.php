<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

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
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'ConfluenceUserImpl' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		
		// Can not use `XMLHelper::getIDNodeValue` here, as the key is not an integer
		$idNode = $objectNode->getElementsByTagName( 'id' )->item( 0 );
		$objectNodeKey = $idNode->nodeValue;
		$lcUserName = $this->xmlHelper->getPropertyValue( 'lowerName', $objectNode );
		$email = $this->xmlHelper->getPropertyValue( 'email', $objectNode );
		if ( !$lcUserName ) {
			$this->output->writeln( "\033[31m User $objectNodeKey has no username\033[39m" );
			return;
		}

		$mediaWikiUsername = $this->makeMWUserName( $lcUserName );

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

		$this->data['global-userkey-to-username-map'][$objectNodeKey] = $mediaWikiUsername;
		$this->data['users'][$mediaWikiUsername] = [
			'email' => $email === null ? '' : $email
		];

		$this->output->writeln( "Add user '$mediaWikiUsername' (ID:$objectNodeKey)" );
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
