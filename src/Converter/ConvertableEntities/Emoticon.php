<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;


class Emoticon implements \HalloWelt\MigrateConfluence\Converter\IProcessable
{

    public function process( $sender, $match, $dom, $xpath ): void
    {
        $replacement = '';
        $sKey = $match->getAttribute('ac:name');
        if( !isset($this->aEmoticonMapping[$sKey]) ) {
            //$this->log( 'EMOTICON: '. $sKey );
        }
        else {
            $replacement = " $sKey ";
        }
        //$this->notify( 'processEmoticon', array( $match, $dom, $xpath, &$replacement ) );
        if( !empty( $replacement ) ) {
            $match->parentNode->replaceChild(
                $dom->createTextNode( $replacement ),
                $match
            );
        }
    }
}