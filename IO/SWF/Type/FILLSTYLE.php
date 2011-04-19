<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_FILLSTYLE extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $fillStyle = array();
        $fillStyleType = $reader->getUI8();
        $fillStyle['FillStyleType'] = $fillStyleType;
        switch ($fillStyleType) {
          case 0x00: // solid fill
            if ($tagCode < 32 ) { // 32:DefineShape3
                $fillStyle['Color'] = IO_SWF_Type_RGB::parse($reader);
            } else {
                $fillStyle['Color'] = IO_SWF_Type_RGBA::parse($reader);
            }
            break;
          case 0x10: // linear gradient fill
          case 0x12: // radial gradient fill
            $fillStyle['GradientMatrix'] = IO_SWF_Type_MATRIX::parse($reader);
            $reader->byteAlign();
            $fillStyle['SpreadMode'] = $reader->getUIBits(2);
            $fillStyle['InterpolationMode'] = $reader->getUIBits(2);
            $numGradients = $reader->getUIBits(4);
            $fillStyle['GradientRecords'] = array();
            for ($j = 0 ; $j < $numGradients ; $j++) {
                $gradientRecord = array();
                $gradientRecord['Ratio'] = $reader->getUI8();
                if ($tagCode < 32 ) { // 32:DefineShape3
                    $gradientRecord['Color'] = IO_SWF_Type_RGB::parse($reader);
                } else {
                    $gradientRecord['Color'] = IO_SWF_Type_RGBA::parse($reader);
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
            $fillStyle['BitmapMatrix'] = IO_SWF_Type_MATRIX::parse($reader);
            break;
          default:
        // XXX: 受理できない旨のエラー出力
            break ; // XXX
        }
        return $fillStyle;
    }
    static function build(&$writer, $fillStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];

        $fillStyleType = $fillStyle['FillStyleType'];
        $writer->putUI8($fillStyleType);
        switch ($fillStyleType) {
          case 0x00: // solid fill
            if ($tagCode < 32 ) { // 32:DefineShape3
                IO_SWF_Type_RGB::build($writer, $fillStyle['Color']);
            } else {
                IO_SWF_Type_RGBA::build($writer, $fillStyle['Color']);
            }
            break;
          case 0x10: // linear gradient fill
          case 0x12: // radial gradient fill
            IO_SWF_Type_MATRIX::build($writer, $fillStyle['GradientMatrix']);
            $writer->byteAlign();
            $writer->putUIBits($fillStyle['SpreadMode'], 2);
            $writer->putUIBits($fillStyle['InterpolationMode'], 2);
            $numGradients = count($fillStyle['GradientRecords']);
            $writer->putUIBits($numGradients , 4);
            foreach ($fillStyle['GradientRecords'] as $gradientRecord) {
                $writer->putUI8($gradientRecord['Ratio']);
                if ($tagCode < 32 ) { // 32:DefineShape3
                    IO_SWF_Type_RGB::build($writer, $gradientRecord['Color']);
                } else {
                    IO_SWF_Type_RGBA::build($writer, $gradientRecord['Color']);
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
            IO_SWF_Type_MATRIX::build($writer, $fillStyle['BitmapMatrix']);
            break;
        }
        return true;
    }
    static function string($fillStyle, $opts = array()) {
        $tagCode = $opts['tagCode'];
        $text = '';
        $fillStyleType = $fillStyle['FillStyleType'];
        switch ($fillStyleType) {
          case 0x00: // solid fill
            $color = $fillStyle['Color'];
            if ($tagCode < 32 ) { // 32:DefineShape3
                $color_str = IO_SWF_Type_RGB::string($color);
            } else {
                $color_str = IO_SWF_Type_RGBA::string($color);
            }
            $text .= "\tsolid fill: $color_str\n";
            break;
          case 0x10: // linear gradient fill
          case 0x12: // radial gradient fill
            if ($fillStyleType == 0x10) {
                $text .= "\tlinear gradient fill\n";
            } else {
                $text .= "\tradial gradient fill\n";
            }
            $opts = array('indent' => 2);
            $matrix_str = IO_SWF_Type_MATRIX::string($fillStyle['GradientMatrix'], $opts);
            $text .= $matrix_str . "\n";
            $spreadMode = $fillStyle['SpreadMode'];
            $interpolationMode = $fillStyle['InterpolationMode'];
            foreach ($fillStyle['GradientRecords'] as $gradientRecord) {
                $ratio = $gradientRecord['Ratio'];
                $color = $gradientRecord['Color'];
                if ($tagCode < 32 ) { // 32:DefineShape3
                    $color_str = IO_SWF_Type_RGB::string($color);
                } else {
                    $color_str = IO_SWF_Type_RGBA::string($color);
                }
                $text .= "\t\tRatio: $ratio Color:$color_str\n";
            }
            break;
          case 0x40: // repeating bitmap fill
          case 0x41: // clipped bitmap fill
          case 0x42: // non-smoothed repeating bitmap fill
          case 0x43: // non-smoothed clipped bitmap fill
            $text .= "\tBigmap($fillStyleType): ";
            $text .= "  BitmapId: ".$fillStyle['BitmapId']."\n";
            $text .= "\tBitmapMatrix:\n";
            $opts = array('indent' => 2);
            $matrix_str = IO_SWF_Type_MATRIX::string($fillStyle['BitmapMatrix'], $opts);
            $text .= $matrix_str . "\n";
            break;
          default:
            $text .= "Unknown FillStyleType($fillStyleType)\n";
        }
        return $text;
    }
}
