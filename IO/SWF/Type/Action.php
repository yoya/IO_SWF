<?php

/*
 * 2011/06/03- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/String.php';
require_once dirname(__FILE__).'/Float.php';
require_once dirname(__FILE__).'/Double.php';
                              
class IO_SWF_Type_Action implements IO_SWF_Type {
    static $action_code_table = array(
        //
        // ActionCode only
        //
        0x00 => Array('name' => 'End', 'version' => 4),
        //
        0x04 => Array('name' => 'NextFrame', 'version' => 4),
        0x05 => Array('name' => 'PreviousFrame', 'version' => 4),
        0x06 => Array('name' => 'Play', 'version' => 4),
        0x07 => Array('name' => 'Stop', 'version' => 4),
        0x08 => Array('name' => 'ToggleQuality', 'version' => 4),
        0x09 => Array('name' => 'StopSounds', 'version' => 4),
        //
        0x0A => Array('name' => 'Add', 'version' => 4),
        0x0B => Array('name' => 'Subtract', 'version' => 4),
        0x0C => Array('name' => 'Multiply', 'version' => 4),
        0x0D => Array('name' => 'Divide', 'version' => 4),
        0x0E => Array('name' => 'Equals', 'version' => 4),
        0x0F => Array('name' => 'Less', 'version' => 4),
        0x10 => Array('name' => 'And', 'version' => 4),
        0x11 => Array('name' => 'Or', 'version' => 4),
        0x12 => Array('name' => 'Not', 'version' => 4),
        0x13 => Array('name' => 'StringEquals', 'version' => 4),
        0x14 => Array('name' => 'StringLength', 'version' => 4),
        0x15 => Array('name' => 'StringExtract', 'version' => 4),
        //
        0x17 => Array('name' => 'Pop', 'version' => 4),
        0x18 => Array('name' => 'ToInteger', 'version' => 4),
        //
        0x1C => Array('name' => 'GetVariable', 'version' => 4),
        0x1D => Array('name' => 'SetVariable', 'version' => 4),
        //
        0x20 => Array('name' => 'SetTarget2', 'version' => 4),
        0x21 => Array('name' => 'StringAdd', 'version' => 4),
        0x22 => Array('name' => 'GetProperty', 'version' => 4),
        0x23 => Array('name' => 'SetProperty', 'version' => 4),
        0x24 => Array('name' => 'CloneSprite', 'version' => 4),
        0x25 => Array('name' => 'RemoveSprite', 'version' => 4),
        0x26 => Array('name' => 'Trace', 'version' => 4),
        //
        0x2D => Array('name' => 'FSCommand2', 'version' => 4),// Flash Lite
        //
        0x27 => Array('name' => 'StartDrag', 'version' => 4),
        0x28 => Array('name' => 'EndDrag', 'version' => 4),
        0x29 => Array('name' => 'StringLess', 'version' => 4),
        //
        0x30 => Array('name' => 'RandomNumber', 'version' => 4),
        0x31 => Array('name' => 'MBStringLength', 'version' => 4),
        0x32 => Array('name' => 'CharToAscii', 'version' => 4),
        0x33 => Array('name' => 'AsciiToChar', 'version' => 4),
        0x34 => Array('name' => 'GetTime', 'version' => 4),
        0x35 => Array('name' => 'MBStringExtract', 'version' => 4),
        0x36 => Array('name' => 'MBCharToAscii', 'version' => 4),
        0x37 => Array('name' => 'MBAsciiToChar', 'version' => 4),
        //
        0x3A => Array('name' => 'Delete', 'version' => 5),
        0x3B => Array('name' => 'Delete2', 'version' => 5),
        0x3C => Array('name' => 'DefineLocal', 'version' => 5),
        0x3D => Array('name' => 'CallFunction', 'version' => 5),
        0x3E => Array('name' => 'Return', 'version' => 5),
        0x3F => Array('name' => 'Modulo', 'version' => 5),
        0x40 => Array('name' => 'NewObject', 'version' => 5),
        0x41 => Array('name' => 'DefineLocal2', 'version' => 5),
        0x42 => Array('name' => 'InitArray', 'version' => 5),
        0x43 => Array('name' => 'InitObject', 'version' => 5),
        0x44 => Array('name' => 'TypeOf', 'version' => 5),
        0x45 => Array('name' => 'TargetPath', 'version' => 5),
        0x46 => Array('name' => 'Enumerate', 'version' => 5),
        0x47 => Array('name' => 'Add2', 'version' => 5),
        0x48 => Array('name' => 'Less2', 'version' => 5),
        0x49 => Array('name' => 'Equals2', 'version' => 5),
        0x4A => Array('name' => 'ToNumber', 'version' => 5),
        0x4B => Array('name' => 'ToString', 'version' => 5),
        0x4C => Array('name' => 'PushDuplicate', 'version' => 5), // SWF 5
        0x4D => Array('name' => 'StackSwap', 'version' => 5),
        0x4E => Array('name' => 'GetMember', 'version' => 5),
        0x4F => Array('name' => 'SetMember', 'version' => 5),
        0x50 => Array('name' => 'Increment', 'version' => 5),
        0x51 => Array('name' => 'Decrement', 'version' => 5),
        0x52 => Array('name' => 'CallMethod', 'version' => 5),
        0x53 => Array('name' => 'NewMethod', 'version' => 5),
        0x54 => Array('name' => 'InstanceOf', 'version' => 6),
        0x55 => Array('name' => 'Enumerate2', 'version' => 6),
	//
        0x60 => Array('name' => 'BitAnd', 'version' => 5),
        0x61 => Array('name' => 'BitOr', 'version' => 5),
        0x62 => Array('name' => 'BitXOr', 'version' => 5),
        0x63 => Array('name' => 'BitShift', 'version' => 5),
        0x64 => Array('name' => 'BitURShift', 'version' => 5),
	//
        0x66 => Array('name' => 'StrictEquals', 'version' => 6),
        0x67 => Array('name' => 'Greater', 'version' => 6),
        0x68 => Array('name' => 'StringGreater', 'version' => 6),
        //
        // has Data Payload
        //
        0x81 => Array('name' => 'GotoFrame', 'version' => 4),
        0x83 => Array('name' => 'GetURL', 'version' => 4),
        0x87 => Array('name' => 'StoreRegister', 'version' => 5),
        0x88 => Array('name' => 'ConstantPool', 'version' => 5),
        0x8A => Array('name' => 'WaitForFrame', 'version' => 5),
        0x8B => Array('name' => 'SetTarget', 'version' => 4),
        0x8C => Array('name' => 'GoToLabel', 'version' => 4),
        0x8D => Array('name' => 'WaitForFrame2', 'version' => 4),
        0x8E => Array('name' => 'DefineFunction2', 'version' => 6),
        //
        0x94 => Array('name' => 'With', 'version' => 5), // SWF 5
        0x96 => Array('name' => 'Push', 'version' => 4),
        //
        0x99 => Array('name' => 'Jump', 'version' => 4),
        0x9A => Array('name' => 'GetURL2', 'version' => 4),
        0x9B => Array('name' => 'DefineFunction', 'version' => 5),
        //
        0x9D => Array('name' => 'If', 'version' => 4),
        0x9E => Array('name' => 'Call', 'version' => 4), // why it >=0x80 ?
        0x9F => Array('name' => 'GotoFrame2', 'version' => 4),
        );
    static function getCodeName($code) {
        if (isset(self::$action_code_table[$code])) {
            return self::$action_code_table[$code]['name'];
        } else {
            return "Unknown";
        }
    }
    static function getCodeVersion($code) {
        if (isset(self::$action_code_table[$code])) {
            return self::$action_code_table[$code]['version'];
        } else {
            return false;
        }
    }
    static function parse(&$reader, $opts = array()) {
            $action = array();
        $code = $reader->getUI8();
        $action['Code'] = $code;
        if ($code >= 0x80) {
            $length = $reader->getUI16LE();
            $action['Length'] = $length;
            switch ($code) {
              case 0x81: // ActionGotoFrame
                $action['Frame'] = $reader->getUI16LE();
                break;
              case 0x83: // ActionGetURL
                $data = $reader->getData($length);
                $strs = explode("\0", $data, 2+1);
                $action['UrlString'] = $strs[0];
                $action['TargetString'] = $strs[1];
                break;
              case 0x88: // ActionConstantPool
                $count = $reader->getUI16LE();
                $action['Count'] = $count;
                $data = $reader->getData($length - 2);
                $strs = explode("\0", $data, $count+1);
                $action['ConstantPool'] = array_splice($strs, 0, $count);
                break;
              case 0x8A: // ActionWaitForFrame
                $action['Frame'] = $reader->getUI16LE();
                $action['SkipCount'] = $reader->getUI8();
                break;
              case 0x8B: // ActionSetTarget
                $data = $reader->getData($length);
                $strs = explode("\0", $data, 1+1);
                $action['TargetName'] = $strs[0];
                break;
              case 0x8C: // ActionSetTarget
                $data = $reader->getData($length);
                $strs = explode("\0", $data, 1+1);
                $action['Label'] = $strs[0];
                break;
              case 0x8D: // ActionWaitForFrame2
                $action['Frame'] = $reader->getUI16LE();
                $action['SkipCount'] = $reader->getUI8();
                break;
              case 0x96: // ActionPush
                $data = $reader->getData($length);
                $values = array();
                $values_reader = new IO_Bit();
                $values_reader->input($data);
                while ($values_reader->hasNextData()) {
                    $value = array();
                    $type = $values_reader->getUI8();
                    $value['Type'] = $type;
                    switch ($type) {
                      case 0: // STRING
                        $value['String'] = IO_SWF_Type_String::parse($values_reader);
                        break;
                      case 1: // Float
                        $value['Float'] = IO_SWF_Type_Float::parse($values_reader);
                        break;
                      case 2: // null
                      $value['null'] = null;
                        break;
                      case 3: // undefined
                      $value['undefined'] = null;
                        break;
                      case 4: // RegisterNumber
                        $value['RegisterNumber'] = $values_reader->getUI8();
                        break;
                      case 5: // Boolean
                        $value['Boolean'] = $values_reader->getUI8();
                        break;
                      case 6: // Double
                        $value['Double'] = IO_SWF_Type_Double::parse($values_reader);
                        break;
                      case 7: // Integer
                        $value['Integer'] = $values_reader->getUI32LE();
                        break;
                      case 8: // Constant8
                        $value['Constant8'] = $values_reader->getUI8();
                        break;
                      case 9: // Constant16
                        $value['Constant16'] = $values_reader->getUI16LE();
                        break;
                      default:
                        throw new IO_SWF_Exception("Illegal ActionPush value's type($type)");
                    }
                    $values[] = $value;
                }
                $action['Values'] = $values;
                break;
              case 0x99: // ActionJump
                $action['BranchOffset'] = $reader->getSI16LE();
                break;
              case 0x9A: // ActionGetURL2
//                $action['SendVarsMethod'] = $reader->getUIBits(2);
//                $action['(Reserved)'] = $reader->getUIBits(4);
//                $action['LoadTargetFlag'] = $reader->getUIBit();
//                $action['LoadVariablesFlag'] = $reader->getUIBit();
                // swf_file_format_spec_v10 bug, field reverse.
                $action['LoadVariablesFlag'] = $reader->getUIBit();
                $action['LoadTargetFlag'] = $reader->getUIBit();
                $action['(Reserved)'] = $reader->getUIBits(4);
                $action['SendVarsMethod'] = $reader->getUIBits(2);
                break;
              case 0x9D: // ActionIf
                $action['Offset'] = $reader->getSI16LE();
                break;
              case 0x9F: // ActionGotoFrame2
                $action['(Reserved)'] = $reader->getUIBits(6);
                $sceneBiasFlag = $reader->getUIBit();
                $action['SceneBiasFlag'] = $sceneBiasFlag;
                $action['PlayFlag'] =  $reader->getUIBit();
                if ($sceneBiasFlag == 1) {
                    $action['SceneBias'] = $reader->getUI16LE();
                }
                break;
              default:
                if ($length > 0) {
                    $action['Data'] =  $reader->getData($length);
                }
                break;
            }
        }
            return $action;
    }
    static function build(&$writer, $action, $opts = array()) {
        $code = $action['Code'];
            $writer->putUI8($code);
        if (0x80 <= $code) {
            switch ($code) {
              case 0x81: // ActionGotoFrame
                $writer->putUI16LE(2);
                $writer->putUI16LE($action['Frame']);
                break;
              case 0x83: // ActionGetURL
                $data = $action['UrlString']."\0".$action['TargetString']."\0";
                $writer->putUI16LE(strlen($data));
                $writer->putData($data);
                break;
              case 0x88: // ActionConstantPool
                $count = count($action['ConstantPool']);
                $data = implode("\0", $action['ConstantPool'])."\0";
                $writer->putUI16LE(strlen($data) + 2);
                $writer->putUI16LE($count);
                $writer->putData($data);
                break;
              case 0x8A: // ActionWaitForFrame
                $writer->putUI16LE($action['Frame']);
                $writer->putUI8($action['SkipCount']);
                break;
              case 0x8B: // ActionSetTarget
                $data = $action['TargetName']."\0";
                $writer->putUI16LE(strlen($data));
                $writer->putData($data);
                break;
              case 0x8C: // ActionGoToLabel
                $data = $action['Label']."\0";
                $writer->putUI16LE(strlen($data));
                $writer->putData($data);
                break;
              case 0x8D: // ActionWaitForFrame2
                $writer->putUI16LE($action['Frame']);
                $writer->putUI8($action['SkipCount']);
                break;
              case 0x96: // ActionPush
                $values_writer = new IO_Bit();
                foreach ($action['Values'] as $value) {
                    $type = $value['Type'];
                    $values_writer->putUI8($type);
                    switch ($type) {
                      case 0: // STRING
                        $str = $value['String'];
                        $pos = strpos($str, "\0");
                        if ($pos === false) {
                            $str .= "\0";
                        } else {
                            $str = substr($str, 0, $pos + 1);
                        }
                        $values_writer->putData($str);
                        break;
                      case 1: // Float
                        IO_SWF_Type_Float::build($values_writer, $value['Float']);
                        break;
                      case 2: // null
                        // nothing to do.
                        break;
                      case 3: // undefined
                        // nothing to do.
                        break;
                      case 4: // RegisterNumber
                        $values_writer->putUI8($value['RegisterNumber']);
                        break;
                      case 5: // Boolean
                        $values_writer->putUI8($value['Boolean']);
                        break;
                      case 6: // Double
                        IO_SWF_Type_Double::build($values_writer, $value['Double']);
                        break;
                      case 7: // Integer
                        $values_writer->putUI32LE($value['Integer']);
                        break;
                      case 8: // Constant8
                        $values_writer->putUI8($value['Constant8']);
                        break;
                      case 9: // Constant16
                        $values_writer->putUI16LE($value['Constant16']);
                        break;
                      default:
                        throw new IO_SWF_Exception("Illegal ActionPush value's type($type)");
                        break;
                    }
                } 
                $values_data = $values_writer->output();
                $writer->putUI16LE(strlen($values_data));
                $writer->putData($values_data);
                break;
              case 0x99: // ActionJump
                $writer->putUI16LE(2);
                $writer->putSI16LE($action['BranchOffset']);
                break;
              case 0x9A: // ActionGetURL2
                $writer->putUI16LE(1);
//                $writer->putUIBits($action['SendVarsMethod'], 2);
//                $writer->putUIBits(0, 4); // Reserved
//                $writer->putUIBit($action['LoadTargetFlag']);
//                $writer->putUIBit($action['LoadVariablesFlag']);
                // swf_file_format_spec_v10 bug, field reverse.
                $writer->putUIBit($action['LoadVariablesFlag']);
                $writer->putUIBit($action['LoadTargetFlag']);
                $writer->putUIBits(0, 4); // Reserved
                $writer->putUIBits($action['SendVarsMethod'], 2);
                break;
              case 0x9D: // ActionIf
                $writer->putUI16LE(2);
                $writer->putSI16LE($action['Offset']);
                break;
              case 0x9F: // ActionGotoFrame2
                if (isset($action['SceneBias'])) {
                    $sceneBiasFlag = 1;
                    $writer->putUI16LE(3);
                } else {
                    $sceneBiasFlag = 0;
                    $writer->putUI16LE(1);
                }
                $writer->putUIBits(0, 6); // Reserved
                $writer->putUIBit($sceneBiasFlag);
                $writer->putUIBit($action['PlayFlag']);
                if ($sceneBiasFlag) {
                    $writer->putUI16LE($action['SceneBias']);
                }
                break;
              default:
                if (isset($action['Data'])) {
                    $data = $action['Data'];
                    $writer->putUI16LE(strlen($data));
                    $writer->putData($data);
                } else {
                    $writer->putUI16LE(0);
                }
                break;
            }
        }
    }
    static function string($action, $opts = array()) {
        $code = $action['Code'];
        $str = sprintf('%s(Code:0x%02X)', self::getCodeName($code), $code);
        if (isset($action['Length'])) {
            $str .= " (Length:{$action['Length']})";
            switch ($code) {
              case 0x88: // ActonConstantPool
                $str .= " Count=".$action['Count'].PHP_EOL;
                foreach ($action['ConstantPool'] as $idx => $c) {
                    $str .= "\t[$idx] $c".PHP_EOL;
                }
                break;
              case 0x96: // ActonPush
                foreach ($action['Values'] as $value) {
                  unset($value['Type']);
                  list($type_name) = array_keys($value);
                  $str .= " ($type_name)".$value[$type_name];
                }
                break;
              default:
                $data_keys = array_diff(array_keys($action), array('Code', 'Length'));
                if (count($data_keys) > 0) {
                    foreach ($data_keys as $key) {
                        $value = $action[$key];
                        if (is_array($value)) {
                            $new_value = array();
                            foreach ($value as $k => $v) {
                                $new_value[] = "$k:$v";
                            }
                            $value = implode(' ', $new_value);
                        }
                        $str .= " " ."$key=$value";
                    }
                }
                break;
            }
        }
        return $str;
    }
    static function replaceActionString(&$action, $trans_table) {
        $replaced = false;
        switch($action['Code']) {
          case 0x83: // ActionGetURL
            ;
            if (isset($trans_table[$action['UrlString']])) {
                $action['UrlString'] = $trans_table[$action['UrlString']];
                $replaced = true;
            }
            if (isset($trans_table[$action['TargetString']])) {
                $action['TargetString'] = $trans_table[$action['TargetString']];
                $replaced = true;
            }
            break;
          case 0x88: // ActionConstantPool
            foreach ($action['ConstantPool'] as $idx_cp => $cp) {
                if (isset($trans_table[$cp])) {
                    $action['ConstantPool'][$idx_cp] = $trans_table[$cp];
                    $replaced = true;
                }
            }
            break;
          case 0x96: // ActionPush
            foreach ($action['Values'] as &$value) {
                if ($value['Type'] == 0) { // Type String
                    if (isset($trans_table[$value['String']])) {
                        $value['String'] = $trans_table[$value['String']];
                        $replaced = true;
                    }
                }
            }
            unset($value);
            break;
        }
        
        return $replaced;
    }
}
