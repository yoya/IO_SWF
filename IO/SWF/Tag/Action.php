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
    var $_byteOffsetTable = array();
    var $_byteSizeTable = array();

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
        $i = 0;
        while ($reader->hasNextData(1)) {
            list($byteOffset, $dummy) = $reader->getOffset();
            $action = IO_SWF_Type_Action::parse($reader);
            list($nextByteOffset, $dummy) = $reader->getOffset();
            //
            $this->_byteOffsetTable[$i] = $byteOffset;
            $this->_byteSizeTable[$i] = $nextByteOffset - $byteOffset;
            $recordOffsetToByteOffset[$i] = $byteOffset;
            $byteOffsetToRecordOffset[$byteOffset] = $i;
            //
            $this->_actions [] = $action;
            $i++;
        }
        if ($i > 0) {
            $recordOffsetToByteOffset[$i] = $nextByteOffset;
            $byteOffsetToRecordOffset[$nextByteOffset] = $i;
            $byteOffsetToRecordOffset[$nextByteOffset] = $i;
        }

        $label_num = 0;
        foreach ($this->_actions as $i => $action) {
            if ($action['Code'] == 0x99) {  // Jump
                $branch_offset = $action['BranchOffset'];
            } else if ($action['Code'] == 0x9D) {  // If
                $branch_offset = $action['Offset'];
            } else {
                continue;
            }
            $targetByteOffset = $recordOffsetToByteOffset[$i + 1] + $branch_offset;
            if (isset($byteOffsetToRecordOffset[$targetByteOffset])) {
                $targetRecordOffset = $byteOffsetToRecordOffset[$targetByteOffset];
                if (isset($this->_labels[$targetRecordOffset]) === false) {
                    $this->_labels[$targetRecordOffset] = $targetRecordOffset;
                }
                $this->_branches[$i] = $this->_labels[$targetRecordOffset];
            }
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "    Actions:";
        if ($tagCode == 59) { // DoInitAction
            echo " SpriteID=".$this->_spriteId;
        }
        echo "\n";

        foreach ($this->_actions as $i => $action) {
            if (isset($opts['addlabel']) && $opts['addlabel']
                && isset($this->_labels[$i])) {
                echo "    (LABEL: " . $this->_labels[$i] . "):\n";
            }
            $action_str = IO_SWF_Type_Action::string($action);
            if (isset($opts['addlabel']) && $opts['addlabel']
                && isset($this->_branches[$i])) {
                echo "\t[$i] $action_str (LABEL: " . $this->_branches[$i] . ")\n";
            } else {
                echo "\t[$i] $action_str\n";
            }
        }
        if (count($this->_actions) > 0) {
            if (isset($opts['addlabel']) && $opts['addlabel']
                && isset($this->_labels[$i])) {
                echo "    (LABEL" . $this->_labels[$i] . "):\n";
            }
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode == 59) { // DoInitAction
            $writer->putUI16LE($this->_spriteId);
        }
        $action = null;
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
        if ((is_null($action) === false) && ($action['Code'] !== 0)) {
            $writer->putUI8(0); // ActionEndFlag
        }
    	return $writer->output();
    }

    function replaceActionStrings($trans_table) {
        $replaced = false;
        foreach ($this->_actions as &$action) {
            if (IO_SWF_Type_Action::replaceActionString($action, $trans_table)) {
                $replaced = true;
            }
        }
        unset($action);
        return $replaced;
    }

    function insertAction($pos, $action) {
        array_splice($this->_actions, $pos, 0, array($action));

        $labels = array();
        $branches = array();

        foreach ($this->_labels as $key => $value) {
            if ($key < $pos) {
                $labels[$key] = $value;
            } else {
                $labels[$key + 1] = $value;
            }
        }
        $this->_labels = $labels;

        foreach ($this->_branches as $key => $value) {
            if ($key < $pos) {
                $branches[$key] = $value;
            } else {
                $branches[$key + 1] = $value;
            }
        }
        $this->_branches = $branches;
    }
}
