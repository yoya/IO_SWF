<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/RGB.php';

class IO_SWF_Tag_BGColor extends IO_SWF_Tag_Base {
    var $_color;

   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_color = IO_SWF_Type_RGB::parse($reader);
    }

    function dumpContent($tagCode, $opts = array()) {
        $color_str = IO_SWF_Type_RGB::string($this->_color);
        echo "\tColor: $color_str\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        IO_SWF_Type_RGB::parse($writer, $this->_color);
    	return $writer->output();
    }
}
