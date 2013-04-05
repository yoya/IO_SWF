<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/../Type/RGB.php';
require_once dirname(__FILE__).'/../Type/RGBA.php';

class IO_SWF_Type_LINESTYLE extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        $lineStyle = array();
        if ($isMorph === false) {
            $lineStyle['Width'] = $reader->getUI16LE();
            if ($tagCode == 83) { // DefineShape4
                $lineStyle['StartCapStyle'] = $reader->getUIBits(2);
                $lineStyle['JoinStyle'] = $reader->getUIBits(2);
                $lineStyle['HasFillFlag'] = $reader->getUIBit();
                $lineStyle['NoHScaleFlag'] = $reader->getUIBit();
                $lineStyle['NoVScaleFlag'] = $reader->getUIBit();
                $lineStyle['PixelHintingFlag'] = $reader->getUIBit();
                // ----
                $lineStyle['(Reserved)'] = $reader->getUIBits(5);
                $lineStyle['NoClose'] = $reader->getUIBit();
                $lineStyle['EndCapStyle'] = $reader->getUIBits(2);
                if ($lineStyle['JoinStyle'] == 2) {
                    $lineStyle['MiterLimitFactor'] = $reader->getUI16LE();
                }
            }
            if ($tagCode < 32 ) { // DefineShape1,2
                $lineStyle['Color'] = IO_SWF_Type_RGB::parse($reader);
            } else if ($tagCode == 32) { // DefineShape3
                $lineStyle['Color'] = IO_SWF_Type_RGBA::parse($reader);
            } else { // DefineShape4
                if ($lineStyle['HasFillFlag'] == 0) {
                    $lineStyle['Color'] = IO_SWF_Type_RGBA::parse($reader);
                } else {
                    $lineStyle['FillType'] = IO_SWF_Type_FILLSTYLE::parse($reader, $opts);
                }
            }
        } else {
            $lineStyle['StartWidth'] = $reader->getUI16LE();
            $lineStyle['EndWidth']   = $reader->getUI16LE();
            $lineStyle['StartColor'] = IO_SWF_Type_RGBA::parse($reader);
            $lineStyle['EndColor']   = IO_SWF_Type_RGBA::parse($reader);
        }
        return  $lineStyle;
    }
    static function build(&$writer, $lineStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        if ($isMorph === false) {
            $writer->putUI16LE($lineStyle['Width']);
            if ($tagCode == 83) { // DefineShape4
                $writer->putUIBits($lineStyle['StartCapStyle'], 2);
                $writer->putUIBits($lineStyle['JoinStyle'], 2);
                $writer->putUIBit($lineStyle['HasFillFlag']);
                $writer->putUIBit($lineStyle['NoHScaleFlag']);
                $writer->putUIBit($lineStyle['NoVScaleFlag']);
                $writer->putUIBit($lineStyle['PixelHintingFlag']);
                // ----
                $writer->putUIBits(0, 5); //Reserved
                $writer->putUIBit($lineStyle['NoClose']);
                $writer->putUIBits($lineStyle['EndCapStyle'], 2);
                if ($lineStyle['JoinStyle'] == 2) {
                    $writer->putUI16LE($lineStyle['MiterLimitFactor']);
                }
            }
            if ($tagCode < 32 ) { // DefineShape1,2
                IO_SWF_Type_RGB::build($writer, $lineStyle['Color']);
            } else if ($tagCode == 32) { // DefineShape3
                IO_SWF_Type_RGBA::build($writer, $lineStyle['Color']);
            } else { // DefineShape4
                if ($lineStyle['HasFillFlag'] == 0) {
                    IO_SWF_Type_RGBA::build($writer, $lineStyle['Color']);
                } else {
                    IO_SWF_Type_FILLSTYLE::build($writer, $lineStyle['FillType'], $opts);
                }
            }
        } else {
            $writer->putUI16LE($lineStyle['StartWidth']);
            $writer->putUI16LE($lineStyle['EndWidth']);
            IO_SWF_Type_RGBA::build($writer, $lineStyle['StartColor']);
            IO_SWF_Type_RGBA::build($writer, $lineStyle['EndColor']);
        }
        return true;
    }
    static function string($lineStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        $text = '';

        if ($isMorph === false) {
            $text .= "Width:{$lineStyle['Width']}  ";
            if ($tagCode == 83) { // DefineShape4
                $text .= "StartCapStyle:{$lineStyle['StartCapStyle']} JoinStyle:{$lineStyle['JoinStyle']}  ";
                $text .= "HasFillFlag:{$lineStyle['HasFillFlag']} NoHScaleFlag:{$lineStyle['NoHScaleFlag']} NoVScaleFlag:{$lineStyle['NoVScaleFlag']} PixelHintingFlag:{$lineStyle['PixelHintingFlag']}  ";
            }
            if ($tagCode < 32 ) { // DefineShape1,2
                $color_str = IO_SWF_Type_RGB::string($lineStyle['Color']);
                $text .= "Color: $color_str  ";
            } else if ($tagCode == 32 ) { // DefineShape3
                $color_str = IO_SWF_Type_RGBA::string($lineStyle['Color']);
                $text .= "Color: $color_str  ";
            } else { // DefineShape4
                if ($lineStyle['HasFillFlag'] == 0) {
                    $color_str = IO_SWF_Type_RGBA::string($lineStyle['Color']);
                    $text .= "Color: $color_str  ";
                } else {
                    $filltype_str = IO_SWF_Type_FILLSTYLE::string($lineStyle['FillType']);
                    $text .= "FillType: ".$filltype_str;
                }
            }
        } else {
            $startWidth = $lineStyle['StartWidth'];
            $endWidth = $lineStyle['EndWidth'];
            $startColorStr = IO_SWF_Type_RGBA::string($lineStyle['StartColor']);
            $endColorStr = IO_SWF_Type_RGBA::string($lineStyle['EndColor']);
            $text .= "Width: $startWidth => $endWidth Color: $startColorStr => $endColorStr  ";
        }
        return $text.PHP_EOL;
    }
}
