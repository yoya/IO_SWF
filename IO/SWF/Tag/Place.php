<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Tag.php';
require_once dirname(__FILE__).'/../Type/CXFORM.php';
require_once dirname(__FILE__).'/../Type/CXFORMWITHALPHA.php';

class IO_SWF_Tag_Place extends IO_SWF_Tag_Base {
    var $_characterId = null; // refid
    var $_depth = null;
    var $_matrix = null;
    var $_colorTransform = null;
    var $_ratio = null;
    var $_name = null;
    var $_clipDepth = null;
    var $_clipActions = null;
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        switch ($tagCode) {
          case 4: // PlaceObject
            $this->_characterId = $reader->getUI16LE();
            $this->_depth = $reader->getUI16LE();
            $this->_matrix = IO_SWF_Type_MATRIX::parse($reader);
            if ($reader->hasNextData()) { // optional
                $this->_colorTransform = IO_SWF_Type_CXFORM::parse($reader);
            }
            break;
          case 26: // PlaceObject2
            // placeFlag
            $this->_placeFlagHasClipActions = $reader->getUIBit();
            $this->_placeFlagHasClipDepth = $reader->getUIBit();
            $this->_placeFlagHasName = $reader->getUIBit();
            $this->_placeFlagHasRatio = $reader->getUIBit();
            $this->_placeFlagHasColorTransform = $reader->getUIBit();
            $this->_placeFlagHasMatrix = $reader->getUIBit();
            $this->_placeFlagHasCharacter = $reader->getUIBit();
            $this->_placeFlagMove = $reader->getUIBit();
            // 
            $this->_depth = $reader->getUI16LE();
            if ($this->_placeFlagHasCharacter) {
                $this->_characterId = $reader->getUI16LE();
            }
            if ($this->_placeFlagHasMatrix) {
                $this->_matrix = IO_SWF_Type_MATRIX::parse($reader);
            }
            if ($this->_placeFlagHasColorTransform) {
                $this->_colorTransform = IO_SWF_Type_CXFORMWITHALPHA::parse($reader);
            }
            if ($this->_placeFlagHasRatio) {
                $this->_ratio =  $reader->getUI16LE();
            }
            if ($this->_placeFlagHasName) {
                $this->_name = IO_SWF_Type_String::parse($reader);
            }
            if ($this->_placeFlagHasClipDepth) {
                $this->_clipDepth =  $reader->getUI16LE();
            }
            if ($this->_placeFlagHasClipActions) {
                $this->_clipActions = IO_SWF_Type_CLIPACTIONS::parse($reader);
            }
            break;
        }
        return true;
    }
    
    function dumpContent($tagCode, $opts = array()) {
        if (is_null($this->_characterId) === false) {
            echo "\tCharacterId: ".$this->_characterId."\n";
        }
        if (is_null($this->_depth) === false) {
            echo "\tDepth: ".$this->_depth."\n";
        }
        if (is_null($this->_matrix) === false) {
            $opts = array('indent' => 2);
            echo "\tMatrix:\n".IO_SWF_Type_MATRIX::string($this->_matrix, $opts)."\n";
        }
        if (is_null($this->_colorTransform) === false) {
            if ($tagCode == 4) { // PlaceObject
                echo "\tColorTransform: ".IO_SWF_Type_CXFORM::string($this->_colorTransform)."\n";
            } else {
                echo "\tColorTransform: ".IO_SWF_Type_CXFORMWITHALPHA::string($this->_colorTransform)."\n";
            }
        }
        if (is_null($this->_ratio) === false) {
            echo "\tRatio: ".$this->_ratio."\n";
        }
        if (is_null($this->_name) === false) {
            echo "\tName:".$this->_name."\n";
        }
        if (is_null($this->_clipDepth) === false) {
            echo "\tClipDepth:".$this->_clipDepth."\n";
        }
        if (is_null($this->_clipActions) === false) {
            echo "\tClipActions:".IO_SWF_Type_CLIPACTIONS::string($this->_clipActions)."\n";
        }
    }
    
    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        switch ($tagCode) {
          case 4: // PlaceObject
            $this->_characterId = $writer->getUI16LE();
            $this->_depth = $writer->getUI16LE();
            $this->_matrix = IO_SWF_Type_MATRIX::parse($writer);
            if ($writer->hasNextData()) { // optional
                $this->_colorTransform = IO_SWF_Type_CXFORM::parse($writer);
            }
            break;
          case 26: // PlaceObject2
            //
            if (is_null($this->_characterId) === false) {
                $this->_placeFlagHasCharacter = 1;
            } else {
                $this->_placeFlagHasCharacter = 0;
            }
            if (is_null($this->_matrix) === false) {
                $this->_placeFlagHasMatrix = 1;
            } else {
                $this->_placeFlagHasMatrix = 0;
            }
            if (is_null($this->_colorTransform) === false) {
                $this->_placeFlagHasColorTransform = 1;
            } else {
                $this->_placeFlagHasColorTransform = 0;
            }
            if (is_null($this->_ratio) === false) {
                $this->_placeFlagHasRatio = 1;
            } else {
                $this->_placeFlagHasRatio = 0;
            }
            if (is_null($this->_name) === false) {
                $this->_placeFlagHasName = 1;
            } else {
                $this->_placeFlagHasName = 0;
            }
            if (is_null($this->_clipDepth) === false) {
                $this->_placeFlagHasClipDepth = 1;
            } else {
                $this->_placeFlagHasClipDepth = 0;
            }
            if (is_null($this->_clipActions) === false) {
                $this->_placeFlagHasClipActions = 1;
            } else {
                $this->_placeFlagHasClipActions = 0;
            }
            // placeFlag
            $writer->putUIBit($this->_placeFlagHasClipActions);
            $writer->putUIBit($this->_placeFlagHasClipDepth);
            $writer->putUIBit($this->_placeFlagHasName);
            $writer->putUIBit($this->_placeFlagHasRatio);
            $writer->putUIBit($this->_placeFlagHasColorTransform);
            $writer->putUIBit($this->_placeFlagHasMatrix);
            $writer->putUIBit($this->_placeFlagHasCharacter);
            $writer->putUIBit($this->_placeFlagMove);
            // 
            $writer->putUI16LE($this->_depth);
            if ($this->_placeFlagHasCharacter) {
                $writer->putUI16LE($this->_characterId);
            }
            if ($this->_placeFlagHasMatrix) {
                IO_SWF_Type_MATRIX::build($writer, $this->_matrix);
            }
            if ($this->_placeFlagHasColorTransform) {
                IO_SWF_Type_CXFORMWITHALPHA::build($writer, $this->_colorTransform);
            }
            if ($this->_placeFlagHasRatio) {
                $writer->putUI16LE($this->_ratio);
            }
            if ($this->_placeFlagHasName) {
                IO_SWF_Type_String::build($writer, $this->_name);
            }
            if ($this->_placeFlagHasClipDepth) {
                $writer->putUI16LE($this->_clipDepth);
            }
            if ($this->_placeFlagHasClipActions) {
                IO_SWF_Type_CLIPACTIONS::build($writer, $this->_clipActions);
            }
            break;
        }
    	return $writer->output();
    }
}
