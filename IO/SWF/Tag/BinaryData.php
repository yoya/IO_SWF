<?php

/*
 * DefineBinaryData (87)
 *   Tag UI16, Reserved UI32 (=0), Data BINARY (タグの終わりまで)
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_BinaryData extends IO_SWF_Tag_Base {
    var $CharacterID;   // Tag
    var $_reserved = 0;
    var $_data = '';

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->_reserved = $reader->getUI32LE();
        $this->_data = $reader->hasNextData(1) ? $reader->getDataUntil(false) : '';
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tTag: {$this->CharacterID}  Data: ".strlen($this->_data)." bytes\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        $writer->putUI32LE($this->_reserved);
        $writer->putData($this->_data);
        return $writer->output();
    }
}
