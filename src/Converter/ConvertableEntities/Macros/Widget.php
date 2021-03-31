<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;


use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class Widget implements \HalloWelt\MigrateConfluence\Converter\IProcessable
{

    /**
     * @inheritDoc
     */
    public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath): void
    {
        // TODO: Implement process() method.
    }
}