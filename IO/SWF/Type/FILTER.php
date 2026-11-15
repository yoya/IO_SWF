<?php

/*
 * 2011/9/9- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/RGBA.php';
require_once dirname(__FILE__).'/Float.php';

class IO_SWF_Type_FILTER implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$filter = array();
    	$filterID = $reader->getUI8();
    	$filter['FilterID'] = $filterID;
        switch ($filterID) {
        case 0: // DropShadowFilter
            $dropshadowfilter = array();
            $dropshadowfilter['DefaultColor'] = IO_SWF_Type_RGBA::parse($reader, $opts);
            $dropshadowfilter['BlurX'] = $reader->getUI32LE(); // 16.16 FIXED
            $dropshadowfilter['BlurY'] = $reader->getUI32LE(); // 16.16 FIXED
            $dropshadowfilter['Angle'] = $reader->getUI32LE(); // 16.16 FIXED
            $dropshadowfilter['Distance'] = $reader->getUI32LE(); // 16.16 FIXED
            $dropshadowfilter['Strength'] = $reader->getUI16LE(); // 8.8 FIXED
            $dropshadowfilter['InnerShadow'] = $reader->getUIBit();
            $dropshadowfilter['Knockout'] = $reader->getUIBit();
            $dropshadowfilter['CompositeSource'] = $reader->getUIBit();
            $dropshadowfilter['Passed'] = $reader->getUIBits(5);
            $filter['DropShadowFilter'] = $dropshadowfilter;
            break;
        case 1: // BlurFilter
            $blurfilter = array();
            $blurfilter['BlurX'] = $reader->getUI32LE(); // 16.16 FIXED
            $blurfilter['BlurY'] = $reader->getUI32LE(); // 16.16 FIXED
            $blurfilter['Passes'] = $reader->getUIBits(5);
            $blurfilter['Reserved'] = $reader->getUIBits(3);
            $filter['BlurFilter'] = $blurfilter;
            break;
        case 2: // GlowFilter
            $glowfilter = array();
            $glowfilter['GlowColor'] = IO_SWF_Type_RGBA::parse($reader, $opts);
            $glowfilter['BlurX'] = $reader->getUI32LE(); // 16.16 FIXED
            $glowfilter['BlurY'] = $reader->getUI32LE(); // 16.16 FIXED
            $glowfilter['Strength'] = $reader->getUI16LE(); // 8.8 FIXED
            $glowfilter['InnerGlow'] = $reader->getUIBit();
            $glowfilter['Knockout'] = $reader->getUIBit();
            $glowfilter['CompositeSource'] = $reader->getUIBit();
            $glowfilter['Passed'] = $reader->getUIBits(5);
            $filter['GlowFilter'] = $glowfilter;
            break;
        case 3: // BevelFilter
            $bevelfilter = array();
            $bevelfilter['ShadowColor'] = IO_SWF_Type_RGBA::parse($reader, $opts);
            $bevelfilter['HighlightColor'] = IO_SWF_Type_RGBA::parse($reader, $opts);
            $bevelfilter['BlurX'] = $reader->getUI32LE(); // 16.16 FIXED
            $bevelfilter['BlurY'] = $reader->getUI32LE(); // 16.16 FIXED
            $bevelfilter['Angle'] = $reader->getUI32LE(); // 16.16 FIXED
            $bevelfilter['Distance'] = $reader->getUI32LE(); // 16.16 FIXED
            $bevelfilter['Strength'] = $reader->getUI16LE(); // 8.8 FIXED
            $bevelfilter['InnerShadow'] = $reader->getUIBit();
            $bevelfilter['Knockout'] = $reader->getUIBit();
            $bevelfilter['CompositeSource'] = $reader->getUIBit();
            $bevelfilter['OnTop'] = $reader->getUIBit();
            $bevelfilter['Passed'] = $reader->getUIBits(4);
            $filter['BevelFilter'] = $bevelfilter;
            break;
        case 4: // GradientFilter
            $gradientfilter = array();
            $numColors = $reader->getUI8();
            $gradientfilter['NumColors'] = $numColors;
            $gradientColors = array();
            for ($i = 0 ; $i < $numColors ; $i++) {
                $gradientColors []= IO_SWF_Type_RGBA::parse($reader, $opts);
            }
            $gradientfilter['GradientColors'] = $gradientColors;
            $gradientRatio = array();
            for ($i = 0 ; $i < $numColors ; $i++) {
                $gradientRatio []= $reader->getUI8();
            }
            $gradientfilter['GradientRatio'] = $gradientRatio;
            $gradientfilter['BlurX'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientfilter['BlurY'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientfilter['Angle'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientfilter['Distance'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientfilter['Strength'] = $reader->getUI16LE(); // 8.8 FIXED
            $gradientfilter['InnerShadow'] = $reader->getUIBit();
            $gradientfilter['Knockout'] = $reader->getUIBit();
            $gradientfilter['CompositeSource'] = $reader->getUIBit();
            $gradientfilter['OnTop'] = $reader->getUIBit();
            $gradientfilter['Passed'] = $reader->getUIBits(4);
            $filter['GradientFilter'] = $gradientfilter;
            break;
        case 5: // ConvolutionFilter
            $convfilter = array();
            $matrixX = $reader->getUI8();
            $matrixY = $reader->getUI8();
            $convfilter['MatrixX'] = $matrixX;
            $convfilter['MatrixY'] = $matrixY;
            $convfilter['Divisor'] = IO_SWF_Type_Float::parse($reader);
            $convfilter['Bios'] = IO_SWF_Type_Float::parse($reader);
            $matrix_mn = array();
            $mn = $matrixX * $matrixY;
            for ($i = 0 ; $i < $mn ; $i++) {
                $matrix_mn []= IO_SWF_Type_Float::parse($reader);
            }
            $convfilter['Matrix'] = $matrix_mn;
            $convfilter['DefaultColor'] = IO_SWF_Type_RGBA::parse($reader, $opts);
            $convfilter['Reserved'] = $reader->getUIBits(6);
            $convfilter['Clamp'] = $reader->getUIBit();
            $convfilter['PreserveAlpha'] = $reader->getUIBit();
            $filter['ConvolutionFilter'] = $convfilter;
            break;
        case 6: // ColorMatrixFilter
            $matrix_20 = array();
            for ($i = 0 ; $i < 20 ; $i++) {
                $matrix_20 []= IO_SWF_Type_Float::parse($reader);
            }
            $filter['ColorMatrixFilter'] = array('Matrix' => $matrix_20);
            break;
        case 7: // GradientBevelFilter
            $gradientbevelfilter = array();
            $numColors = $reader->getUI8();
            $gradientbevelfilter['NumColors'] = $numColors;
            $gradientColors = array();
            for ($i = 0 ; $i < $numColors ; $i++) {
                $gradientColors []= IO_SWF_Type_RGBA::parse($reader, $opts);
            }
            $gradientbevelfilter['GradientColors'] = $gradientColors;
            $gradientRatio = array();
            for ($i = 0 ; $i < $numColors ; $i++) {
                $gradientRatio []= $reader->getUI8();
            }
            $gradientbevelfilter['GradientRatio'] = $gradientRatio;
            $gradientbevelfilter['BlurX'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientbevelfilter['BlurY'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientbevelfilter['Angle'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientbevelfilter['Distance'] = $reader->getUI32LE(); // 16.16 FIXED
            $gradientbevelfilter['Strength'] = $reader->getUI16LE(); // 8.8 FIXED
            $gradientbevelfilter['InnerShadow'] = $reader->getUIBit();
            $gradientbevelfilter['Knockout'] = $reader->getUIBit();
            $gradientbevelfilter['CompositeSource'] = $reader->getUIBit();
            $gradientbevelfilter['OnTop'] = $reader->getUIBit();
            $gradientbevelfilter['Passed'] = $reader->getUIBits(4);
            $filter['GradientBevelFilter'] = $gradientbevelfilter;
            break;
        }
    	return $filter;
    }
    static function build(&$writer, $filter, $opts = array()) {
        $filterID = $filter['FilterID'];
        $writer->putUI8($filterID);
        switch ($filterID) {
        case 0: // DropShadowFilter
            $dropshadowfilter = $filter['DropShadowFilter'];
            IO_SWF_Type_RGBA::build($writer, $dropshadowfilter['DefaultColor'], $opts);
            $writer->putUI32LE($dropshadowfilter['BlurX']); // 16.16 FIXED
            $writer->putUI32LE($dropshadowfilter['BlurY']); // 16.16 FIXED
            $writer->putUI32LE($dropshadowfilter['Angle']); // 16.16 FIXED
            $writer->putUI32LE($dropshadowfilter['Distance']); // 16.16 FIXED
            $writer->putUI16LE($dropshadowfilter['Strength']); // 8.8 FIXED
            $writer->putUIBit($dropshadowfilter['InnerShadow']);
            $writer->putUIBit($dropshadowfilter['Knockout']);
            $writer->putUIBit($dropshadowfilter['CompositeSource']);
            $writer->putUIBits($dropshadowfilter['Passed'], 5);
            break;
        case 1: // BlurFilter
            $blurfilter = $filter['BlurFilter'];
            $writer->putUI32LE($blurfilter['BlurX']); // 16.16 FIXED
            $writer->putUI32LE($blurfilter['BlurY']); // 16.16 FIXED
            $writer->putUIBits($blurfilter['Passes'], 5);
            $writer->putUIBits($blurfilter['Reserved'], 3);
            break;
        case 2: // GlowFilter
            $glowfilter = $filter['GlowFilter'];
            IO_SWF_Type_RGBA::build($writer, $glowfilter['GlowColor'], $opts);
            $writer->putUI32LE($glowfilter['BlurX']); // 16.16 FIXED
            $writer->putUI32LE($glowfilter['BlurY']); // 16.16 FIXED
            $writer->putUI16LE($glowfilter['Strength']); // 8.8 FIXED
            $writer->putUIBit($glowfilter['InnerGlow']);
            $writer->putUIBit($glowfilter['Knockout']);
            $writer->putUIBit($glowfilter['CompositeSource']);
            $writer->putUIBits($glowfilter['Passed'], 5);
            break;
        case 3: // BevelFilter
            $bevelfilter = $filter['BevelFilter'];
            IO_SWF_Type_RGBA::build($writer, $bevelfilter['ShadowColor'], $opts);
            IO_SWF_Type_RGBA::build($writer, $bevelfilter['HighlightColor'], $opts);
            $writer->putUI32LE($bevelfilter['BlurX']); // 16.16 FIXED
            $writer->putUI32LE($bevelfilter['BlurY']); // 16.16 FIXED
            $writer->putUI32LE($bevelfilter['Angle']); // 16.16 FIXED
            $writer->putUI32LE($bevelfilter['Distance']); // 16.16 FIXED
            $writer->putUI16LE($bevelfilter['Strength']); // 8.8 FIXED
            $writer->putUIBit($bevelfilter['InnerShadow']);
            $writer->putUIBit($bevelfilter['Knockout']);
            $writer->putUIBit($bevelfilter['CompositeSource']);
            $writer->putUIBit($bevelfilter['OnTop']);
            $writer->putUIBits($bevelfilter['Passed'], 4);
            break;
        case 4: // GradientFilter
            $gradientfilter = $filter['GradientFilter'];
            $numColors = $gradientfilter['NumColors'];
            $writer->putUI8($numColors);
            foreach ($gradientfilter['GradientColors'] as $gradientColor) {
                IO_SWF_Type_RGBA::build($writer, $gradientColor, $opts);
            }
            foreach ($gradientfilter['GradientRatio'] as $gradientRatio) {
                $writer->putUI8($gradientRatio);
            }
            $writer->putUI32LE($gradientfilter['BlurX']); // 16.16 FIXED
            $writer->putUI32LE($gradientfilter['BlurY']); // 16.16 FIXED
            $writer->putUI32LE($gradientfilter['Angle']); // 16.16 FIXED
            $writer->putUI32LE($gradientfilter['Distance']); // 16.16 FIXED
            $writer->putUI16LE($gradientfilter['Strength']); // 8.8 FIXED
            $writer->putUIBit($gradientfilter['InnerShadow']);
            $writer->putUIBit($gradientfilter['Knockout']);
            $writer->putUIBit($gradientfilter['CompositeSource']);
            $writer->putUIBit($gradientfilter['OnTop']);
            $writer->putUIBits($gradientfilter['Passed'], 4);
            break;
        case 5: // ConvolutionFilter
            $convfilter = $filter['ConvolutionFilter'];
            $writer->putUI8($convfilter['MatrixX']);
            $writer->putUI8($convfilter['MatrixY']);
            IO_SWF_Type_Float::build($writer, $convfilter['Divisor']);
            IO_SWF_Type_Float::build($writer, $convfilter['Bios']);
            foreach ($convfilter['Matrix'] as $matrixValue) {
                IO_SWF_Type_Float::build($writer, $matrixValue);
            }
            IO_SWF_Type_RGBA::build($writer, $convfilter['DefaultColor'], $opts);
            $writer->putUIBits($convfilter['Reserved'], 6);
            $writer->putUIBit($convfilter['Clamp']);
            $writer->putUIBit($convfilter['PreserveAlpha']);
            break;
        case 6: // ColorMatrixFilter
            $colormatrixfilter = $filter['ColorMatrixFilter'];
            foreach ($colormatrixfilter['Matrix'] as $matrixValue) {
                IO_SWF_Type_Float::build($writer, $matrixValue);
            }
            break;
        case 7: // GradientBevelFilter
            $gradientbevelfilter = $filter['GradientBevelFilter'];
            $numColors = $gradientbevelfilter['NumColors'];
            $writer->putUI8($numColors);
            foreach ($gradientbevelfilter['GradientColors'] as $gradientColor) {
                IO_SWF_Type_RGBA::build($writer, $gradientColor, $opts);
            }
            foreach ($gradientbevelfilter['GradientRatio'] as $gradientRatio) {
                $writer->putUI8($gradientRatio);
            }
            $writer->putUI32LE($gradientbevelfilter['BlurX']); // 16.16 FIXED
            $writer->putUI32LE($gradientbevelfilter['BlurY']); // 16.16 FIXED
            $writer->putUI32LE($gradientbevelfilter['Angle']); // 16.16 FIXED
            $writer->putUI32LE($gradientbevelfilter['Distance']); // 16.16 FIXED
            $writer->putUI16LE($gradientbevelfilter['Strength']); // 8.8 FIXED
            $writer->putUIBit($gradientbevelfilter['InnerShadow']);
            $writer->putUIBit($gradientbevelfilter['Knockout']);
            $writer->putUIBit($gradientbevelfilter['CompositeSource']);
            $writer->putUIBit($gradientbevelfilter['OnTop']);
            $writer->putUIBits($gradientbevelfilter['Passed'], 4);
            break;
        }
    }
    static $filterNameList = array(
        0 => 'DropShadowFilter',
        1 => 'BlurFilter',
        2 => 'GlowFilter',
        3 => 'BevelFilter',
        4 => 'GradientFilter',
        5 => 'ConvolutionFilter',
        6 => 'ColorMatrixFilter',
        7 => 'GradientBevelFilter',
    );
    static function string($filter, $opts = array()) {
        $filterID = $filter['FilterID'];
        if (isset(self::$filterNameList[$filterID])) {
            $filterName = self::$filterNameList[$filterID];
        } else {
            $filterName = "UnknownFilter";
        }
        return "FilterID:$filterID($filterName)\n";
    }
}
