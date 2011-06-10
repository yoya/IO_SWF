<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Tag.php';

class IO_SWF_Tag_Sprite extends IO_SWF_Tag_Base {
    var $_spriteId = null;
    var $_frameCount = null;
    var $_controlTags = array();
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        
        $this->_spriteId = $reader->getUI16LE();
        $this->_frameCount = $reader->getUI16LE();
        /* SWF Tags */
        while (true) {
            $tag = new IO_SWF_Tag();
            $tag->parse($reader);
            $this->_controlTags[] = $tag;
            if ($tag->code == 0) { // END Tag
                break;
            }
        }
        return true;
    }
    
    function dumpContent($tagCode, $opts = array()) {
        echo "\tSprite: SpriteID={$this->_spriteId} FrameCount={$this->_frameCount}\n";
        foreach ($this->_controlTags as $tag) {
            echo "  ";
            $tag->dump($opts);
        }
    }
    
    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_spriteId);
        $writer->putUI16LE($this->_frameCount);
        foreach ($this->_controlTags as $tag) {
            $tagData = $tag->build();
            if ($tagData != false) {
                $writer->putData($tag->build());
            }
        }
    	return $writer->output();
    }
}
