<?php

/*
 * DefineButtonCxform (23)
 *   ButtonId UI16, ButtonColorTransform CXFORM
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/CXFORM.php';

class IO_SWF_Tag_ButtonCxform extends IO_SWF_Tag_Base {
    var $CharacterID;   // ButtonId
    var $_colorTransform;
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->_colorTransform = IO_SWF_Type_CXFORM::parse($reader);
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tButtonId: {$this->CharacterID}\n";
        echo "\tButtonColorTransform: ".IO_SWF_Type_CXFORM::string($this->_colorTransform)."\n";
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        IO_SWF_Type_CXFORM::build($writer, $this->_colorTransform);
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
