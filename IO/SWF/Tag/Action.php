<?php

/*
 * 2011/06/03- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/Action.php';

class IO_SWF_Tag_Action extends IO_SWF_Tag_Base {
    var $_actions = array();
    var $_spriteId = null; // DoInitAction
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        if ($tagCode == 59) { // DoInitAction
            $this->_spriteId = $reader->getUI16LE();
        }
        while ($reader->getUI8() != 0) {
            $reader->incrementOffset(-1, 0); // 1 byte back
            $action = IO_SWF_Type_Action::parse($reader);
            $this->_actions [] = $action;
        }
        // ActionEndFlag
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    Actions:";
        if ($tagCode == 59) { // DoInitAction
            echo " SpriteID=".$this->_spriteId;
        }
        echo "\n";
        foreach ($this->_actions as $action) {
            $action_str = IO_SWF_Type_Action::string($action);
            echo "\t$action_str\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode == 59) { // DoInitAction
            $writer->putUI16LE($this->_spriteId);
        }
        foreach ($this->_actions as $action) {
            IO_SWF_Type_Action::build($writer, $action);
        }
        $writer->putUI8(0); // ActionEndFlag
    	return $writer->output();
    }
    function replaceActionStrings($trans_table) {
        foreach ($this->_actions as &$action) {
            switch($action['Code']) {
            case 0x83: // ActionGetURL
                ;
                if (isset($trans_table[$action['UrlString']])) {
                    $action['UrlString'] = $trans_table[$action['UrlString']];
                }
                if (isset($trans_table[$action['TargetString']])) {
                    $action['TargetString'] = $trans_table[$action['TargetString']];
                }
                break;
            case 0x88: // ActionConstantPool
                foreach ($action['ConstantPool'] as $idx_cp => $cp) {
                    if (isset($trans_table[$cp])) {
                        $action['ConstantPool'][$idx_cp] = $trans_table[$cp];
                    }
                }
                break;
            case 0x96: // ActionPush
                foreach ($action['Values'] as &$value) {
                    if ($value['Type'] == 0) { // Type String
                        if (isset($trans_table[$value['String']])) {
                            $value['String'] = $trans_table[$value['String']];
                        }
                    }
                }
                break;
                
            }
            
        }
        // don't touch $action(reference), danger!
    }
}
