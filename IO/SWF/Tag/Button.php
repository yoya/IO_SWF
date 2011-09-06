<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Tag.php';
require_once dirname(__FILE__).'/../Type/BUTTONRECORD.php';
require_once dirname(__FILE__).'/../Type/BUTTONCONDACTION.php';
require_once dirname(__FILE__).'/../Type/Action.php';

class IO_SWF_Tag_Button extends IO_SWF_Tag_Base {
    var $_buttonId = null;
    var $_reservedFlags = null;
    var $_trackAsMenu = null;
    var $_actionOffset = null;
    var $_characters = null;
    var $_actions = null;
    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_buttonId = $reader->getUI16LE();
        $opts['tagCode'] = $tagCode;
        if ($tagCode == 34) { // DefineButton2
            $this->_reservedFlags = $reader->getUIBits(7);
            $this->_trackAsMenu = $reader->getUIBit();
            list($offset_actionOffset, $dummy) = $reader->getOffset();
            $this->_actionOffset = $reader->getUI16LE();
        }
        $characters = array();
        while ($reader->getUI8() != 0) {
            $reader->incrementOffset(-1, 0); // 1 byte back
            $characters []= IO_SWF_Type_BUTTONRECORD::parse($reader, $opts);
        }
        $this->_characters = $characters;
        if ($tagCode == 34) { // DefineButton2
            // TODO: skip ActionOffset - CurrentOffsetUntilCharactersField
            $actions = array();
            if ($this->_actionOffset > 0) {
                list($offset_buttonCondition, $dummy) = $reader->getOffset();
                if ($offset_actionOffset + $this->_actionOffset != $offset_buttonCondition) {
                    // TODO: warning
                    $reader->setOffset($offset_actionOffset + $this->_actionOffset, 0);
                }
                while (true) {
                    $action  = IO_SWF_Type_BUTTONCONDACTION::parse($reader);
                    $actions []= $action;
                    if ($action['CondActionSize'] == 0) {
                        break; // last action
                    }
                }
                $this->_actions = $actions;
            } else {
                $this->_actions = null;
            }
        } else {
            $actions = array();
            while ($reader->getUI8() != 0) {
                $reader->incrementOffset(-1, 0); // 1 byte back
                $actions []= IO_SWF_Type_Action::parse($reader);
            }
            $this->_actions = $actions;
        }
        return true;
    }
    
    function dumpContent($tagCode, $opts = array()) {
        $opts['tagCode'] = $tagCode;
        echo "\tButtonID:{$this->_buttonId} TrackAsMenu:{$this->_trackAsMenu} (ActionOffset:{$this->_actionOffset})\n";
        echo "\t    Characters:\n";
        foreach ($this->_characters as $character) {
            $buttonrecord_str = IO_SWF_Type_BUTTONRECORD::string($character, $opts);
            echo "\t\t$buttonrecord_str\n";
        }
        echo "\t    Actions:\n";
        if ($tagCode == 34) { // DefineButton2
            foreach ($this->_actions as $action) {
                $action_str = IO_SWF_Type_BUTTONCONDACTION::string($action, $opts);
                echo "\t\t$action_str\n";
            }
        } else {
            foreach ($this->_actions as $action) {
                $action_str = IO_SWF_Type_Action::string($action, $opts);
                echo "\t\t$action_str\n";
            }
        }
    }
    
    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->_buttonId);
        $opts['tagCode'] = $tagCode;        ;
        if ($tagCode == 34) { // DefineButton2
            $writer->putUIBits($this->_trackAsMenu, 7);
            $writer->putUIBit($this->_characters);
            list($offset_actionOffset, $dummy) = $writer->getOffset();
            $writer->putUI16LE(0); // dummy;
        }
        foreach ($this->_characters as $character) {
            IO_SWF_Type_BUTTONRECORD::build($writer, $character, $opts);
        }
        $writer->putUI8(0); // terminater of button record
        if ($tagCode == 34) { // DefineButton2
            $actions = array();
            if (is_null($this->_actions) === false) {
                list($offset_buttonCondition, $dummy) = $writer->getOffset();
                $writer->setUI16LE($offset_buttonCondition - $offset_actionOffset, $offset_buttonCondition);
                foreach ($this->_actions as $idx => $action) {
                    if (isset($this->_actions[$idx + 1]) === false) {
                        $opts['lastAction'] = true;
                    } else {
                        $opts['lastAction'] = false;
                    }
                    IO_SWF_Type_BUTTONCONDACTION::build($writer, $action, $opts);
                }
            }
        } else {
            foreach ($this->_actions as $action) {
                IO_SWF_Type_Action::build($writer, $action);
            }
            $writer->putUI8(0); // terminator of actions
            
        }
    	return $writer->output();
    }
}
