<?php

/*
 * 2020/01/28- (c) yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../ABC.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_ABC extends IO_SWF_Tag_Base {
    var $_Flags = null;
    var $_Name = null;
    var $_ABCData = null;
    var $_ABC = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $opts['abcdump'] = ! empty($opts['abcdump']);
        $opts['abcparse'] = ! empty($opts['abcparse']);
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_Flags = $reader->getUI32LE();
        $this->_Name = IO_SWF_Type_String::parse($reader);
        $this->_ABCData = $reader->getDataUntil(false);
        if ($opts['abcparse'] || $opts['abcdump']) {
            $abc = new IO_SWF_ABC();
            $abc->parse($this->_ABCData);
            $this->_ABC = $abc;
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $opts['abcdump'] = ! empty($opts['abcdump']);
        printf("    Flags: 0x%08x", $this->_Flags);
        echo "  Name: ".$this->_Name;
        echo "  ABCData (len:".strlen($this->_ABCData).")";
        echo "\n";
        if ($opts['abcdump']) {
            $abc = $this->_ABC;
            $abc->dump($opts);
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI32LE($this->_Flags);
        IO_SWF_Type_String::build($writer, $this->_Name);
        $writer->putData($this->_ABCData);
    	return $writer->output();
    }
}
