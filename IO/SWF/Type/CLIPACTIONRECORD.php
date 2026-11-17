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
        if (! empty($clipactionrecord['EventFlags']['ClipEventKeyPress'])) { // SWF6-
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
        // ActionRecordSize は (KeyCode +) Actions + ActionEndFlag のバイト数。
        // アクションの編集や再エンコードでサイズが変わり得るので、
        // parse 時の値を使わず、ここで組み立ててから計算する。
        $actions_writer = new IO_Bit();
        foreach ($clipactionrecord['Actions'] as $action) {
            IO_SWF_Type_Action::build($actions_writer, $action);
        }
        $actions_writer->putUI8(0); // ActionEndFlag
        $actions_data = $actions_writer->output();
        $hasKeyCode = ! empty($clipactionrecord['EventFlags']['ClipEventKeyPress']); // SWF6-
        $actionRecordSize = ($hasKeyCode ? 1 : 0) + strlen($actions_data);
        $writer->putUI32LE($actionRecordSize);
        if ($hasKeyCode) {
            $writer->putUI8($clipactionrecord['KeyCode']);
        }
        $writer->putData($actions_data);
    }
    static function string($clipactionrecord, $opts = array()) {
        $text = '';
        $text .= IO_SWF_Type_CLIPEVENTFLAGS::string($clipactionrecord['EventFlags'], $opts);
        $text .= "\n";
        $text .= "    Actions:\n";
        foreach ($clipactionrecord['Actions'] as $action) {
            $text .= "    ";
            $text .= IO_SWF_Type_Action::string($action, $opts);
            $text .= "\n";
        }
    	return $text;
    }
}
