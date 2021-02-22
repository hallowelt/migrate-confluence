<?php

namespace HalloWelt\MigrateConfluence\Converter;


interface IProcessable
{
    /**
     * Converts Confluence markup object to HTML.
     *
     * @param ConfluenceContentXML|null $sender Main converter class object.
     * @param \DOMNode $match Matched Confluence object to be converted.
     * @param \DOMDocument $dom DOMDocument object for context.
     * @param \DOMXPath $xpath XPath for current XML file which is being converted.
     */
    public function process( ?ConfluenceContentXML $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath ): void;
}