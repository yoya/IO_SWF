<?php

/*
 * 2011/9/9- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
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
            $filte['GlowFilter'] = $glowfilter;
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
            $filter['GradientFilter'] = $gradientfilter;
            break;
        case 5: // ConvolutionFilter
            $convfilter = array();
            $matrixX = $reader->getUI8();
            $matrixY = $reader->getUI8();
            $convfilter['MatrixX'] = $matrixX;
            $convfilter['MatrixY'] = $matrixY;
            $convfilter['Divisor'] = IO_SWF_Type_Float($reader);
            $convfilter['Bios'] = IO_SWF_Type_Float($reader);
            $matrix_mn = array();
            $mn = $marixX * $matrixY;
            for ($i = 0 ; $i < $mn ; $i++) {
                $matrix_mn []= IO_SWF_Type_Float($reader);
            }
            $convfilter['Matrix'] = $matrix_mn;
            $convfilter['DefaultColor'] = IO_SWF_Type_RGBA::parse($reader, $opts);
            $convfilter['Reserved'] = $reader->getUIBits(6);
            $convfilter['Clamp'] = $reader->getUIBit();
            $convfilter['PreserveAlpha'] = $reader->getUIBit();
            $filter['ColorMatrixFilter'] = $convfilter;
            break;
        case 6: // ColorMatrixFilter
            $matrix_20 = array();
            for ($i = 0 ; $i < 20 ; $i++) {
                $matrix_20 []= IO_SWF_Type_Float($reader);
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
    static function build(&$writer, $filterlist, $opts = array()) {
        $NumberOfFilters = count($filterlist['Filter']);
    	$writer->putUI8($NumberOfFilters);
        foreach ($filterlist['Filter'] as $filter_entry) {
            IO_SWF_Type_FILTER::build($writer, $filter_entry, $opts);
        }
    }
    static function string($filterlist, $opts = array()) {
        $text = "\tNumberOfFilters:{$filterlist['NumberOfFilters']}\n";
        foreach ($filterlist['Filter'] as $filter_entry) {
            $text .= "\t\t".IO_SWF_Type_FILTER::string($$filter_entry, $opts);
        }
    	return $text;
    }
}
