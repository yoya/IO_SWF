<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_FrameLabel extends IO_SWF_Tag_Base {
    var $_label;
    var $_namedAnchor = null; // SWF6- : ラベルの後ろに付く UI8 (付いていれば 1)

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_label = IO_SWF_Type_String::parse($reader);
        if ($reader->hasNextData(1)) {
            $this->_namedAnchor = $reader->getUI8();
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $label_str = IO_SWF_Type_String::string($this->_label);
        echo "\tLabel: $label_str";
        if (! is_null($this->_namedAnchor)) {
            echo "  NamedAnchor: {$this->_namedAnchor}";
        }
        echo "\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        IO_SWF_Type_String::build($writer, $this->_label);
        if (! is_null($this->_namedAnchor)) {
            $writer->putUI8($this->_namedAnchor);
        }
    	return $writer->output();
    }
}
