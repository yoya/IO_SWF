<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_RECT extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $frameSize = array();
        $nBits = $reader->getUIBits(5);
        $frameSize['Xmin'] = $reader->getSIBits($nBits);
        $frameSize['Xmax'] = $reader->getSIBits($nBits);
        $frameSize['Ymin'] = $reader->getSIBits($nBits);
        $frameSize['Ymax'] = $reader->getSIBits($nBits) ;
    	return $frameSize; 
    }
    static function build(&$writer, $frameSize, $opts = array()) {
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
    static function string($rect, $opts = array()) {
        return "Xmin: ".($rect['Xmin'] / 20).
               " Xmax: ".($rect['Xmax'] / 20).
               " Ymin: ".($rect['Ymin'] / 20).
               " Ymax: ".($rect['Ymax'] / 20);
    }
}
