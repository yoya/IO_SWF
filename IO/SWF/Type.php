<?php

/*
 * 2011/1/25- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';

class IO_SWF_Type {
    static function parseRECT($reader) {
        $frameSize = array();
        $nBits = $reader->getUIBits(5);
        $frameSize['Xmin'] = $reader->getSIBits($nBits);
        $frameSize['Xmax'] = $reader->getSIBits($nBits);
        $frameSize['Ymin'] = $reader->getSIBits($nBits);
        $frameSize['Ymax'] = $reader->getSIBits($nBits) ;
    	return $frameSize; 
    }
    static function buildRECT($writer, $frameSize) {
        $nBits = 0;
	foreach ($frameSize as $size) {
	    if ($size == 0){
	        $bits = 0;
	    } else {
	        $bits = $writer->need_bits_signed($size);
	    }
	    $nBits = max($nBits, $bits);
	}
	$writer->putUIBits($nBits, 5);
        $writer->putSIBits($frameSize['Xmin'], $nBits);
        $writer->putSIBits($frameSize['Xmax'], $nBits);
        $writer->putSIBits($frameSize['Ymin'], $nBits);
        $writer->putSIBits($frameSize['Ymax'], $nBits);
    }
    static function parseRGB($reader) {
    	$rgb = array();
    	$rgb['Red'] = $reader->getUI8();
    	$rgb['Green'] = $reader->getUI8();
    	$rgb['Blue'] = $reader->getUI8();
	return $rgb;
    }
    static function buildRGB($writer, $rgb) {
	$writer->putUI8($rgb['Red']);
	$writer->putUI8($rgb['Green']);
	$writer->putUI8($rgb['Blue']);
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
    static function buildRGBA($writer, $rgba) {
	$writer->putUI8($rgba['Red']);
	$writer->putUI8($rgba['Green']);
	$writer->putUI8($rgba['Blue']);
	$writer->putUI8($rgba['Alpha']);
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
    static function buildMATRIX($writer, $matrix) {
        if ($matrix['ScaleX'] | $matrix['ScaleY']) {
	    $writer->putUIBit(1); // HasScale;
	    if ($matrix['ScaleX'] | $matrix['ScaleY']) {
	        $xNBits = $writer->need_bits_signed($matrix['ScaleX']);
	        $yNBits = $writer->need_bits_signed($matrix['ScaleY']);
	        $nScaleBits = max($xNBits, $yNBits);
	    } else {
	        $nScaleBits = 0;
	    }
	    $writer->putUIBits($nScaleBits, 5);
	    $writer->putSIBits($matrix['ScaleX'], $nScaleBits);
	    $writer->putSIBits($matrix['ScaleY'], $nScaleBits);
	} else {
	    $writer->putUIBit(0); // HasScale;
	}
	if ($matrix['RotateSkew0'] | $matrix['RotateSkew1']) {
	    $writer->putUIBit(1); // HasRotate
	    if ($matrix['RotateSkew0'] | $matrix['RotateSkew1']) {
	        $rs0NBits = $writer->need_bits_signed($matrix['RotateSkew0']);
	        $rs1NBits = $writer->need_bits_signed($matrix['RotateSkew1']);
	        $nRotateBits = max($rs0NBits, $rs1NBits);
	    } else {
	        $nRotateBits = 0;
	    }
	    $writer->putUIBits($nRotateBits, 5);
	    $writer->putSIBits($matrix['RotateSkew0'], $nRotateBits);
	    $writer->putSIBits($matrix['RotateSkew1'], $nRotateBits);
	} else {
	    $writer->putUIBit(0); // HasRotate
        }
	if ($matrix['TranslateX'] | $matrix['TranslateY']) {
	    $xNTranslateBits = $writer->need_bits_signed($matrix['TranslateX']);
	    $yNTranslateBits = $writer->need_bits_signed($matrix['TranslateY']);
	    $nTranslateBits = max($xNTranslateBits, $yNTranslateBits);
	} else {
	    $nTranslateBits = 0;
	}
	$writer->putUIBits($nTranslateBits, 5);
	$writer->putSIBits($matrix['TranslateX'], $nTranslateBits);
	$writer->putSIBits($matrix['TranslateY'], $nTranslateBits);
    }
    static function stringMATRIX($matrix, $indent) {
	   $text_fmt = <<< EOS
%s| %3.2f %3.2f |  %3.2f
%s| %3.2f %3.2f |  %3.2f
EOS;
	return 	sprintf($text_fmt, 
		str_repeat("\t", $indent),
		$matrix['ScaleX'] / 0x10000 / 20 ,
		$matrix['RotateSkew0'] / 0x10000 / 20,
		$matrix['TranslateX'] / 0x10000 / 20,
		str_repeat("\t", $indent),
		$matrix['RotateSkew1'] / 0x10000 / 20,
		$matrix['ScaleY'] / 0x10000 / 20,
		$matrix['TranslateY'] / 0x10000 / 20);
    }
}