<?php

/*
 * DefineButtonSound (17)
 *   ButtonId UI16
 *   ButtonSoundChar0 UI16 (OverUpToIdle),     ButtonSoundInfo0 SOUNDINFO (Char0 != 0)
 *   ButtonSoundChar1 UI16 (IdleToOverUp),     ButtonSoundInfo1 SOUNDINFO (Char1 != 0)
 *   ButtonSoundChar2 UI16 (OverUpToOverDown), ButtonSoundInfo2 SOUNDINFO (Char2 != 0)
 *   ButtonSoundChar3 UI16 (OverDownToOverUp), ButtonSoundInfo3 SOUNDINFO (Char3 != 0)
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/SOUNDINFO.php';

class IO_SWF_Tag_ButtonSound extends IO_SWF_Tag_Base {
    static $stateNames = array('OverUpToIdle', 'IdleToOverUp',
                               'OverUpToOverDown', 'OverDownToOverUp');
    var $CharacterID;      // ButtonId (replaceCharacterId の規約に合わせた名前)
    var $_sounds = array(); // [ ['Char' => SoundId(0=なし), 'Info' => SOUNDINFO|null], x4 ]
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->_sounds = array();
        for ($i = 0; $i < 4; $i++) {
            $char = $reader->getUI16LE();
            $info = ($char != 0) ? IO_SWF_Type_SOUNDINFO::parse($reader) : null;
            $this->_sounds []= array('Char' => $char, 'Info' => $info);
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tButtonId: {$this->CharacterID}\n";
        foreach ($this->_sounds as $i => $sound) {
            $name = self::$stateNames[$i];
            if ($sound['Char'] == 0) {
                echo "\t$name: (none)\n";
            } else {
                echo "\t$name: SoundId:{$sound['Char']} ".IO_SWF_Type_SOUNDINFO::string($sound['Info'])."\n";
            }
        }
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        for ($i = 0; $i < 4; $i++) {
            $sound = isset($this->_sounds[$i]) ? $this->_sounds[$i] : array('Char' => 0, 'Info' => null);
            $writer->putUI16LE($sound['Char']);
            if ($sound['Char'] != 0) {
                IO_SWF_Type_SOUNDINFO::build($writer, $sound['Info']);
            }
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
