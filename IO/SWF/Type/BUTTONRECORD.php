<?php

/*
 * 2011/7/9- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/CXFORMWITHALPHA.php';

class IO_SWF_Type_BUTTONRECORD extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$buttonrecord = array();

        $buttonrecord['ButtonReserved'] = $reader->getUIBits(2); // must be 0
	$buttonHasBlendMode = $reader->getUIBit();
	$buttonHasFilterList = $reader->getUIBit(); 
        $buttonrecord['ButtonHasBlandMode'] = $buttonHasBlendMode;
        $buttonrecord['ButtonHasFilterList'] = $buttonHasFilterList;
        $buttonrecord['ButtonStateHitTest'] = $reader->getUIBit(); 
        $buttonrecord['ButtonStateDown'] = $reader->getUIBit(); 
        $buttonrecord['ButtonStateOver'] = $reader->getUIBit(); 
        $buttonrecord['ButtonStateUp'] = $reader->getUIBit(); 
	//
	$buttonrecord['CharacterID'] = $reader->getUI16LE();
	$buttonrecord['PlaceDepth'] = $reader->getUI16LE();
	$buttonrecord['PlaceMatrix'] = IO_SWF_Type_MATRIX::parse($reader);
	if ($opts['tagCode'] == 34) { // DefineButton2
	    $buttonrecord['ColorTransform'] = IO_SWF_Type_CXFORMWITHALPHA::parse($reader);
	} else {
	    $buttonrecord['ColorTransform'] = IO_SWF_Type_CXFORM::parse($reader);
	}
	if (($opts['tagCode'] == 34) &&  // DefineButton2
	    ($buttonHasFilterList == 1)) {
	    $buttonrecord['FilterList'] = IO_SWF_Type_FILTERLIST::parse($reader);
	}
	if (($opts['tagCode'] == 34) &&  // DefineButton2
	    ($buttonHasBlendMode == 1)) {
	    $buttonrecord['BlendMode'] = $reader->getUI8();
        }
    	return $buttonrecord;
    }
    static function build(&$writer, $cxform, $opts = array()) {
        $nbits = 0;
        $hasAddTerms = 0;
        $hasMultiTerms = 0;
        $multi_term_list = array('RedMultiTerm', 'GreenMultiTerm', 'BlueMultiTerm');
        foreach ($multi_term_list as $term) {
            if (isset($cxform[$term])) {
                $hasMultiTerms = 1;
                $need_bits = $writer->need_bits_signed($cxform[$term]);
                if ($nbits < $need_bits){
                    $nbits = $need_bits;
                }
	    }
	}
	$add_term_list = array('RedAddTerm', 'GreenAddTerm', 'BlueAddTerm');
	foreach ($add_term_list as $term) {
	    if (isset($cxform[$term])) {
	        $hasAddTerms = 1;
            $need_bits = $writer->need_bits_signed($cxform[$term]);
            if ($nbits < $need_bits){
                $nbits = $need_bits;
            }
	    }
	}
	$writer->putUIBit($hasAddTerms);
	$writer->putUIBit($hasMultiTerms);
	$writer->putUIBits($nbits, 4);
	if ($hasMultiTerms) {
	    $writer->putSIBits($cxform['RedMultiTerm'],   $nbits);
	    $writer->putSIBits($cxform['GreenMultiTerm'], $nbits);
	    $writer->putSIBits($cxform['BlueMultiTerm'],  $nbits);
	}
	if ($hasAddTerms) {
	  $writer->putSIBits($cxform['RedAddTerm'],   $nbits);
	  $writer->putSIBits($cxform['GreenAddTerm'], $nbits);
	  $writer->putSIBits($cxform['BlueAddTerm'],  $nbits);
	}
    }
    static function string($cxform, $opts = array()) {
        if (($cxform['HasMultiTerms'] == 0) && ($cxform['HasAddTerms'] == 0)) {
            return '(No Data: CXFORM)';
        }
        $text = '';
        if ($cxform['HasMultiTerms']) {
            $text .= sprintf("MultiTerms:(%d,%d,%d)", $cxform['RedMultiTerm'], $cxform['GreenMultiTerm'], $cxform['BlueMultiTerm']);
        }
        if ($cxform['HasAddTerms']) {
            if ($cxform['HasMultiTerms']) {
                $text .= ' ';
            }
            $text .= sprintf("AddTerms:(%d,%d,%d)", $cxform['RedAddTerm'], $cxform['GreenAddTerm'], $cxform['BlueAddTerm']);
        }
        return $text;
    }
}
