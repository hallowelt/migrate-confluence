<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;


use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class LocalTabGroup implements \HalloWelt\MigrateConfluence\Converter\IProcessable
{
    /**
     * {@inheritDoc}
     *
     *   <ac::macro ac:name="localtabgroup">
     *   <ac::rich-text-body>
     *   <ac::macro ac:name="localtab">
     *   <ac::parameter ac:name="title">...</acparameter>
     *   <ac::rich-text-body>...</acrich-text-body>
     *   </ac:macro>
     *   </ac:rich-text-body>
     *   </ac:macro>
     */
    public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath): void
    {
        // TODO: Implement process() method.
    }
}