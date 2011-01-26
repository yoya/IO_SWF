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

	$this->_parseFILLSTYLEARRAY($reader);
	$this->_parseLINESTYLEARRAY($reader);

	$numFillBits = $reader->getUIBits(4);
	$numLineBits = $reader->getUIBits(4);
	$done = false;
	while ($done) {
	    $shapeRecord = array();
	    $typeFlag = $reader->getUIBit();
	    $shapeRecord['TypeFlag'] = $typeFlag;
	    if ($typeFlag == 0) {
	        $endOfShape = $reader->getUIBits(5); // XXX not 4 ?
		if ($endOfShape == 0) {
		    $shapeRecord['EndOfShape'] = $endOfShape;
		    $done = true;
		} else {
		    $reader->incrementOffset(0, -5); // XXX not 4 ?
		    $shapeRecord['StateNewStyles'] = $reader->getUIBit();
		    $shapeRecord['StateLineStyle'] = $reader->getUIBit();
		    $shapeRecord['StateFillStyle1'] = $reader->getUIBit();
		    $shapeRecord['StateFillStyle0'] = $reader->getUIBit();
		    //
		    $shapeRecord['StateMoveTo'] = $reader->getUIBit();
		    if ($shapeRecord['StateMoveTo']) {
		        $moveBits = $reader->getUIBits(5);
		    	$shapeRecord['(MoveBits)'] = $moveBits;
			$shapeRecord['MoveDeltaDeltaX'] = $reader->getUIBits($moveBits);
			$shapeRecord['MoveDeltaDeltaY'] = $reader->getUIBits($moveBits);
		    }
		    if ($shapeRecord['StateFillStyle0']) {
		        $shapeRecord['FillStyle0'] = $reader->getUIBits($numFillBits);
		    }
		    if ($shapeRecord['StateFillStyle1']) {
		        $shapeRecord['FillStyle1'] = $reader->getUIBits($numFillBits);
		    }
		    if ($shapeRecord['StateLineStyle']) {
		        $shapeRecord['LineStyle'] = $reader->getUIBits($numLineBits);
		    }
		    if ($shapeRecord['StateNewStyles']) {
		    	$this->_parseFILLSTYLEARRAY($reader);
			$this->_parseLINESTYLEARRAY($reader);
			$numFillBits = $reader->getUIBits(4);
			$numLineBits = $reader->getUIBits(4);
		    }
		}
	    } else { // Edge
	        $shapeRecord['StraightFlag'] = $reader->getUIBit();
		if ($shapeRecord['StraightFlag']) {
		       ;
		} else {
		       ;
		}
	    }
	    $this->_shapeRecords []= $shapeRecord;
	}

    }
    function _parseFILLSTYLEARRAY($reader) {
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
	      $fillStyle['SpreadMode'] = $reader->getUIBits(2);
	      $fillStyle['InterpolationMode'] = $reader->getUIBits(2);
	      $numGradients = $reader->getUIBits(4);
	      $fillStyle['NumGradients'] = $numGradients;
	      $fillStyle['GradientRecords'] = array();
	      for ($i = 0 ; $i < $numGradients ; $i++) {
	          $gradientRecords = array();
		  $gradientRecords['Ratio'] = $reader->getUI8();
		  if ($tagCode < 32 ) { // 32:DefineShape3
		      $gradientRecords['Color'] = IO_SWF_Type::parseRGB($reader);
		  } else {
		      $gradientRecords['Color'] = IO_SWF_Type::parseRGBA($reader);
		  }
	          $fillStyle['GradientRecords'] []= $gradientRecords;
	      }
	      break;
	      // case 0x13: // focal gradient fill // 8 and later
	      // break;
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
    }
    function _parseLINESTYLEARRAY($reader) {
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
    }
    function dump() {
    	echo "ShapeId: {$this->_shapeId}\n";
    	echo "ShapeBounds: ";
    	print_r($this->_shapeBounds);
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
