<?php

namespace HalloWelt\MigrateConfluence\Converter;


use DOMDocument;
use DOMNode;
use DOMXPath;

interface IProcessable
{
    /**
     * Converts Confluence markup object to HTML.
     *
     * @param ConfluenceConverter|null $sender Main converter class object.
     * @param DOMNode $match Matched Confluence object to be converted.
     * @param DOMDocument $dom DOMDocument object for context.
     * @param DOMXPath $xpath XPath for current XML file which is being converted.
     */
    public function process( ?ConfluenceConverter $sender, DOMNode $match, DOMDocument $dom, DOMXPath $xpath ): void;
}