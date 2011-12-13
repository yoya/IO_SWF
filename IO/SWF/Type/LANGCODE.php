<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_LANGCODE extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$langcode = array();
    	$langcode['LanguageCode'] = $reader->getUI8();
    	return $langcode;
    }
    static function build(&$writer, $langcode, $opts = array()) {
    	$writer->putUI8($langcode['LanguageCode']);
    }
    static function string($langcode, $opts = array()) {
        $languageCode = $langcode['LanguageCode'];
        switch ($languageCode) {
        case 1:
            $language = 'Latin';
        case 2:
            $language = 'Japanese';
        case 3:
            $language = 'Korean';
        case 4:
            $language = 'Simplified Chinese';
        case 5:
            $language = 'Traditional Chinese';
        default:
            $language = 'Unknown';
        }
        return "$languageCode($language)";
    }
}
