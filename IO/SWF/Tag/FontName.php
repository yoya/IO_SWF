<?php

/*
 * DefineFontName (88)
 *   FontID UI16, FontName STRING, FontCopyright STRING
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_FontName extends IO_SWF_Tag_Base {
    var $CharacterID;   // FontID
    var $FontName;
    var $FontCopyright;
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->FontName = IO_SWF_Type_String::parse($reader);
        $this->FontCopyright = IO_SWF_Type_String::parse($reader);
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    FontID: {$this->CharacterID}  FontName: {$this->FontName}\n";
        echo "    FontCopyright: {$this->FontCopyright}\n";
        if (! is_null($this->_rest)) {
            echo "    (trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        IO_SWF_Type_String::build($writer, $this->FontName);
        IO_SWF_Type_String::build($writer, $this->FontCopyright);
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
