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
    var $_labels = array();
    var $_branches = array();

    static function actionLength($action) {
        $length = 1;
        if ($action['Code'] >= 0x80) {
            $length += 2 + $action['Length'];
        }
        return $length;
    }

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

        $label_num = 0;
        for ($i = 0; $i < count($this->_actions); $i++) {
            $action = $this->_actions[$i];
            if ($action['Code'] == 0x99 || $action['Code'] == 0x9D) {
                if ($action['Code'] == 0x99) {  // Jump
                    $branch_offset = $action['BranchOffset'];
                }
                if ($action['Code'] == 0x9D) {  // If
                    $branch_offset = $action['Offset'];
                }
                $offset = 0;
                $j = $i + 1;
                if ($branch_offset > 0) {
                    while ($offset != $branch_offset) {
                        $offset += IO_SWF_Tag_Action::actionLength(
                            $this->_actions[$j]);
                        $j++;
                    }
                } else {
                    while ($offset != $branch_offset) {
                        $offset -= IO_SWF_Tag_Action::actionLength(
                            $this->_actions[$j - 1]);
                        $j--;
                    }
                }
                if (isset($this->_labels[$j])) {
                    // More than two If / Jump to a label.
                    $this->_branches[$i] = $this->_labels[$j];
                } else {
                    $this->_branches[$i] = $this->_labels[$j] = $label_num;
                }
                $label_num++;
            }
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    Actions:";
        if ($tagCode == 59) { // DoInitAction
            echo " SpriteID=".$this->_spriteId;
        }
        echo "\n";
        for ($i = 0; $i < count($this->_actions); $i++) {
            $action = $this->_actions[$i];
            if (isset($opts['addlabel']) && $opts['addlabel']
                && isset($this->_labels[$i])) {
                echo "    (LABEL" . $this->_labels[$i] . "):\n";
            }
            $action_str = IO_SWF_Type_Action::string($action);
            if (isset($opts['addlabel']) && $opts['addlabel']
                && isset($this->_branches[$i])) {
                echo "\t$action_str (LABEL" . $this->_branches[$i] . ")\n";
            } else {
                echo "\t$action_str\n";
            }
        }
        if (isset($opts['addlabel']) && $opts['addlabel']
            && isset($this->_labels[$i])) {
            echo "    (LABEL" . $this->_labels[$i] . "):\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode == 59) { // DoInitAction
            $writer->putUI16LE($this->_spriteId);
        }
        for ($i = 0; $i < count($this->_actions); $i++) {
            $action = $this->_actions[$i];
            if ($action['Code'] == 0x99 || $action['Code'] == 0x9D) {  // Jump
                // Find label to jump
                for ($j = 0; $j <= count($this->_actions); $j++) {
                    if (isset($this->_labels[$j])
                        && $this->_labels[$j] == $this->_branches[$i]) {
                        break;
                    }
                }

                // Calculate new offset
                $branch_offset = 0;
                if ($i < $j) {
                    for ($k = $i + 1; $k < $j; $k++) {
                        $branch_offset += IO_SWF_Tag_Action::actionLength(
                            $this->_actions[$k]);
                    }
                } else {
                    for ($k = $i; $k >= $j; $k--) {
                        $branch_offset -= IO_SWF_Tag_Action::actionLength(
                            $this->_actions[$k]);
                    }
                }
                if ($action['Code'] == 0x99) {  // Jump
                    $action['BranchOffset'] = $branch_offset;
                }
                if ($action['Code'] == 0x9D) {  // If
                    $action['Offset'] = $branch_offset;
                }
            }
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

    function insertAction($pos, $action) {
        array_splice($this->_actions, $pos - 1, 0, array($action));

        $labels = array();
        $branches = array();

        foreach ($this->_labels as $key => $value) {
            if ($key < $pos - 1) {
                $labels[$key] = $value;
            } else {
                $labels[$key + 1] = $value;
            }
        }
        $this->_labels = $labels;

        foreach ($this->_branches as $key => $value) {
            if ($key < $pos - 1) {
                $branches[$key] = $value;
            } else {
                $branches[$key + 1] = $value;
            }
        }
        $this->_branches = $branches;
    }
}
