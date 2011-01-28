<?php

/*
 * 2011/1/25- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';

class IO_SWF_Type {
    static function parseRECT($reader) {
        $frameSize = array();
        $nBits = $reader->getUIBits(5);
//        $frameSize['(NBits)'] = $nBits;
        $frameSize['Xmin'] = $reader->getSIBits($nBits);
        $frameSize['Xmax'] = $reader->getSIBits($nBits);
        $frameSize['Ymin'] = $reader->getSIBits($nBits);
        $frameSize['Ymax'] = $reader->getSIBits($nBits) ;
    	return $frameSize; 
    }
    static function buildRECT($data) {
    	   return '';
    }
    static function parseRGB($reader) {
    	$rgb = array();
    	$rgb['Red'] = $reader->getUI8();
    	$rgb['Green'] = $reader->getUI8();
    	$rgb['Blue'] = $reader->getUI8();
	return $rgb;
    }
    static function buildRGB($d) {
    	   return '';
    }
    static function stringRGB($color) {
	return sprintf("#%02x%02x%02x", $color['Red'], $color['Green'], $color['Blue']);
    }
    static function parseRGBA($reader) {
    	$rgba = array();
    	$rgba['Red'] = $reader->getUI8();
    	$rgba['Green'] = $reader->getUI8();
    	$rgba['Blue'] = $reader->getUI8();
    	$rgba['Alpha'] = $reader->getUI8();
	return $rgba;
    }
    static function buildRGBA($d) {
    	   return '';
    }
    static function stringRGBA($color) {
	return sprintf("#%02x%02x%02x(02x)", $color['Red'], $color['Green'], $color['Blue'], $color['Alpha']);
    }
    static function stringRGBorRGBA($color) {
    	if (isset($color['Alpha'])) {
	   return self::stringRGBA($color);
	} else {
	   return self::stringRGB($color);
	}
    }
    static function parseMATRIX($reader) {
    	$matrix = array();
        $hasScale = $reader->getUIBit();
	if ($hasScale) {
	    $nScaleBits = $reader->getUIBits(5);
//	    $matrix['(NScaleBits)'] = $nScaleBits;
	    $matrix['ScaleX'] = $reader->getSIBits($nScaleBits);
	    $matrix['ScaleY'] = $reader->getSIBits($nScaleBits);
	} else {
	    $matrix['ScaleX'] = 20;
	    $matrix['ScaleY'] = 20;
	}
        $hasRotate = $reader->getUIBit();
	if ($hasRotate) {
	    $nRotateBits = $reader->getUIBits(5);
//	    $matrix['(NRotateBits)'] = $nRotateBits;
	    $matrix['RotateSkew0'] = $reader->getSIBits($nRotateBits);
	    $matrix['RotateSkew1'] = $reader->getSIBits($nRotateBits);
	} else  {
	    $matrix['RotateSkew0'] = 0;
	    $matrix['RotateSkew1'] = 0;
	}
        $nTranslateBits = $reader->getUIBits(5);
	$matrix['TranslateX'] = $reader->getSIBits($nTranslateBits);
	$matrix['TranslateY'] = $reader->getSIBits($nTranslateBits);
	return $matrix;
    }
    static function buildMATRIX($d) {
    	   return '';
    }
    static function stringMATRIX($matrix, $indent) {
	   $text_fmt = <<< EOS
%s| %3.2f %3.2f |  %3.2f
%s| %3.2f %3.2f |  %3.2f
EOS;
	return 	sprintf($text_fmt, 
		str_repeat("\t", $indent),
		$matrix['ScaleX'] / 0x10000 / 20 ,
		$matrix['RotateSkew0'],
		$matrix['TranslateX'] / 0x10000 / 20,
		str_repeat("\t", $indent),
		$matrix['RotateSkew1'],
		$matrix['ScaleY'] / 0x10000 / 20,
		$matrix['TranslateY'] / 0x10000 / 20);
    }
}