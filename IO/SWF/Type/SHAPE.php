<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_SHAPE extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $shapeRecords = array();

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
                $endOfShape = $reader->getUIBits(5);
               if ($endOfShape == 0) {
                    // EndShapeRecord
                    $shapeRecord['EndOfShape'] = $endOfShape;
                    $done = true;
               } else {
                    // StyleChangeRecord
                    $reader->incrementOffset(0, -5);
                    $stateNewStyles = $reader->getUIBit();
                    $stateLineStyle = $reader->getUIBit();
                    $stateFillStyle1 = $reader->getUIBit();
                    $stateFillStyle0 = $reader->getUIBit();

                    $stateMoveTo = $reader->getUIBit();
                    if ($stateMoveTo) {
                    $moveBits = $reader->getUIBits(5);
//                        $shapeRecord['(MoveBits)'] = $moveBits;
                        $moveDeltaX = $reader->getSIBits($moveBits);
                        $moveDeltaY = $reader->getSIBits($moveBits);
//                        $currentDrawingPositionX += $moveDeltaX;
//                        $currentDrawingPositionY += $moveDeltaY;
                        $currentDrawingPositionX = $moveDeltaX;
                        $currentDrawingPositionY = $moveDeltaY;
                        $shapeRecord['MoveX'] = $currentDrawingPositionX;
                        $shapeRecord['MoveY'] = $currentDrawingPositionY;
                    }
                    $shapeRecord['MoveX'] = $currentDrawingPositionX;
                    $shapeRecord['MoveY'] = $currentDrawingPositionY;

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
                        $opts = array('tagCode' => $tagCode);
                        $shapeRecord['FillStyles'] = IO_SWF_TYPE_FILLSTYLEARRAY::parse($reader, $opts);
                        $shapeRecord['LineStyles'] = IO_SWF_TYPE_LINESTYLEARRAY::parse($reader, $opts);
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
//                    $shapeRecord['(NumBits)'] = $numBits;
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
//                    $shapeRecord['(NumBits)'] = $numBits;

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
            $shapeRecords []= $shapeRecord;
       }
    	return $shapeRecords;
    }
    static function build(&$writer, $shapeRecords, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $fillStyleCount = $opts['fillStyleCount'];
        $lineStyleCount = $opts['lineStyleCount'];
        if ($fillStyleCount == 0) {
            $numFillBits = 0;
        } else {
            // $fillStyleCount == fillStyle MaxValue because 'undefined' use 0
            $numFillBits = $writer->need_bits_unsigned($fillStyleCount);
        }
        if ($lineStyleCount == 0) {
            $numLineBits = 0;
        } else {
            // $lineStyleCount == lineStyle MaxValue because 'undefined' use 0
            $numLineBits = $writer->need_bits_unsigned($lineStyleCount);
        }

        $writer->byteAlign();
        $writer->putUIBits($numFillBits, 4);
        $writer->putUIBits($numLineBits, 4);
        $currentDrawingPositionX = 0;
        $currentDrawingPositionY = 0;
        $currentFillStyle0 = 0;
        $currentFillStyle1 = 0;
        $currentLineStyle = 0;
        foreach ($shapeRecords as $shapeRecordIndex => $shapeRecord) {
            $typeFlag = $shapeRecord['TypeFlag'];
            $writer->putUIBit($typeFlag);
            if($typeFlag == 0) {
                if (isset($shapeRecord['EndOfShape']) && ($shapeRecord['EndOfShape']) == 0) {
                    // EndShapeRecord
                    $writer->putUIBits(0, 5);
                } else {
                    // StyleChangeRecord
                    $stateNewStyles =  isset($shapeRecord['FillStyles'])?1:0;
                    $stateLineStyle = ($shapeRecord['LineStyle'] != $currentLineStyle)?1:0;
                    $stateFillStyle1 = ($shapeRecord['FillStyle1'] != $currentFillStyle1)?1:0;
                    $stateFillStyle0 = ($shapeRecord['FillStyle0'] != $currentFillStyle0)?1:0;

                    $writer->putUIBit($stateNewStyles);
                    $writer->putUIBit($stateLineStyle);
                    $writer->putUIBit($stateFillStyle1);
                    $writer->putUIBit($stateFillStyle0);

                    if (($shapeRecord['MoveX'] != $currentDrawingPositionX) || ($shapeRecord['MoveY'] != $currentDrawingPositionY)) {
                        $stateMoveTo = true;
                    } else {
                        $stateMoveTo = false;
                    }
                    $writer->putUIBit($stateMoveTo);
                    if ($stateMoveTo) {
                        $moveX = $shapeRecord['MoveX'];
                        $moveY = $shapeRecord['MoveY'];
                        $currentDrawingPositionX = $moveX;
                        $currentDrawingPositionY = $moveY;
                        if ($moveX | $moveY) { 
                            $XmoveBits = $writer->need_bits_signed($moveX);
                            $YmoveBits = $writer->need_bits_signed($moveY);
                            $moveBits = max($XmoveBits, $YmoveBits);
                        } else {
                            $moveBits = 0;
                        }
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
                        $opts = array('tagCode' => $tagCode);
                        IO_SWF_Type_FILLSTYLEARRAY::build($writer, $shapeRecord['FillStyles'], $opts);
                        IO_SWF_Type_LINESTYLEARRAY::build($writer, $shapeRecord['LineStyles'], $opts);
                        $fillStyleCount = count($shapeRecord['FillStyles']);
                        if ($fillStyleCount == 0) {
                            $numFillBits = 0;
                        } else {
                            // $fillStyleCount == fillStyle MaxValue because 'undefined' use 0
                            $numFillBits = $writer->need_bits_unsigned($fillStyleCount);
                        }
                        if ($lineStyleCount == 0) {
                            $numLineBits = 0;
                        } else {
                            // $lineStyleCount == lineStyle MaxValue because 'undefined' use 0
                            $numLineBits = $writer->need_bits_unsigned($lineStyleCount);
                        }
                        $writer->byteAlign();
                        $writer->putUIBits($numFillBits, 4);
                        $writer->putUIBits($numLineBits, 4);
                    }
                }
            } else {
                $straightFlag = $shapeRecord['StraightFlag'];
                $writer->putUIBit($straightFlag);
                if ($straightFlag) {
                    $deltaX = $shapeRecord['X'] - $currentDrawingPositionX;
                    $deltaY = $shapeRecord['Y'] - $currentDrawingPositionY;
                    if ($deltaX | $deltaY) {
                       $XNumBits = $writer->need_bits_signed($deltaX);
                       $YNumBits = $writer->need_bits_signed($deltaY);
                       $numBits = max($XNumBits, $YNumBits);
                    } else {
                        $numBits = 0;
                    }
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
        return true;
    }
    static function string($shapeRecords, $opts = array()) {
        $tagCode = $opts['tagCode'];
        foreach ($shapeRecords as $shapeRecord) {
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
                   if (isset($shapeRecord['FillStyles'])) {
                       echo "    FillStyles:\n";
                       echo IO_SWF_Type_FILLSTYLEARRAY::string($shapeRecord['FillStyles']);
                   }
                   if (isset($shapeRecord['LineStyles'])) {
                        echo "    LineStyles:\n";
                        echo IO_SWF_Type_FILLSTYLEARRAY::string($shapeRecord['LineStyles']);
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
                    echo "\tCurvedEdge: MoveTo: Control($controlX, $controlY) Anchor($anchorX, $anchorY)\n";
                }
            }
        }
    }
}
