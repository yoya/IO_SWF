<?php

/*
 * StartSound (15):  SoundId UI16, SoundInfo SOUNDINFO
 * StartSound2 (89): SoundClassName STRING, SoundInfo SOUNDINFO
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';
require_once dirname(__FILE__).'/../Type/SOUNDINFO.php';

class IO_SWF_Tag_StartSound extends IO_SWF_Tag_Base {
    var $_soundId = null;        // StartSound
    var $_soundClassName = null; // StartSound2
    var $_soundInfo;
    var $_rest = null;           // 想定外の後続データ (そのまま保持)

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        if ($tagCode == 89) { // StartSound2
            $this->_soundClassName = IO_SWF_Type_String::parse($reader);
        } else {
            $this->_soundId = $reader->getUI16LE();
        }
        $this->_soundInfo = IO_SWF_Type_SOUNDINFO::parse($reader);
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        if ($tagCode == 89) {
            echo "\tSoundClassName: {$this->_soundClassName}\n";
        } else {
            echo "\tSoundId: {$this->_soundId}\n";
        }
        echo "\tSoundInfo: ".IO_SWF_Type_SOUNDINFO::string($this->_soundInfo)."\n";
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode == 89) {
            IO_SWF_Type_String::build($writer, $this->_soundClassName);
        } else {
            $writer->putUI16LE($this->_soundId);
        }
        IO_SWF_Type_SOUNDINFO::build($writer, $this->_soundInfo);
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
