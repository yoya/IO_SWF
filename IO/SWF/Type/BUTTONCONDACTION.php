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
        ;
    }
    static function string($condAction, $opts = array()) {
        $text = "\tBUTTONCONDACTION (CondActionSize:{$condAction['CondActionSize']})\n";
        return $text;
    }
}
