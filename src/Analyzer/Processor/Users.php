<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use XMLReader;

/**
 * object class="ConfluenceUserImpl" package="com.atlassian.confluence.user">
 * 	<id name="key"><![CDATA[12345]]></id>
 * 	<property name="name"><![CDATA[name]]></property>
 * 	<property name="lowerName"><![CDATA[name]]></property>
 * 	<property name="atlassianAccountId"><![CDATA[12345]]></property>
 * </object>
 */
class Users extends ProcessorBase {

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
		$userKey = '';
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$userKey = $this->getCDATAValue();
				} else {
					$userKey = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( !isset( $properties['lowerName'] ) || $properties['lowerName'] === '' ) {
			$this->output->writeln( "\033[31m User $userKey has no username\033[39m" );
			return;
		}

		$properties['key'] = $userKey;

		$mediaWikiUsername = $this->makeMWUserName( $properties['lowerName'] );

		if ( !isset( $properties['email'] ) ) {
			$properties['email'] = '';
		}

		$this->data['global-userkey-to-username-map'][$userKey] = $mediaWikiUsername;
		$this->data['users'][$mediaWikiUsername] = $properties;

		$this->output->writeln( "Add user '$mediaWikiUsername' (ID:$userKey)" );
	}

	/**
	 * @param string $userName
	 *
	 * @return string
	 * @throws InvalidTitleException
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
