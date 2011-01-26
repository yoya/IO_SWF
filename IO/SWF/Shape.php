<?php

require_once 'IO/Bit.php';

class IO_SWF_Shape {
    var $_shapeId;
    var $_shapeBounds;
    var $_fillStyles = array(), $_lineStyles = array();
    var $_shapeRecords = array();
    function parse($tagCode, $content) {
        $reader = new IO_Bit();
	$reader->input($content);
        $this->_shapeId = $reader->getUI16LE();
    	$this->_shapeBounds = IO_SWF_Type::parseRECT($reader);
	// FillStyle
	$fillStyleCount = $reader->getUI8();
	if (($tagCode > 2) && ($fillStyleCount == 0xff)) {
	   // DefineShape2 以降は 0xffff サイズまで扱える
	   $fillStyleCount = $reader->getUI16LE();
	}
	for ($i = 0 ; $i < $fillStyleCount ; $i++) {
	    $fillStyle = array();
	    $fillStyleType = $reader->getUI8();
	    switch ($fillStyleType) {
	      case 0x00: // solid fill
	      	$fillStyle['FillStyleType'] = $fillStyleType;
		if ($tagCode < 32 ) { // 32:DefineShape3
		    $fillStyle['Color'] = IO_SWF_Type::parseRGB($reader);
		} else {
		    $fillStyle['Color'] = IO_SWF_Type::parseRGBA($reader);
		}
	      	break;
	      case 0x10: // linear gradient fill
	      case 0x12: // radianar gradient fill
	      case 0x12: // radianar gradient fill
     // TODO
	      // case 0x13: // focal gradient fill // 8 and later
	      case 0x40: // repeating bitmap fill
	      case 0x41: // clipped bitmap fill
	      case 0x42: // non-smoothed repeating bitmap fill
	      case 0x43: // non-smoothed clipped bitmap fill
	        $fillStyle['BitmapId'] = $reader->getUI16LE();
	        $fillStyle['BitmapMatrix'] = IO_SWF_Type::parseMATRIX($reader);
	        break;
	      default:
	        break 2; // XXX
	    }
	    $this->_fillStyles[] = $fillStyle;
	}
	// LineStyle
	$lineStyleCount = $reader->getUI8();
	if (($tagCode > 2) && ($lineStyleCount == 0xff)) {
	   // DefineShape2 以降は 0xffff サイズまで扱える
	   $lineStyleCount = $reader->getUI16LE();
	}
	for ($i = 0 ; $i < $lineStyleCount ; $i++) {
	    $lineStyle = array();
	    $lineStyle['Width'] = $reader->getUI16LE();
	    if ($tagCode < 32 ) { // 32:DefineShape3
	        $lineStyle['Color'] = IO_SWF_Type::parseRGB($reader);
	    } else {
	        $lineStyle['Color'] = IO_SWF_Type::parseRGBA($reader);
	    }
	    $this->_lineStyles[] = $lineStyle;
        }
	$numfillBits = 0;
	$numLineBits = 0;

    }
    function dump() {
    	echo "ShapeId: {$this->_shapeId}\n";
    	echo "ShapeBounds: ";
    	print_r($this->_shapeBounds);
    	echo "Shapes: ";
    	echo "FillStyles: ";
    	print_r($this->_fillStyles);
    	echo "LineStyles: ";
    	print_r($this->_lineStyles);
    	echo "ShapeRecords: ";
    	print_r($this->_shapeRecords);
    }
    function build() {
        $tagData = '';
	return $tagData;
    }

}
