<?php

/*
 * 2011/9/9- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/FILTER.php';

class IO_SWF_Type_FILTERLIST extends IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
    	$filterlist = array();
    	$NumberOfFilters = $reader->getUI8();
    	$filterlist['NumberOfFilters'] = $NumberOfFilters;
        $filter = array();
        for ($i = 0 ; $i < $NumberOfFilters ; $i++) {
            $filter []= IO_SWF_Type_FILTER::parse($reader, $opts);
        }
        $filterlist['Filter'] = $filter;
    	return $filterlist;
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
