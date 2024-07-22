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
	 *
	 * @var DOMDocument
	 */
	protected $dom = null;

	/**
	 *
	 * @var DOMXPath
	 */
	protected $xpath = null;

	/**
	 * @var LoggerInterface
	 */
	protected $logger = null;

	/**
	 * @param DOMDocument $dom
	 */
	public function __construct( $dom ) {
		$this->dom = $dom;
		$this->xpath = new DOMXPath( $this->dom );
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 *
	 * @return DOMXPath
	 */
	public function getXPath() {
		return $this->xpath;
	}

	/**
	 * Returns the integer ID of an model entity in Confluence export XML
	 * @param DOMElement $domNode
	 * @return int
	 * @throws UnexpectedValueException
	 */
	public function getIDNodeValue( $domNode ) {
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
	 * Returns the value of id node name="key" of an model entity in Confluence export XML
	 * @param DOMElement $domNode
	 * @return string
	 * @throws UnexpectedValueException
	 */
	public function getKeyNodeValue( $domNode ) {
		if ( !$domNode->hasChildNodes() ) {
			return -1;
		}

		$hasIDNode = false;
		$value = '';
		foreach ( $domNode->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $childNode->nodeName !== 'id' ) {
				continue;
			}

			$hasIDNode = true;
			$value = (string)$childNode->nodeValue;
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
	 * @return DOMNodeList
	 */
	public function getPropertyNodes( $propName, $contextElement = null ) {
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
	 * @return DOMElement
	 */
	public function getPropertyNode( $propName, $contextElement = null ) {
		return $this->getPropertyNodes( $propName, $contextElement )->item( 0 );
	}

	/**
	 * There are some classes of <property> elements that do not contain the value
	 * directly as nodeValue but instead contain an additional <id> element that
	 * references another element in the XML
	 *
	 * @var array
	 */
	protected $propertyClassesOfTypeIDRef = [
		'Space', 'Page', 'ConfluenceUserImpl', 'Attachment'
	];

	/**
	 *
	 * @param string $propertyName
	 * @param DOMElement $contextElement
	 * @return null|string
	 */
	public function getPropertyValue( $propertyName, $contextElement ) {
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
	 * @return DOMNodeList
	 */
	public function getObjectNodes( $objectNodeClass ) {
		return $this->xpath->query( '//object[@class="' . $objectNodeClass . '"]' );
	}

	/**
	 *
	 * @param int $id
	 * @param string $objectNodeClass
	 * @return DOMElement
	 */
	public function getObjectNodeById( $id, $objectNodeClass ) {
		$xpathExpression = "//object[@class='$objectNodeClass' and id='$id']";
		return $this->xpath->query( $xpathExpression )->item( 0 );
	}

	/**
	 *
	 * @param string $collectionName
	 * @param DOMElement $contextElement
	 * @return DOMNodeList
	 */
	public function getElementsFromCollection( $collectionName, $contextElement ) {
		return $this->xpath->query( './collection[@name="' . $collectionName . '"]/element', $contextElement );
	}
}
