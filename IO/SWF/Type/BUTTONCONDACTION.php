<?php

/*
 * 2011/4/15- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_BUTTONCONDACTION extends IO_SWF_Type {
    static $buttoncond_list =  array(
        'IdleToOverDown', 'OutDownToIdle', 'OutDownToOverDown',
        'OverDownToOutDown', 'OverDownToOver', 'OverUpToOverDown',
        'OverUpToIdle', 'IdleToOverUp');
    static function parse(&$reader, $opts = array()) {
        $condAction = array();
        $condAction['CondActionSize'] = $reader->getUI16LE();
        foreach (self::$buttoncond_list as $key) {
            $condAction['Cond'.$key] = $reader->getUIBit();
        }
        $condAction['CondKeyPress'] = $reader->getUIBits(7);
        $condAction['CondOverDownToIdle'] = $reader->getUIBit();
        if ($reader->hasNextData()) { // XXX (depends on ActionOffset
            $actions = array();
            while ($reader->getUI8() != 0) {
                $reader->incrementOffset(-1, 0); // 1 byte back
                $actions []= IO_SWF_Type_Action::parse($reader);
            }
            $condAction['Actions'] = $actions;
        }
    	return $condAction; 
    }
    static function build(&$writer, $condAction, $opts = array()) {
        list($offset_condAction, $dummy) = $writer->getOffset();
        $writer->putUI16LE(0);
        foreach (self::$buttoncond_list as $key) {
            $writer->putUIBit($condAction['Cond'.$key]);
        }
        $writer->putUIBits($condAction['CondKeyPress'], 7);
        $writer->putUIBit($condAction['CondOverDownToIdle']);
        foreach ($condAction['Actions'] as $action) {
            IO_SWF_Type_Action::build($writer, $action);
        }
        $writer->putUI8(0); // terminate
        if ($opts['lastAction'] === false) {
            list($offset_next, $dummy) = $writer->getOffset();
            $writer->setUI16LE($offset_next - $offset_condAction, $offset_condAction);
        }
        return true;
    }
    static function string($condAction, $opts = array()) {
        $text = "\tBUTTONCONDACTION (CondActionSize:{$condAction['CondActionSize']})\n";

        $text .= "\t\tCondAction: ";
        foreach (self::$buttoncond_list as $key) {
            $text .= " $key:".$condAction['Cond'.$key];
        }
        $text .= "\n";
        $text .= "\t\tCondKeyPress:".$condAction['CondKeyPress']." CondOverDownToIdle:".$condAction['CondOverDownToIdle']."\n";
        
        
        $text .= "\t\tActions:\n";
        foreach ($condAction['Actions'] as $action) {
            $text .= "\t\t\t".IO_SWF_Type_Action::string($action, $opts)."\n";
        }
        return $text;
    }
}
