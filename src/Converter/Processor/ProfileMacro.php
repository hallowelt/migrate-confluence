<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Converts the Confluence (user) profile macro
 *
 * @see https://support.atlassian.com/confluence-cloud/docs/insert-the-user-profile-macro/
 */
class ProfileMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public const MACRO_NAME = 'profile';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'UserProfile' ];

	public function __construct(
		private readonly DBConversionDataLookup $dataLookup,
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	public function process( DOMDocument $dom ): void {
		parent::process( $dom );
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$username = '';

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}

			if ( $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}

			$name = mb_strtolower( $childNode->getAttribute( 'ac:name' ) );
			if ( $name === 'user' ) {
				foreach ( $childNode->childNodes as $subChildNode ) {
					if ( $subChildNode instanceof DOMElement && $subChildNode->nodeName === 'ri:user' ) {
						$userNode = $subChildNode;
						$userkey = $userNode->getAttribute( 'ri:userkey' );
						$username = $this->dataLookup->getUsernameFromUserKey( $userkey ) ?? $userkey;
						if ( $username ) {
							break;
						}
					}
				}
			}
		}

		if ( !$username ) {
			/* let UnknownMacro processor handle ones where we cannot determine the username */
			return;
		}

		$parent = $node->parentNode;

		$placeholder = $this->createTextNode(
			$node->ownerDocument,
			$this->placeholderManager->getPlaceholder(
				sprintf(
					'<user-profile user="%s" framed="false" orientation="horizontal" />',
					$username ) ),
			__METHOD__
		);
		$parent->insertBefore( $placeholder, $node );
		$parent->removeChild( $node );
	}

}
