<?php

  /*
   * 2011/8/22- (c) yoya@awm.jp
   */


require_once 'IO/Bit.php';

require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/RECT.php';
require_once dirname(__FILE__).'/../Type/MATRIX.php';
require_once dirname(__FILE__).'/../Type/TEXTRECORD.php';

class IO_SWF_Tag_Text extends IO_SWF_Tag_Base {
    var $_CharacterID;
    var $_TextBounds;
    var $_TextMatrix;
    var $_GlyphBits;
    var $_AdvanceBits;
    function parseContent($tagCode, $content, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_CharacterID = $reader->getUI16LE();
        $this->_TextBounds = IO_SWF_TYPE_RECT::parse($reader);
        $this->_TextMatrix = IO_SWF_Type_MATRIX::parse($reader);
        $glyphBits = $reader->getUI8();
        $advanceBits = $reader->getUI8();
        $this->_GlyphBits = $glyphBits;
        $this->_AdvanceBits = $advanceBits;
        $textRecords = array();
        $opts['GlyphBits'] = $glyphBits;
        $opts['AdvanceBits'] = $advanceBits;
        while ($reader->getUI8() != 0) {
            $reader->incrementOffset(-1, 0); // 1 byte back
            $textRecords []= IO_SWF_Type_TEXTRECORD::parse($reader, $opts);
        }
        $this->_TextRecords = $textRecords;
    }

    function dumpContent($tagCode, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        echo "\tCharacterID:{$this->_CharacterID}\n";
        echo "\tTextBounds:\n";
        $rect_str = IO_SWF_Type_RECT::string($this->_TextBounds, $opts);
        echo "\t$rect_str\n";
        echo "";
        $opts['indent'] = 2;
        $matrix_str = IO_SWF_Type_MATRIX::string($this->_TextMatrix, $opts);
        echo "\tTextMatrix:\n";
        echo "$matrix_str\n";
        echo "\tGlyphBits:{$this->_GlyphBits} AdvanceBits:{$this->_AdvanceBits}\n";
        if (count($this->_TextRecords) == 0) {
            echo "\t(TEXTRECORD empty)\n";
        } else {
            foreach ($this->_TextRecords as $textRecord) {
                echo "\t".IO_SWF_Type_TEXTRECORD::string($textRecord, $opts);
            }
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_CharacterID);
        IO_SWF_TYPE_RECT::build($writer, $this->_TextBounds);
        IO_SWF_Type_MATRIX::build($writer, $this->_TextMatrix);

    	return $writer->output();
    }
}
