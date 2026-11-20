<?php

/*
 * DefineFont4 (91)
 *   FontID UI16
 *   FontFlagsReserved UB[5], FontFlagsHasFontData UB[1], FontFlagsItalic UB[1], FontFlagsBold UB[1]
 *   FontName STRING
 *   FontData FONTDATA (CFF/OpenType の生データ。タグの終わりまで)
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_Font4 extends IO_SWF_Tag_Base {
    var $CharacterID;   // FontID
    var $FontFlagsReserved;
    var $FontFlagsHasFontData, $FontFlagsItalic, $FontFlagsBold;
    var $FontName;
    var $FontData = '';

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->FontFlagsReserved = $reader->getUIBits(5);
        $this->FontFlagsHasFontData = $reader->getUIBit();
        $this->FontFlagsItalic = $reader->getUIBit();
        $this->FontFlagsBold = $reader->getUIBit();
        $this->FontName = IO_SWF_Type_String::parse($reader);
        $this->FontData = $reader->hasNextData(1) ? $reader->getDataUntil(false) : '';
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    FontID: {$this->CharacterID}  FontName: {$this->FontName}\n";
        echo "    FontFlags: HasFontData:{$this->FontFlagsHasFontData} Italic:{$this->FontFlagsItalic} Bold:{$this->FontFlagsBold}\n";
        echo "    FontData: ".strlen($this->FontData)." bytes\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        $writer->putUIBits($this->FontFlagsReserved, 5);
        // HasFontData は実際にデータがあるかどうかで決める
        $writer->putUIBit((strlen($this->FontData) > 0) ? 1 : $this->FontFlagsHasFontData);
        $writer->putUIBit($this->FontFlagsItalic);
        $writer->putUIBit($this->FontFlagsBold);
        IO_SWF_Type_String::build($writer, $this->FontName);
        $writer->putData($this->FontData);
        return $writer->output();
    }
}
