<?php

/*
 * 2011/7/11- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_CLIPEVENTFLAGS extends IO_SWF_Type {
  static $clipevent_list =  array(
            'KeyUp', 'KeyDown', 'MouseUp', 'MouseDown', 'MouseMove',
            'Unload', 'EnterFrame', 'Load', 'DragOver',
            'RollOut', 'RollOver',
            'ReleaseOutside', 'Release', 'Press', 'Initialize', 'Data');
    static function parse(&$reader, $opts = array()) {
    	$clipeventflags = array();
        foreach (self::$clipevent_list as $key) {
            $clipeventflags['ClipEvent'.$key] = $reader->getUIBit();
        }
        if ($opts['Version'] >= 6) {
            $clipeventflags['Reserved'] = $reader->getUIBits(6);
            $clipeventflags['ClipEventKeyConstruct'] = $reader->getUIBit();
            $clipeventflags['ClipEventKeyPress'] = $reader->getUIBit();
            $clipeventflags['ClipEventDragOut'] = $reader->getUIBit();
            $clipeventflags['Reserved2'] = $reader->getUIBits(8);
        }
    	return $clipeventflags;
    }
    static function build(&$writer, $clipeventflags, $opts = array()) {
        foreach (self::$clipevent_list as $key) {
            $writer->putUIBit($clipeventflags['ClipEvent'.$key]);
        }
        if ($opts['Version'] >= 6) {
            $writer->putUIBits($clipeventflags['Reserved'], 6);
            $writer->putUIBit($clipeventflags['ClipEventConstruct']);
            $writer->putUIBit($clipeventflags['ClipEventKeyPress']);
            $writer->putUIBit($clipeventflags['ClipEventDragOut']);
            $writer->putUIBits($clipeventflags['Reserved2'], 8);
        }
    }
    static function string($clipeventflags, $opts = array()) {
        $text = "ClipEvent: ";
        if ($opts['Version'] <= 5) {
            $clipevent_list = self::$clipevent_list;
        } else {
            $clipevent_list = self::$clipevent_list + array('Construct', 'KeyPress', 'DragOut');
        }
        foreach ($clipevent_list as $key) {
            if ($clipeventflags['ClipEvent'.$key] == 1) {
                $text .= $key.' ';
            }
        }
    	return $text;
    }
}
