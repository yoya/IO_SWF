<?php

/*
 * 2011/7/11- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/Action.php';
require_once dirname(__FILE__).'/CLIPEVENTFLAGS.php';
                              
class IO_SWF_Type_CLIPACTIONRECORD implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $clipactionrecord = array();
        $clipactionrecord['EventFlags'] = IO_SWF_Type_CLIPEVENTFLAGS::parse($reader, $opts);
        $clipactionrecord['ActionRecordSize'] = $reader->getUI32LE();
        if ($clipactionrecord['EventFlags']['ClipEventKeyPress'] == 1) {
            $clipactionrecord['KeyCode'] = $reader->getUI8();
        }
        $actions = array();
        while ($reader->getUI8() != 0) {
            $reader->incrementOffset(-1, 0); // 1 byte back
            $action = IO_SWF_Type_Action::parse($reader);
            $actions [] = $action;
        }
        $clipactionrecord['Actions'] = $actions;
    	return $clipactionrecord;
    }
    static function build(&$writer, $clipactionrecord, $opts = array()) {
        IO_SWF_Type_CLIPEVENTFLAGS::build($writer, $clipactionrecord['EventFlags'], $opts);
        $actionRecordSize = $clipactionrecord['ActionRecordSize']; // XXX
        $writer->putUI32LE($actionRecordSize);
        if ($clipactionrecord['EventFlags']['ClipEventKeyPress'] == 1) {
            $writer->putUI8($clipactionrecord['KeyCode']);
        }
        $actions = array();
        foreach ($clipactionrecord['Actions'] as $action) {
            IO_SWF_Type_Action::build($writer, $action);
        }
        $writer->putUI8(0); // ActionEndFlag
    }
    static function string($clipactionrecord, $opts = array()) {
        $text = '';
        $text .= IO_SWF_Type_CLIPEVENTFLAGS::string($clipactionrecord['EventFlags'], $opts);
        $text .= "\n";
        $text .= "\tActions:\n";
        foreach ($clipactionrecord['Actions'] as $action) {
            $text .= "\t";
            $text .= IO_SWF_Type_Action::string($action, $opts);
            $text .= "\n";
        }
    	return $text;
    }
}
