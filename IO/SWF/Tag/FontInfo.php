<?php

/*
 * DefineFontInfo (13) / DefineFontInfo2 (62)
 *   FontID UI16
 *   FontNameLen UI8, FontName UI8[FontNameLen]
 *   FontFlagsReserved UB[2], FontFlagsSmallText UB[1], FontFlagsShiftJIS UB[1],
 *   FontFlagsANSI UB[1], FontFlagsItalic UB[1], FontFlagsBold UB[1], FontFlagsWideCodes UB[1]
 *   LanguageCode LANGCODE  (DefineFontInfo2 のみ)
 *   CodeTable: WideCodes なら UI16[]、そうでなければ UI8[] (タグの終わりまで)
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/LANGCODE.php';

class IO_SWF_Tag_FontInfo extends IO_SWF_Tag_Base {
    var $CharacterID;   // FontID
    var $FontName;      // 生のバイト列 (長さ付き。NUL 終端とは限らない)
    var $FontFlagsReserved;
    var $FontFlagsSmallText, $FontFlagsShiftJIS, $FontFlagsANSI;
    var $FontFlagsItalic, $FontFlagsBold, $FontFlagsWideCodes;
    var $LanguageCode = null; // DefineFontInfo2
    var $CodeTable = array();
    var $_rest = null;        // WideCodes で奇数バイト余った場合など

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $fontNameLen = $reader->getUI8();
        $this->FontName = $reader->getData($fontNameLen);
        $this->FontFlagsReserved = $reader->getUIBits(2);
        $this->FontFlagsSmallText = $reader->getUIBit();
        $this->FontFlagsShiftJIS = $reader->getUIBit();
        $this->FontFlagsANSI = $reader->getUIBit();
        $this->FontFlagsItalic = $reader->getUIBit();
        $this->FontFlagsBold = $reader->getUIBit();
        $this->FontFlagsWideCodes = $reader->getUIBit();
        if ($tagCode == 62) { // DefineFontInfo2
            $this->LanguageCode = IO_SWF_Type_LANGCODE::parse($reader);
        }
        $this->CodeTable = array();
        if ($this->FontFlagsWideCodes) {
            while ($reader->hasNextData(2)) {
                $this->CodeTable []= $reader->getUI16LE();
            }
        } else {
            while ($reader->hasNextData(1)) {
                $this->CodeTable []= $reader->getUI8();
            }
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    FontID: {$this->CharacterID}  FontName: ".rtrim($this->FontName, "\0")."\n";
        echo "    FontFlags: SmallText:{$this->FontFlagsSmallText} ShiftJIS:{$this->FontFlagsShiftJIS} ANSI:{$this->FontFlagsANSI} Italic:{$this->FontFlagsItalic} Bold:{$this->FontFlagsBold} WideCodes:{$this->FontFlagsWideCodes}\n";
        if ($tagCode == 62) {
            echo "    LanguageCode: ".IO_SWF_Type_LANGCODE::string($this->LanguageCode)."\n";
        }
        echo "    CodeTable: (".count($this->CodeTable).")";
        foreach ($this->CodeTable as $code) {
            echo " $code";
        }
        echo "\n";
        if (! is_null($this->_rest)) {
            echo "    (trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        $writer->putUI8(strlen($this->FontName));
        $writer->putData($this->FontName);
        // CodeTable に 255 を超える値があれば WideCodes が必要
        $wideCodes = $this->FontFlagsWideCodes;
        foreach ($this->CodeTable as $code) {
            if ($code > 0xff) {
                $wideCodes = 1;
                break;
            }
        }
        $writer->putUIBits($this->FontFlagsReserved, 2);
        $writer->putUIBit($this->FontFlagsSmallText);
        $writer->putUIBit($this->FontFlagsShiftJIS);
        $writer->putUIBit($this->FontFlagsANSI);
        $writer->putUIBit($this->FontFlagsItalic);
        $writer->putUIBit($this->FontFlagsBold);
        $writer->putUIBit($wideCodes);
        if ($tagCode == 62) {
            IO_SWF_Type_LANGCODE::build($writer, $this->LanguageCode);
        }
        foreach ($this->CodeTable as $code) {
            if ($wideCodes) {
                $writer->putUI16LE($code);
            } else {
                $writer->putUI8($code);
            }
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
