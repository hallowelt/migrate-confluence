<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UnexpectedValueException;

class XMLHelper implements LoggerAwareInterface {

	/**
	 * @var DOMDocument|null
	 */
	protected ?DOMDocument $dom = null;

	/**
	 * @var DOMXPath|null
	 */
	protected ?DOMXPath $xpath = null;

	/**
	 * @var LoggerInterface|NullLogger|null
	 */
	protected LoggerInterface|NullLogger|null $logger = null;

	/**
	 * @param DOMDocument $dom
	 */
	public function __construct( DOMDocument $dom ) {
		$this->dom = $dom;
		$this->xpath = new DOMXPath( $this->dom );
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Returns the integer ID of an model entity in Confluence export XML
	 *
	 * @param DOMElement $domNode
	 *
	 * @return int
	 * @throws UnexpectedValueException
	 */
	public function getIDNodeValue( DOMElement $domNode ): int {
		if ( !$domNode->hasChildNodes() ) {
			return -1;
		}

		$hasIDNode = false;
		$value = -1;
		foreach ( $domNode->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $childNode->nodeName !== 'id' ) {
				continue;
			}

			$hasIDNode = true;
			$value = (int)$childNode->nodeValue;
			break;
		}

		if ( !$hasIDNode ) {
			throw new UnexpectedValueException( 'No ID element found!' );
		}

		return $value;
	}

	/**
	 *
	 * @param string $propName
	 * @param DOMElement|null $contextElement
	 *
	 * @return DOMNodeList
	 */
	public function getPropertyNodes( string $propName, DOMElement $contextElement = null ): DOMNodeList {
		if ( $contextElement === null ) {
			// Fetch all in whole document
			return $this->xpath->query( '//property[@name="' . $propName . '"]' );
		}

		// Fetch only direct children from context
		return $this->xpath->query( './property[@name="' . $propName . '"]', $contextElement );
	}

	/**
	 *
	 * @param string $propName
	 * @param DOMElement|null $contextElement
	 * 
	 * @return DOMElement
	 */
	public function getPropertyNode( string $propName, DOMElement $contextElement = null ): DOMElement {
		return $this->getPropertyNodes( $propName, $contextElement )->item( 0 );
	}

	/**
	 * There are some classes of <property> elements that do not contain the value
	 * directly as nodeValue but instead contain an additional <id> element that
	 * references another element in the XML
	 *
	 * @var array
	 */
	protected array $propertyClassesOfTypeIDRef = [
		'Space', 'Page', 'ConfluenceUserImpl', 'Attachment'
	];

	/**
	 *
	 * @param string $propertyName
	 * @param DOMElement $contextElement
	 *
	 * @return int|string|null
	 */
	public function getPropertyValue( string $propertyName, DOMElement $contextElement ): int|string|null {
		$propertyNode = $this->getPropertyNode( $propertyName, $contextElement );
		if ( $propertyNode instanceof DOMElement == false ) {
			$contextElementId = $this->getIDNodeValue( $contextElement );
			$this->logger->debug(
				'Node "' . $contextElement->getNodePath() . " (ID:$contextElementId)" .
					'" contains no property "' . $propertyName . '"!'
			);
			return null;
		}
		$sClass = $propertyNode->getAttribute( 'class' );
		if ( in_array( $sClass, $this->propertyClassesOfTypeIDRef ) ) {
			return $this->getIDNodeValue( $propertyNode );
		}

		return $propertyNode->nodeValue;
	}

	/**
	 *
	 * @param string $objectNodeClass e.g. 'Space', 'Page', 'Attachment',
	 * 'BodyContent', 'ConfluenceUserImpl', ...
	 *
	 * @return DOMNodeList
	 */
	public function getObjectNodes( string $objectNodeClass ): DOMNodeList {
		return $this->xpath->query( '//object[@class="' . $objectNodeClass . '"]' );
	}

	/**
	 *
	 * @param string $collectionName
	 * @param DOMElement $contextElement
	 *
	 * @return DOMNodeList
	 */
	public function getElementsFromCollection( string $collectionName, DOMElement $contextElement ): DOMNodeList {
		return $this->xpath->query( './collection[@name="' . $collectionName . '"]/element', $contextElement );
	}
}
