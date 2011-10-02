<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_FrameLabel extends IO_SWF_Tag_Base {
    var $_label;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_label = IO_SWF_Type_String::parse($reader);
    }

    function dumpContent($tagCode, $opts = array()) {
        $label_str = IO_SWF_Type_String::string($this->_label);
        echo "\tLabel: $label_str\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        IO_SWF_Type_String::build($writer, $this->_label);
    	return $writer->output();
    }
}
