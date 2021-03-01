<?php


namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;


class Emoticon implements \HalloWelt\MigrateConfluence\Converter\IProcessable
{

    protected $aEmoticonMapping = array(
        'smile' => ':)',
        'sad' => ':( ',
        'cheeky' => ':P',
        'laugh' => ':D',
        'wink' => ';)',
        'thumbs-up' => '(y)',
        'thumbs-down' => '(n)',
        'information' => '(i)',
        'tick' => '(/)',
        'cross' => '(x)',
        'warning' => '(!)',

        //Non standard!?
        'question' => '(?)',
    );

    public function process( $sender, $match, $dom, $xpath ): void
    {
        $replacement = '';
        $sKey = $match->getAttribute('ac:name');
        if( !isset($this->aEmoticonMapping[$sKey]) ) {
            //$this->log( 'EMOTICON: '. $sKey );
        }
        else {
            $replacement = " {$this->aEmoticonMapping[$sKey]} ";
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