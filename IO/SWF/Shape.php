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

	$reader->byteAlign();
	$numFillBits = $reader->getUIBits(4);
	$numLineBits = $reader->getUIBits(4);
	$currentDrawingPositionX = 0;
	$currentDrawingPositionY = 0;
	$done = false;
	while ($done === false) {
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
		    $stateNewStyles = $reader->getUIBit();
		    $stateLineStyle = $reader->getUIBit();
		    $stateFillStyle1 = $reader->getUIBit();
		    $stateFillStyle0 = $reader->getUIBit();
//		    $shapeRecord['(StateNewStyles)'] = $stateNewStyles;
//		    $shapeRecord['(StateLineStyle)'] = $stateLineStyle;
//		    $shapeRecord['(StateFillStyle1)'] = $stateFillStyle1;
//		    $shapeRecord['(StateFillStyle0)'] = $stateFillStyle0;
		    //
		    $stateMoveTo = $reader->getUIBit();
//		    $shapeRecord['(StateMoveTo)'] = $stateMoveTo;
		    if ($stateMoveTo) {
		        $moveBits = $reader->getUIBits(5);
//		    	$shapeRecord['(MoveBits)'] = $moveBits;
			$moveDeltaX = $reader->getUIBits($moveBits);
			$moveDeltaY = $reader->getUIBits($moveBits);
//			$currentDrawingPositionX += $moveDeltaX;
//			$currentDrawingPositionY += $moveDeltaY;
			$currentDrawingPositionX = $moveDeltaX;
			$currentDrawingPositionY = $moveDeltaY;
//			$shapeRecord['(MoveDeltaX)'] = $moveDeltaX;
//			$shapeRecord['(MoveDeltaY)'] = $moveDeltaY;
			$shapeRecord['MoveX'] = $currentDrawingPositionX;
			$shapeRecord['MoveY'] = $currentDrawingPositionY;

		    }
		    if ($stateFillStyle0) {
		        $shapeRecord['FillStyle0'] = $reader->getUIBits($numFillBits);
		    }
		    if ($stateFillStyle1) {
		        $shapeRecord['FillStyle1'] = $reader->getUIBits($numFillBits);
		    }
		    if ($stateLineStyle) {
		        $shapeRecord['LineStyle'] = $reader->getUIBits($numLineBits);
		    }
		    if ($stateNewStyles) {
		    	$this->_parseFILLSTYLEARRAY($reader);
			$this->_parseLINESTYLEARRAY($reader);

			$reader->byteAlign();
			$numFillBits = $reader->getUIBits(4);
			$numLineBits = $reader->getUIBits(4);
		    }
		}
	    } else { // Edge
	        $shapeRecord['StraightFlag'] = $reader->getUIBit();
		if ($shapeRecord['StraightFlag']) { // Straight Edge
		    $numBits = $reader->getUIBits(4);
//	            $shapeRecord['(NumBits)'] = $numBits;
		    $generalLineFlag = $reader->getUIBit();
//	            $shapeRecord['(GeneralLineFlag)'] = $generalLineFlag;
		    if ($generalLineFlag == 0) {
		       $vertLineFlag = $reader->getUIBit();
//		       $shapeRecord['(VertLineFlag)'] = $vertLineFlag;
		    }
		    if ($generalLineFlag || ($vertLineFlag == 0)) {
		       $deltaX = $reader->getUIBits($numBits + 2);
//		       $shapeRecord['(DeltaX)'] = $deltaX;
       		       $currentDrawingPositionX += $deltaX;
		    }
		    if ($generalLineFlag || $vertLineFlag) {
       		       $deltaY = $reader->getUIBits($numBits + 2);
//     		       $shapeRecord['(DeltaY)'] = $deltaY;
		       $currentDrawingPositionY += $deltaY;
		    }
		    $shapeRecord['X'] = $currentDrawingPositionX;
		    $shapeRecord['Y'] = $currentDrawingPositionY;
		} else { // Curved Edge
		    $numBits = $reader->getUIBits(4);
//    	            $shapeRecord['(NumBits)'] = $numBits;
		    $controlDeltaX = $reader->getUIBits($numBits + 2);
		    $controlDeltaY = $reader->getUIBits($numBits + 2);
//		    $shapeRecord['(ControlDeltaX)'] = $controlDeltaX;
//		    $shapeRecord['(ControlDeltaY)'] = $controlDeltaY;
		    $currentDrawingPositionX += $controlDeltaX;
		    $currentDrawingPositionY += $controlDeltaY;
		    $shapeRecord['ControlX'] = $currentDrawingPositionX;
		    $shapeRecord['ControlY'] = $currentDrawingPositionY;
		    $anchorDeltaX = $reader->getUIBits($numBits + 2);
		    $anchorDeltaY = $reader->getUIBits($numBits + 2);
//		    $shapeRecord['(AnchorDeltaX)'] = $anchorDeltaX;
//		    $shapeRecord['(AnchorDeltaY)'] = $anchorDeltaY;
		    $currentDrawingPositionX += $anchorDeltaX;
		    $currentDrawingPositionY += $anchorDeltaY;
		    $shapeRecord['AnchorX'] = $currentDrawingPositionX;
		    $shapeRecord['AnchorY'] = $currentDrawingPositionY;
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
	        $fillStyle['SpreadMode'] = $reader->getUIBits(2);
	        $fillStyle['InterpolationMode'] = $reader->getUIBits(2);
	   	$numGradients = $reader->getUIBits(4);
	   	$fillStyle['NumGradients'] = $numGradients;
	        $fillStyle['GradientRecords'] = array();
	        for ($i = 0 ; $i < $numGradients ; $i++) {
	            $gradientRecord = array();
		    $gradientRecord['Ratio'] = $reader->getUI8();
		    if ($tagCode < 32 ) { // 32:DefineShape3
		        $gradientRecord['Color'] = IO_SWF_Type::parseRGB($reader);
		    } else {
		        $gradientRecord['Color'] = IO_SWF_Type::parseRGBA($reader);
		    }
	            $fillStyle['GradientRecords'] []= $gradientRecord;
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
    	echo "ShapeBounds:\n";
	$Xmin = $this->_shapeBounds['Xmin'] / 20;
	$Xmax = $this->_shapeBounds['Xmax'] / 20;
	$Ymin = $this->_shapeBounds['Xmin'] / 20;
	$Ymax = $this->_shapeBounds['Ymax'] / 20;
    	echo "\t($Xmin, $Ymin) - ($Xmax, $Ymax)\n";
    	echo "FillStyles:\n";
	foreach ($this->_fillStyles as $fillStyle) {
	    $fillStyleType = $fillStyle['FillStyleType'];
	    switch ($fillStyleType) {
	      case 0x00: // solid fill
	        $color = $fillStyle['Color'];
		$color_str = IO_SWF_Type::stringRGBorRGBA($color);
		echo "\tsolid fill: $color_str\n";
	        break;
	      case 0x10: // linear gradient fill
	      case 0x12: // radianar gradient fill
	      	if ($fillStyleType == 0x10) {
		    echo "\tlinear gradient fill\n";
		} else {
		    echo "\tradianar gradient fill\n";
		}
	        $spreadMode = $fillStyle['SpreadMode'];
		$interpolationMode = $fillStyle['InterpolationMode'];
		foreach ($fillStyle['GradientRecords'] as $gradientRecord) {
		    $ratio = $gradientRecords['Ratio'];
		    $color = $gradientRecords['Color'];
		    $color_str = IO_SWF_Type::stringRGBorRGBA($color);
		    echo "\t\tRatio: $radio Color:$color_str\n";
		}
      	        break;

	    }
	}
    	echo "LineStyles:\n";
	foreach ($this->_lineStyles as $lineStyle) {
	    $witdh = $lineStyle['Width'];
	    $color = $lineStyle['Color'];
	    $color_str = IO_SWF_Type::stringRGBorRGBA($color);
	    echo "\tWitdh: $width Color: $color_str\n";
	}
    	echo "ShapeRecords:\n";
    	foreach ($this->_shapeRecords as $shapeRecord) {
		$typeFlag = $shapeRecord['TypeFlag'];
		if ($typeFlag == 0) {
		   if (isset($shapeRecord['EndOfShape'])) {
		       break;
		   } else {
		       $moveX = $shapeRecord['MoveX'];
		       $moveY = $shapeRecord['MoveY'];
		       echo "\tChangeStyle: MoveTo: ($moveX, $moveY)\n";
		       $style_list = array('FillStyle0', 'FillStyle1', 'LineStyle');
		       foreach ($style_list as $style) {
		       	   if (isset($shapeRecord[$style])) {
			      echo "\t\t$style: ".$shapeRecord[$style]."\n";
			   }
		       }
		   }
		} else {
		    $straightFlag = $shapeRecord['StraightFlag'];
		    if ($straightFlag) {
		        $x = $shapeRecord['X'] / 20;
			$y = $shapeRecord['Y'] / 20;
			echo "\tStraightEdge: MoveTo: ($x, $y)\n";
		    } else {
		        $controlX = $shapeRecord['ControlX'] / 20;
		        $controlY = $shapeRecord['ControlY'] / 20;
		        $anchorX = $shapeRecord['AnchorX'] / 20;
		        $anchorY = $shapeRecord['AnchorY'] / 20;
			echo "\tCurvedEdge: MoveTo: ($anchorX, $anchorY) Control($controlX, $controlY)\n";
		    }
		}
	}
    }
    function build() {
        $tagData = '';
	return $tagData;
    }

}
