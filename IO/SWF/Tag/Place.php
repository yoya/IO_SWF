<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Tag.php';
require_once dirname(__FILE__).'/../Type/String.php';
require_once dirname(__FILE__).'/../Type/CXFORM.php';
require_once dirname(__FILE__).'/../Type/CXFORMWITHALPHA.php';
require_once dirname(__FILE__).'/../Type/CLIPACTIONS.php';
require_once dirname(__FILE__).'/../Type/FILTERLIST.php';

class IO_SWF_Tag_Place extends IO_SWF_Tag_Base {
    var $_depth = null;
    var $_className = null;
    var $_characterId = null; // refid
    var $_matrix = null;
    var $_colorTransform = null;
    var $_ratio = null;
    var $_name = null;
    var $_clipDepth = null;
    var $_surfaceFilterList = null;
    var $_blendMode = null;
    var $_bitmapCache = null;
    var $_clipActions = null;
    //
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
          case 70: // PlaceObject3
            // placeFlag
            $placeFlags = $reader->getUI8();
            $this->_placeFlags = $placeFlags;
            $this->_placeFlagHasClipActions    = ($placeFlags >> 7) & 1;
            $this->_placeFlagHasClipDepth      = ($placeFlags >> 6) & 1;
            $this->_placeFlagHasName           = ($placeFlags >> 5) & 1;
            $this->_placeFlagHasRatio          = ($placeFlags >> 4) & 1;
            $this->_placeFlagHasColorTransform = ($placeFlags >> 3) & 1;
            $this->_placeFlagHasMatrix         = ($placeFlags >> 2) & 1;
            $this->_placeFlagHasCharacter      = ($placeFlags >> 1) & 1;
            $this->_placeFlagMove              =  $placeFlags       & 1;
            if ($tagCode >= 70) { // PlaceObject3
                $placeFlags2 = $reader->getUI8();
                $this->_placeFlags2 = $placeFlags2;
                $this->_placeFlagReserved         = ($placeFlags2 >> 5) & 1;
                $this->_placeFlagHasImage         = ($placeFlags2 >> 4) & 1;
                $this->_placeFlagHasClassName     = ($placeFlags2 >> 3) & 1;
                $this->_placeFlagHasCacheAsBitmap = ($placeFlags2 >> 2) & 1;
                $this->_placeFlagHasBlendMode     = ($placeFlags2 >> 1) & 1;
                $this->_placeFlagHasFilterList    =  $placeFlags2       & 1;
            }
            // 
            $this->_depth = $reader->getUI16LE();
            if ($tagCode >= 70) {
                if (($this->_placeFlagHasClassName) ||
                    ($this->_placeFlagHasImage && $this->_placeFlagHasCharacter)) {
                    $this->_className = IO_SWF_Type_String::parse($reader);
                }
            }
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
                $this->_ratio = $reader->getUI16LE();
            }
            if ($this->_placeFlagHasName) {
                $this->_name = IO_SWF_Type_String::parse($reader);
            }
            if ($this->_placeFlagHasClipDepth) {
                $this->_clipDepth = $reader->getUI16LE();
            }
            if ($tagCode >= 70)  {
                if ($this->_placeFlagHasFilterList) {
                    $this->_surfaceFilterList = IO_SWF_Type_FILTERLIST::parse($reader);
                }
                if ($this->_placeFlagHasBlendMode) {
                    $this->_blendMode = $reader->getUI8();
                }
                if ($this->_placeFlagHasCacheAsBitmap) {
                    $this->_bitmapCache = $reader->getUI8();
                }
            }
            if ($this->_placeFlagHasClipActions) {
                $this->_clipActions = IO_SWF_Type_CLIPACTIONS::parse($reader, $opts);
            }
            break;
        }
        return true;
    }
    
    function dumpContent($tagCode, $opts = array()) {
        printf("    Flags: %02X", $this->_placeFlags);
        if ($tagCode >= 70)  {
            printf(" %02X", $this->_placeFlags2);
        }
        echo "  Move: ".($this->_placeFlagMove?'true':'false')."\n";
        if (is_null($this->_depth) === false) {
            echo "    Depth: ".$this->_depth."\n";
        }
        if (is_null($this->_className) === false) {
            echo "    ClassName: ".$this->_className."\n";
        }
        if (is_null($this->_characterId) === false) {
            echo "    CharacterId: ".$this->_characterId."\n";
        }
        if (is_null($this->_matrix) === false) {
            $opts['indent'] = 2;
            echo "    Matrix:\n".IO_SWF_Type_MATRIX::string($this->_matrix, $opts)."\n";
        }
        if (is_null($this->_colorTransform) === false) {
            if ($tagCode == 4) { // PlaceObject
                echo "    ColorTransform: ".IO_SWF_Type_CXFORM::string($this->_colorTransform)."\n";
            } else {
                echo "    ColorTransform: ".IO_SWF_Type_CXFORMWITHALPHA::string($this->_colorTransform)."\n";
            }
        }
        if (is_null($this->_ratio) === false) {
            echo "    Ratio: ".$this->_ratio."\n";
        }

        if (is_null($this->_name) === false) {
            echo "    Name: ".$this->_name."\n";
        }
        if (is_null($this->_clipDepth) === false) {
            echo "    ClipDepth: ".$this->_clipDepth."\n";
        }
        if (is_null($this->_surfaceFilterList) === false) {
            echo "    SurfaceFilterList: ".IO_SWF_Type_FILTERLIST::string($this->_surfaceFilterList)."\n";
        }
        if (is_null($this->_blendMode) === false) {
            if ($this->_blendMode < 15) {
                $blendModeText = [
                    0 => 'normal',     1 => 'normal',
                    2 => 'layer',
                    3 => 'multipy',
                    4 => 'screen',
                    5 => 'lighten',    6 => 'darlken',
                    7 => 'difference',
                    8 => 'add',        9 => 'subtract',
                    10 => 'invert',
                    11 => 'alpha',
                    12 => 'erase',
                    13 => 'overlay',
                    14 => 'hardlight',
                ][$this->_blendMode];
            } else {
                $blendModeText = "reserved";
            }
            echo "    BlendMode: ".$this->_blendMode." ($blendModeText)\n";
        }
        if (is_null($this->_bitmapCache) === false) {
            echo "    BitmapCache: ".$this->_bitmapCache."\n";
        }
        if (is_null($this->_clipActions) === false) {
            echo "    ClipActions:\n";
            echo "    ".IO_SWF_Type_CLIPACTIONS::string($this->_clipActions, $opts)."\n";
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
          case 70: // PlaceObject3
            // placeFlags
            $this->_placeFlagHasCharacter      = is_null($this->_characterId)? 0: 1;
            $this->_placeFlagHasMatrix         = is_null($this->_matrix)? 0: 1;
            $this->_placeFlagHasColorTransform = is_null($this->_colorTransform)? 0: 1;
            $this->_placeFlagHasRatio          = is_null($this->_ratio)? 0: 1;
            $this->_placeFlagHasName           = is_null($this->_name)?0: 1;
            $this->_placeFlagHasClipDepth      = is_null($this->_clipDepth)? 0: 1;
            $this->_placeFlagHasClipActions    = is_null($this->_clipActions)? 0: 1;
            // placeFlag
            $writer->putUIBit($this->_placeFlagHasClipActions);
            $writer->putUIBit($this->_placeFlagHasClipDepth);
            $writer->putUIBit($this->_placeFlagHasName);
            $writer->putUIBit($this->_placeFlagHasRatio);
            $writer->putUIBit($this->_placeFlagHasColorTransform);
            $writer->putUIBit($this->_placeFlagHasMatrix);
            $writer->putUIBit($this->_placeFlagHasCharacter);
            $writer->putUIBit($this->_placeFlagMove);
            if ($tagCode >= 70) { // PlaceObject3
                // placeFlags2
                $this->_placeFlagHasFilterList    = is_null($this->_surfaceFilterList)? 0: 1;
                $this->_placeFlagHasBlendMode     = is_null($this->_blendMode)? 0: 1;
                $this->_placeFlagHasCacheAsBitmap = is_null($this->_bitmapCache)? 0: 1;
                $this->_placeFlagHasClassName     = is_null($this->_className)? 0: 1;
                $this->_placeFlagHasImage         = ($this->_placeFlagHasImage)? 1: 0;
                // placeFlag2
                $writer->putUIBits(0, 3);
                $writer->putUIBit($this->_placeFlagHasImage);
                $writer->putUIBit($this->_placeFlagHasClassName);
                $writer->putUIBit($this->_placeFlagHasCacheAsBitmap);
                $writer->putUIBit($this->_placeFlagHasBlendMode);
                $writer->putUIBit($this->_placeFlagHasFilterList);
            }
            // 
            $writer->putUI16LE($this->_depth);
            if (($tagCode >= 70) && ($this->_placeFlagHasClassName)) {
                IO_SWF_Type_String::build($writer, $this->_className);
            }
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
            if ($tagCode >= 70)  {
                if ($this->_placeFlagHasFilterList) {
                    IO_SWF_Type_FILTERLIST::build($writer, $this->_surfaceFilterList);
                }
                if ($this->_placeFlagHasBlendMode) {
                    $writer->putUI8($this->_blendMode);
                }
                if ($this->_placeFlagHasCacheAsBitmap) {
                    $writer->putUI8($his->_bitmapCache);
                }
            }
            if ($this->_placeFlagHasClipActions) {
                IO_SWF_Type_CLIPACTIONS::build($writer, $this->_clipActions, $opts);
            }
            break;
        }
    	return $writer->output();
    }
}
