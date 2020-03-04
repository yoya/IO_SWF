<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
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
            $tag = new IO_SWF_Tag($this->swfInfo);
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
        $opts['FrameNum'] = 0;
        foreach ($this->_controlTags as $tag) {
            echo "  ";
            try {
                $tag->dump($opts);
            } catch (IO_Bit_Exception $e) {
                echo "(tag parse failed)\n";
            }
        }
    }
    
    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_spriteId);
        $writer->putUI16LE($this->_frameCount);
        foreach ($this->_controlTags as $idx => $tag) {
            $tagData = $tag->build();
            if ($tagData !== false) {
                $writer->putData($tagData);
            } else {
                throw new Exception("failed to tag build in sprite controlTags:[$idx]");
            }
        }
    	return $writer->output();
    }
}
