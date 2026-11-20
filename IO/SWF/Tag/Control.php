<?php

/*
 * 構造の単純な制御タグ
 *   Protect (24)          : 本体は仕様上なし。Flash 5 以降はパスワード (MD5) が
 *                           付くことがあるが形式が処理系依存なので生データで保持
 *   EnableDebugger (58)   : Password STRING
 *   EnableDebugger2 (64)  : Reserved UI16, Password STRING
 *   ScriptLimits (65)     : MaxRecursionDepth UI16, ScriptTimeoutSeconds UI16
 *   SetTabIndex (66)      : Depth UI16, TabIndex UI16
 *   Metadata (77)         : Metadata STRING (XML)
 *   DefineScalingGrid (78): CharacterId UI16, Splitter RECT
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Exception.php';
require_once dirname(__FILE__).'/../Type/String.php';
require_once dirname(__FILE__).'/../Type/RECT.php';

class IO_SWF_Tag_Control extends IO_SWF_Tag_Base {
    var $_data = null;            // Protect
    var $_reserved = null;        // EnableDebugger2
    var $_password = null;        // EnableDebugger / EnableDebugger2
    var $_maxRecursionDepth = null, $_scriptTimeoutSeconds = null; // ScriptLimits
    var $_depth = null, $_tabIndex = null;                         // SetTabIndex
    var $_metadata = null;        // Metadata
    var $CharacterID = null;      // DefineScalingGrid (CharacterId)
    var $_splitter = null;        // DefineScalingGrid
    var $_rest = null;            // 想定外の後続データ (そのまま保持)

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        switch ($tagCode) {
          case 24: // Protect
            $this->_data = $content;
            return;
          case 58: // EnableDebugger
            $this->_password = IO_SWF_Type_String::parse($reader);
            break;
          case 64: // EnableDebugger2
            $this->_reserved = $reader->getUI16LE();
            $this->_password = IO_SWF_Type_String::parse($reader);
            break;
          case 65: // ScriptLimits
            $this->_maxRecursionDepth = $reader->getUI16LE();
            $this->_scriptTimeoutSeconds = $reader->getUI16LE();
            break;
          case 66: // SetTabIndex
            $this->_depth = $reader->getUI16LE();
            $this->_tabIndex = $reader->getUI16LE();
            break;
          case 77: // Metadata
            $this->_metadata = IO_SWF_Type_String::parse($reader);
            break;
          case 78: // DefineScalingGrid
            $this->CharacterID = $reader->getUI16LE();
            $this->_splitter = IO_SWF_Type_RECT::parse($reader);
            break;
          default:
            throw new IO_SWF_Exception("IO_SWF_Tag_Control: unsupported tagCode:$tagCode");
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        switch ($tagCode) {
          case 24:
            $len = strlen($this->_data);
            if ($len === 0) {
                echo "\tProtect: (no password)\n";
            } else {
                // 制御文字を含み得るので印字可能な文字以外は '.' にする
                $printable = preg_replace('/[^\x20-\x7e]/', '.', $this->_data);
                echo "\tProtect: $len bytes: $printable\n";
            }
            break;
          case 58:
          case 64:
            echo "\tPassword: {$this->_password}\n";
            break;
          case 65:
            echo "\tMaxRecursionDepth: {$this->_maxRecursionDepth}  ScriptTimeoutSeconds: {$this->_scriptTimeoutSeconds}\n";
            break;
          case 66:
            echo "\tDepth: {$this->_depth}  TabIndex: {$this->_tabIndex}\n";
            break;
          case 77:
            echo "\tMetadata: {$this->_metadata}\n";
            break;
          case 78:
            echo "\tCharacterId: {$this->CharacterID}  Splitter: ".IO_SWF_Type_RECT::string($this->_splitter)."\n";
            break;
        }
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        switch ($tagCode) {
          case 24:
            return $this->_data;
          case 58:
            IO_SWF_Type_String::build($writer, $this->_password);
            break;
          case 64:
            $writer->putUI16LE($this->_reserved);
            IO_SWF_Type_String::build($writer, $this->_password);
            break;
          case 65:
            $writer->putUI16LE($this->_maxRecursionDepth);
            $writer->putUI16LE($this->_scriptTimeoutSeconds);
            break;
          case 66:
            $writer->putUI16LE($this->_depth);
            $writer->putUI16LE($this->_tabIndex);
            break;
          case 77:
            IO_SWF_Type_String::build($writer, $this->_metadata);
            break;
          case 78:
            $writer->putUI16LE($this->CharacterID);
            IO_SWF_Type_RECT::build($writer, $this->_splitter);
            break;
          default:
            throw new IO_SWF_Exception("IO_SWF_Tag_Control: unsupported tagCode:$tagCode");
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
