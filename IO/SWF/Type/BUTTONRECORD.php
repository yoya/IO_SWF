<?php

/*
 * 2011/7/9- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/CXFORM.php';
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

    static function build(&$writer, $buttonrecord, $opts = array()) {
        $writer->putUIBits(0, 2); // ButtonReserved
        $buttonHasBlendMode = $buttonrecord['ButtonHasBlandMode'];
        $buttonHasFilterList = $buttonrecord['ButtonHasFilterList'];
        $writer->putUIBit($buttonHasBlendMode);
        $writer->putUIBit($buttonHasFilterList);
        $writer->putUIBit($buttonrecord['ButtonStateHitTest']);
        $writer->putUIBit($buttonrecord['ButtonStateDown']);
        $writer->putUIBit($buttonrecord['ButtonStateOver']);
        $writer->putUIBit($buttonrecord['ButtonStateUp']);
        //
        $writer->putUI16LE($buttonrecord['CharacterID']);
        $writer->putUI16LE($buttonrecord['PlaceDepth']);
        IO_SWF_Type_MATRIX::build($writer, $buttonrecord['PlaceMatrix']);
        if ($opts['tagCode'] == 34) { // DefineButton2
            IO_SWF_Type_CXFORMWITHALPHA::build($writer, $buttonrecord['ColorTransform']);
        } else {
            IO_SWF_Type_CXFORM::build($writer, $buttonrecord['ColorTransform']);
        }
        if (($opts['tagCode'] == 34) &&  // DefineButton2
            ($buttonHasFilterList == 1)) {
            IO_SWF_Type_FILTERLIST::build($writer, $buttonrecord['FilterList']);
        }
        if (($opts['tagCode'] == 34) &&  // DefineButton2
            ($buttonHasBlendMode == 1)) {
            $writer->putUI8($buttonrecord['BlendMode']);
        }
    }
    static function string($buttonrecord, $opts = array()) {
        $text = '';
        $buttonHasBlendMode = $buttonrecord['ButtonHasBlandMode'];
        $buttonHasFilterList = $buttonrecord['ButtonHasFilterList'];
        echo 'ButtonHasBlandMode:'.$buttonrecord['ButtonHasBlandMode'].' ButtonHasFilterList:'. $buttonrecord['ButtonHasFilterList']."\n";
        foreach (array('ButtonStateHitTest', 'ButtonStateDown', 'ButtonStateOver', 'ButtonStateUp') as $label) {
            $text .= $label.':'.$buttonrecord[$label].' ';
        }
        $text .= "\n";
        $text .= IO_SWF_Type_MATRIX::string($buttonrecord['PlaceMatrix']);
        if ($opts['tagCode'] == 34) { // DefineButton2
            $text .= 'ColorTransform:'. IO_SWF_Type_CXFORMWITHALPHA::string($buttonrecord['ColorTransform']).' ';
        } else {
            $text .= 'ColorTransform:'.IO_SWF_Type_CXFORM::string($buttonrecord['ColorTransform']).' ';
        }
        if (($opts['tagCode'] == 34) &&  // DefineButton2
            ($buttonHasFilterList == 1)) {
            $text .= 'FilterList:'.IO_SWF_Type_FILTERLIST::string($buttonrecord['FilterList'])."\n";
        }
        if (($opts['tagCode'] == 34) &&  // DefineButton2
            ($buttonHasBlendMode == 1)) {
            $text .= 'BlendMode:'.$buttonrecord['BlendMode']."\n";
        }
        return $text;
    }
}
