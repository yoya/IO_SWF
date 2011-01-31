<?php

require_once 'IO/Bit.php';

class IO_SWF_Shape {
    var $_shapeId = null;
    var $_shapeBounds;
    var $_fillStyles = array(), $_lineStyles = array();
    var $_shapeRecords = array();
    function parse($tagCode, $content, $opts) {
        $reader = new IO_Bit();
	$reader->input($content);
	if (isset($opts['hasShapeId']) && $opts['hasShapeId']) {
	        $this->_shapeId = $reader->getUI16LE();
	}
	// 描画枠
    	$this->_shapeBounds = IO_SWF_Type::parseRECT($reader);

	// 描画スタイル
	$this->_parseFILLSTYLEARRAY($tagCode, $reader);
	$this->_parseLINESTYLEARRAY($tagCode, $reader);

	$reader->byteAlign();
	// 描画スタイルを参照するインデックスのビット幅
	$numFillBits = $reader->getUIBits(4);
	$numLineBits = $reader->getUIBits(4);

	$currentDrawingPositionX = 0;
	$currentDrawingPositionY = 0;
	$currentFillStyle0 = 0;
	$currentFillStyle1 = 0;
	$currentLineStyle = 0;
	$done = false;
	// ShapeRecords
	while ($done === false) {
	    $shapeRecord = array();
	    $typeFlag = $reader->getUIBit();
	    $shapeRecord['TypeFlag'] = $typeFlag;
	    if ($typeFlag == 0) {
	        $endOfShape = $reader->getUIBits(5); // XXX not 4 ?
		if ($endOfShape == 0) {
		    // EndShapeRecord
		    $shapeRecord['EndOfShape'] = $endOfShape;
		    $done = true;
		} else {
		    // StyleChangeRecord
		    $reader->incrementOffset(0, -5); // XXX not 4 ?
		    $stateNewStyles = $reader->getUIBit();
		    $stateLineStyle = $reader->getUIBit();
		    $stateFillStyle1 = $reader->getUIBit();
		    $stateFillStyle0 = $reader->getUIBit();

		    $stateMoveTo = $reader->getUIBit();
		    if ($stateMoveTo) {
		        $moveBits = $reader->getUIBits(5);
//		    	$shapeRecord['(MoveBits)'] = $moveBits;
			$moveDeltaX = $reader->getSIBits($moveBits);
			$moveDeltaY = $reader->getSIBits($moveBits);
//			$currentDrawingPositionX += $moveDeltaX;
//			$currentDrawingPositionY += $moveDeltaY;
			$currentDrawingPositionX = $moveDeltaX;
			$currentDrawingPositionY = $moveDeltaY;
			$shapeRecord['MoveX'] = $currentDrawingPositionX;
			$shapeRecord['MoveY'] = $currentDrawingPositionY;

		    }
		    if ($stateFillStyle0) {
		    	$currentFillStyle0 = $reader->getUIBits($numFillBits);
		    }
		    if ($stateFillStyle1) {
			$currentFillStyle1 = $reader->getUIBits($numFillBits);
		    }
		    if ($stateLineStyle) {
		    	$currentLineStyle = $reader->getUIBits($numLineBits);
		    }
		    $shapeRecord['FillStyle0'] = $currentFillStyle0;
		    $shapeRecord['FillStyle1'] = $currentFillStyle1;
		    $shapeRecord['LineStyle']  = $currentLineStyle;
		    if ($stateNewStyles) {
		    	$this->_parseFILLSTYLEARRAY($tagCode, $reader);
			$this->_parseLINESTYLEARRAY($tagCode, $reader);

			$reader->byteAlign();
			$numFillBits = $reader->getUIBits(4);
			$numLineBits = $reader->getUIBits(4);
		    }
		}
	    } else { // Edge records
	        $shapeRecord['StraightFlag'] = $reader->getUIBit();
		if ($shapeRecord['StraightFlag']) {
		    // StraightEdgeRecord
		    $numBits = $reader->getUIBits(4);
//	            $shapeRecord['(NumBits)'] = $numBits;
		    $generalLineFlag = $reader->getUIBit();
		    if ($generalLineFlag == 0) {
		       $vertLineFlag = $reader->getUIBit();
		    }
		    if ($generalLineFlag || ($vertLineFlag == 0)) {
		       $deltaX = $reader->getSIBits($numBits + 2);
       		       $currentDrawingPositionX += $deltaX;
		    }
		    if ($generalLineFlag || $vertLineFlag) {
       		       $deltaY = $reader->getSIBits($numBits + 2);
		       $currentDrawingPositionY += $deltaY;
		    }
		    $shapeRecord['X'] = $currentDrawingPositionX;
		    $shapeRecord['Y'] = $currentDrawingPositionY;
		} else {
		    // CurvedEdgeRecord
		    $numBits = $reader->getUIBits(4);
//		    $shapeRecord['(NumBits)'] = $numBits;

		    $controlDeltaX = $reader->getSIBits($numBits + 2);
		    $controlDeltaY = $reader->getSIBits($numBits + 2);
		    $anchorDeltaX = $reader->getSIBits($numBits + 2);
		    $anchorDeltaY = $reader->getSIBits($numBits + 2);

		    $currentDrawingPositionX += $controlDeltaX;
		    $currentDrawingPositionY += $controlDeltaY;
		    $shapeRecord['ControlX'] = $currentDrawingPositionX;
		    $shapeRecord['ControlY'] = $currentDrawingPositionY;

		    $currentDrawingPositionX += $anchorDeltaX;
		    $currentDrawingPositionY += $anchorDeltaY;
		    $shapeRecord['AnchorX'] = $currentDrawingPositionX;
		    $shapeRecord['AnchorY'] = $currentDrawingPositionY;
		}
	    }
	    $this->_shapeRecords []= $shapeRecord;
	}

    }
    function _parseFILLSTYLEARRAY($tagCode, $reader) {
	// FillStyle
	$fillStyleCount = $reader->getUI8();
	if (($tagCode > 2) && ($fillStyleCount == 0xff)) {
	   // DefineShape2 以降は 0xffff サイズまで扱える
	   $fillStyleCount = $reader->getUI16LE();
	}
	for ($i = 0 ; $i < $fillStyleCount ; $i++) {
	    $fillStyle = array();
	    $fillStyleType = $reader->getUI8();
	    $fillStyle['FillStyleType'] = $fillStyleType;
	    switch ($fillStyleType) {
	      case 0x00: // solid fill
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
    function _parseLINESTYLEARRAY($tagCode, $reader) {
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
    	if (is_null($this->_shapeId) === false) {
	    	echo "    ShapeId: {$this->_shapeId}\n";
	}
    	echo "    ShapeBounds:";
	$Xmin = $this->_shapeBounds['Xmin'] / 20;
	$Xmax = $this->_shapeBounds['Xmax'] / 20;
	$Ymin = $this->_shapeBounds['Xmin'] / 20;
	$Ymax = $this->_shapeBounds['Ymax'] / 20;
    	echo "  ($Xmin, $Ymin) - ($Xmax, $Ymax)\n";
    	echo "    FillStyles:\n";
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
	      case 0x40: // repeating bitmap fill
	      case 0x41: // clipped bitmap fill
	      case 0x42: // non-smoothed repeating bitmap fill
	      case 0x43: // non-smoothed clipped bitmap fill
	      	   echo "\tBigmap($fillStyleType): ";
		   echo "  BitmapId: ".$fillStyle['BitmapId']."\n";
		   echo "\tBitmapMatrix:\n";
		   $matrix_str = IO_SWF_Type::stringMATRIX($fillStyle['BitmapMatrix'], 2);
		   echo $matrix_str . "\n";
      	        break;
	      default:
      	        echo "Unknown FillStyleType($fillStyleType)\n";
	    }
	}
    	echo "    LineStyles:\n";
	foreach ($this->_lineStyles as $lineStyle) {
	    $witdh = $lineStyle['Width'];
	    $color = $lineStyle['Color'];
	    $color_str = IO_SWF_Type::stringRGBorRGBA($color);
	    echo "\tWitdh: $width Color: $color_str\n";
	}
    	echo "    ShapeRecords:\n";
    	foreach ($this->_shapeRecords as $shapeRecord) {
		$typeFlag = $shapeRecord['TypeFlag'];
		if ($typeFlag == 0) {
		   if (isset($shapeRecord['EndOfShape'])) {
		       break;
		   } else {
		       $moveX = $shapeRecord['MoveX'] / 20;
		       $moveY = $shapeRecord['MoveY'] / 20;
		       echo "\tChangeStyle: MoveTo: ($moveX, $moveY)";
		       $style_list = array('FillStyle0', 'FillStyle1', 'LineStyle');
		       echo "  FillStyle: ".$shapeRecord['FillStyle0']."|".$shapeRecord['FillStyle1'];
		       echo "  LineStyle: ".$shapeRecord['LineStyle']."\n";
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
			echo "\tCurvedEdge: MoveTo: Control($controlX, $controlY) Anchor($anchorX, $anchorY)\n";
		    }
		}
	}
    }
    function build($tagCode, $opts) {
	$writer = new IO_Bit();
	if (isset($opts['hasShapeId']) && $opts['hasShapeId']) {
	    $writer->putUI16LE($this->_shapeId);
	}
	IO_SWF_Type::buildRECT($writer, $this->_shapeBounds);
	// 描画スタイル
	$fillStyleCount = $this->_buildFILLSTYLEARRAY($writer, $tagCode);
	$lineStyleCount = $this->_buildLINESTYLEARRAY($writer, $tagCode);

	if ($fillStyleCount == 0) {
	    $numFillBits = 0;
	} else {
	    $numFillBits = $writer->need_bits_unsigned($fillStyleCount);
        }
	if ($lineStyleCount == 0) {
	    $numLineBits = 0;
	} else {
	    $numLineBits = $writer->need_bits_unsigned($lineStyleCount);
	}
	$writer->putUIBits($numFillBits, 4);
	$writer->putUIBits($numLineBits, 4);
	$currentDrawingPositionX = 0;
	$currentDrawingPositionY = 0;
	$currentFillStyle0 = 0;
	$currentFillStyle1 = 0;
	$currentLineStyle = 0;
	foreach ($this->_shapeRecords as $shapeRecord) {
	    $typeFlag = $shapeRecord['TypeFlag'];
	    $writer->putUIBit($typeFlag);
	    if($typeFlag == 0) {
	        if (isset($shapeRecord['EndOfShape']) && ($shapeRecord['EndOfShape']) == 0) {
		    // EndShapeRecord
   	            $writer->putUIBits(0, 5); // XXX not 4 ?
		} else {
    		    // StyleChangeRecord
		    $stateNewStyles = 0;
		    $stateLineStyle = ($shapeRecord['LineStyle'] == $currentLineStyle)?0:1;
		    $stateFillStyle1 = ($shapeRecord['FillStyle1'] == $currentFillStyle1)?0:1;
		    $stateFillStyle0 = ($shapeRecord['FillStyle0'] == $currentFillStyle0)?0:1;
		    $writer->putUIBit($stateNewStyles);
		    $writer->putUIBit($stateLineStyle);
		    $writer->putUIBit($stateFillStyle1);
		    $writer->putUIBit($stateFillStyle0);

		    $stateMoveTo = isset($shapeRecord['MoveX'])?1:0;
		    $writer->putUIBit($stateMoveTo);
		    if ($stateMoveTo) {
		        $moveX = $shapeRecord['MoveX'];
			$moveY = $shapeRecord['MoveY'];
		    	$currentDrawingPositionX = $moveX;
			$currentDrawingPositionY = $moveY;
			$XmoveBits = $writer->need_bits_signed($moveX);
			$YmoveBits = $writer->need_bits_signed($moveY);
			$moveBits = max($XmoveBits, $YmoveBits);
			$writer->putUIBits($moveBits, 5);
			$writer->putSIBits($moveX, $moveBits);
			$writer->putSIBits($moveY, $moveBits);
		    }
		    if ($stateFillStyle0) {
	 	        $currentFillStyle0 = $shapeRecord['FillStyle0'];
		    	$writer->putUIBits($currentFillStyle0, $numFillBits);
		    }
		    if ($stateFillStyle1) {
	 	        $currentFillStyle1 = $shapeRecord['FillStyle1'];
		    	$writer->putUIBits($currentFillStyle1, $numFillBits);
		    }
		    if ($stateLineStyle) {
	 	        $currentLineStyle = $shapeRecord['LineStyle'];
		    	$writer->putUIBits($currentLineStyle, $numLineBits);
		    }
		    if ($stateNewStyles) {
		       // not implemented yet.
		       abort();
		    }
		}
	    } else {
       	        $straightFlag = $shapeRecord['StraightFlag'];
		$writer->putUIBit($straightFlag);
		if ($straightFlag) {
		    $deltaX = $shapeRecord['X'] - $currentDrawingPositionX;
		    $deltaY = $shapeRecord['Y'] - $currentDrawingPositionY;
   		    $XNumBits = $writer->need_bits_signed($deltaX);
   		    $YNumBits = $writer->need_bits_signed($deltaY);
   		    $numBits = max($XNumBits, $YNumBits);
		    if ($numBits < 2) {
		       $numBits = 2;
		    }
		    $writer->putUIBits($numBits - 2, 4);
		    if ($deltaX && $deltaY) {
		        $writer->putUIBit(1); // GeneralLineFlag
			$writer->putSIBits($deltaX, $numBits);
			$writer->putSIBits($deltaY, $numBits);
		    } else {
		        $writer->putUIBit(0); // GeneralLineFlag
			if ($deltaX) {
			   $writer->putUIBit(0); // VertLineFlag
			   $writer->putSIBits($deltaX, $numBits);
			} else {
			   $writer->putUIBit(1); // VertLineFlag
			   $writer->putSIBits($deltaY, $numBits);
			}
		    }
		    $currentDrawingPositionX = $shapeRecord['X'];
		    $currentDrawingPositionY = $shapeRecord['Y'];
		} else {
		    $controlDeltaX = $shapeRecord['ControlX'] - $currentDrawingPositionX;
		    $controlDeltaY = $shapeRecord['ControlY'] - $currentDrawingPositionY;
		    $currentDrawingPositionX = $shapeRecord['ControlX'];
		    $currentDrawingPositionY = $shapeRecord['ControlY'];
		    $anchorDeltaX = $shapeRecord['AnchorX'] - $currentDrawingPositionX;
		    $anchorDeltaY = $shapeRecord['AnchorY'] - $currentDrawingPositionY;
		    $currentDrawingPositionX = $shapeRecord['AnchorX'];
		    $currentDrawingPositionY = $shapeRecord['AnchorY'];

		    $numBitsControlDeltaX = $writer->need_bits_signed($controlDeltaX);
		    $numBitsControlDeltaY = $writer->need_bits_signed($controlDeltaY);
		    $numBitsAnchorDeltaX = $writer->need_bits_signed($anchorDeltaX);
		    $numBitsAnchorDeltaY = $writer->need_bits_signed($anchorDeltaY);
		    $numBits = max($numBitsControlDeltaX, $numBitsControlDeltaY, $numBitsAnchorDeltaX, $numBitsAnchorDeltaY);
		    if ($numBits < 2) {
		       $numBits = 2;
		    }
		    $writer->putUIBits($numBits - 2, 4);
		    $writer->putSIBits($controlDeltaX, $numBits);
		    $writer->putSIBits($controlDeltaY, $numBits);
		    $writer->putSIBits($anchorDeltaX, $numBits);
		    $writer->putSIBits($anchorDeltaY, $numBits);
		}
	    }
	}
	return $writer->output();
    }
    function _buildFILLSTYLEARRAY($writer, $tagCode) {
    	// とりあえず頭に全部展開するパターン。Shape2 用最適化は後で
	$fillStyleCount = count($this->_fillStyles);
	if ($fillStyleCount < 0xff) {
	    $writer->putUI8($fillStyleCount);
	} else {
	    $writer->putUI8(0xff);
	    if ($tagCode > 2) {
	    	 $writer->putUI16LE($fillStyleCount);
	    } else {
	      	 $fillStyleCount = 0xff; // DefineShape(1)
	    }
	}
//for ($i = 0 ; $i < $fillStyleCount ; $i++) {
        foreach ($this->_fillStyles as $fillStyle) {
	    $fillStyleType = $fillStyle['FillStyleType'];
	    $writer->putUI8($fillStyleType);
	    switch ($fillStyleType) {
	      case 0x00: // solid fill
		if ($tagCode < 32 ) { // 32:DefineShape3
		    IO_SWF_Type::buildRGB($writer, $fillStyle['Color']);
		} else {
		    IO_SWF_Type::buildRGBA($writer, $fillStyle['Color']);
		}
	      	break;
	      case 0x10: // linear gradient fill
	      case 0x12: // radianar gradient fill
	        $writer->putUIBits($fillStyle['SpreadMode'], 2);
	        $writer->putUIBits($fillStyle['InterpolationMode'], 2);
	   	$numGradients = count($fillStyle['GradientRecords']);
	   	$writer->putUIBits($numGradients , 4);
		foreach ($fillStyle['GradientRecords'] as $gradientRecord) {
   		    $writer->putUI8($gradientRecord['Ratio']);
    		    if ($tagCode < 32 ) { // 32:DefineShape3
		        IO_SWF_Type::buildRGB($writer, $gradientRecord['Color']);
		    } else {
		        IO_SWF_Type::buildRGBA($writer, $gradientRecord['Color']);
		    }
		}
	      break;
	      // case 0x13: // focal gradient fill // 8 and later
	      // break;
	      case 0x40: // repeating bitmap fill
	      case 0x41: // clipped bitmap fill
	      case 0x42: // non-smoothed repeating bitmap fill
	      case 0x43: // non-smoothed clipped bitmap fill
      	        $writer->putUI16LE($fillStyle['BitmapId']);
	        IO_SWF_Type::buildMATRIX($writer, $fillStyle['BitmapMatrix']);
		break;
	    }
	}
	return $fillStyleCount;
    }
    function _buildLINESTYLEARRAY($writer, $tagCode) {
    	// とりあえず頭に全部展開するパターン。Shape2 用最適化は後で
	$lineStyleCount = count($this->_lineStyles);
	if ($lineStyleCount < 0xff) {
	    $writer->putUI8($lineStyleCount);
	} else {
	    $writer->putUI8(0xff);
    	    if ($tagCode > 2) {
	        $writer->putUI16LE($lineStyleCount);
	    } else {
	        $lineStyleCount = 0xff; // DefineShape(1)
	    }
	}
	foreach ($this->_lineStyles as $lineStyle) {
	    $writer->putUI16LE($lineStyle['Width']);
    	    if ($tagCode < 32 ) { // 32:DefineShape3
    	        IO_SWF_Type::buildRGB($writer, $lineStyle['Color']);
	    } else {
    	        IO_SWF_Type::buildRGBA($writer, $lineStyle['Color']);
	    }
	}
	return $lineStyleCount;
    }
    function deforme() {
    	; // todo...
    }
}
