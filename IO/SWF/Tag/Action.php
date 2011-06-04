<?php

/*
 * 2011/06/03- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/Action.php';

class IO_SWF_Tag_Action extends IO_SWF_Tag_Base {
    var $_actions = array();

   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        while ($reader->getUI8() != 0) {
            $reader->incrementOffset(-1, 0); // 1 byte back
            $action = IO_SWF_Type_Action::parse($reader);
            $this->_actions [] = $action;
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "  Actions:\n";
        foreach ($this->_actions as $action) {
            $action_str = IO_SWF_Type_Action::string($action);
            echo "\t$action_str\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        foreach ($this->_actions as $action) {
            IO_SWF_Type_RGB::build($writer, $action);
        }
        $writer->putUI8(0);
    	return $writer->output();
    }
}
