<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_MATRIX extends IO_SWF_Type {
    static function parse($reader) {
    	$matrix = array();
        $hasScale = $reader->getUIBit();
    	if ($hasScale) {
    	    $nScaleBits = $reader->getUIBits(5);
//  	    $matrix['(NScaleBits)'] = $nScaleBits;
    	    $matrix['ScaleX'] = $reader->getSIBits($nScaleBits);
    	    $matrix['ScaleY'] = $reader->getSIBits($nScaleBits);
    	} else {
    	    $matrix['ScaleX'] = 20;
    	    $matrix['ScaleY'] = 20;
    	}
        $hasRotate = $reader->getUIBit();
    	if ($hasRotate) {
    	    $nRotateBits = $reader->getUIBits(5);
//	        $matrix['(NRotateBits)'] = $nRotateBits;
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
    static function build($writer, $matrix) {
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

    static function string($matrix, $opts = array()) {
        $indent = 0;
        if (isset($opts['indent'])) {
            $indent = $opts['indent'];
        }
	   $text_fmt = <<< EOS
%s| %3.3f %3.3f |  %3.2f
%s| %3.3f %3.3f |  %3.2f
EOS;
	return 	sprintf($text_fmt, 
		str_repeat("\t", $indent),
		$matrix['ScaleX'] / 0x10000 / 20,
		$matrix['RotateSkew0'] / 0x10000 / 20,
		$matrix['TranslateX'] / 20,
		str_repeat("\t", $indent),
		$matrix['RotateSkew1'] / 0x10000 / 20,
		$matrix['ScaleY'] / 0x10000 / 20,
		$matrix['TranslateY'] / 20);
    }
}
