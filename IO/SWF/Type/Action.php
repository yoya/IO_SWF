<?php

/*
 * 2011/06/03- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
require_once dirname(__FILE__).'/String.php';
require_once dirname(__FILE__).'/Float.php';
require_once dirname(__FILE__).'/Double.php';
                              
class IO_SWF_Type_Action extends IO_SWF_Type {
    static $action_code_table = array(
        //
        // ActionCode only
        //
        0x04 => 'NextFrame',
        0x05 => 'PreviousFrame',
        0x06 => 'Play',
        0x07 => 'Stop',
        0x08 => 'ToggleQuality',
        0x09 => 'StopSounds',
        //
        0x0A => 'Add',
        0x0B => 'Substract',
        0x0C => 'Multiply',
        0x0D => 'Divide',
        0x0E => 'Equals',
        0x0F => 'Less',
        0x10 => 'And',
        0x11 => 'Or',
        0x12 => 'Not',
        0x13 => 'StringEquals',
        0x14 => 'StringLength',
        0x15 => 'StringExtract',
        //
        0x17 => 'Pop',
        0x18 => 'ToInteger',
        //
        0x1C => 'GetVariable',
        0x1D => 'SetVariable',
        //
        0x20 => 'SetTarget2',
        0x21 => 'StringAdd',
        0x22 => 'GetProperty',
        0x23 => 'SetProperty',
        0x24 => 'CloneSprite',
        0x25 => 'RemoveSprite',
        0x26 => 'Trace',
        //
        0x2D => 'FSCommand2',// Flash Lite
        //
        0x27 => 'StartDrag',
        0x28 => 'EndDrag',
        0x29 => 'StringLess',
        //
        0x30 => 'RandomNumber',
        0x31 => 'MBStringLength',
        0x32 => 'CharToAscii',
        0x33 => 'AsciiToChar',
        0x34 => 'GetTime',
        0x35 => 'MBStringExtract',
        0x36 => 'MBCharToAscii',
        0x37 => 'MBAsciiToChar',
        //
        0x3A => 'Delete', // SWF 5
        0x3B => 'Delete2', // SWF 5
        0x3C => 'DefineLocal', // SWF 5
        0x3D => 'CallFunction', // SWF 5
        0x3E => 'Return', // SWF 5
        0x3F => 'Modulo', // SWF 5
        0x40 => 'NewObject', // SWF 5
        0x41 => 'DefineLocal2', // SWF 5
        0x42 => 'InitArray', // SWF 5
        0x43 => 'InitObject', // SWF 5
        0x44 => 'TypeOf', // SWF 5
        0x45 => 'TargetPath', // SWF 5
        0x46 => 'Enumerate', // SWF 5
        0x47 => 'Add2', // SWF 5
        0x48 => 'Less2', // SWF 5
        0x49 => 'Equals2', // SWF 5
        0x4A => 'ToNumber', // SWF 5
        0x4B => 'ToString', // SWF 5
        0x4C => 'PushDuplicate', // SWF 5
        0x4D => 'StackSwap', // SWF 5
        0x4E => 'GetMember', // SWF 5
        0x4F => 'SetMember', // SWF 5
        0x50 => 'Increment', // SWF 5
        0x51 => 'Decrement', // SWF 5
        0x52 => 'CallMethod', // SWF 5
        0x53 => 'NewMethod', // SWF 5
        0x54 => 'InstanceOf', // SWF 6
        0x55 => 'Enumerate2', // SWF 6
	//
        0x60 => 'BitAnd', // SWF 5
        0x61 => 'BitOr', // SWF 5
        0x62 => 'BitXOr', // SWF 5
        0x63 => 'BitShift', // SWF 5
        0x64 => 'BitURShift', // SWF 5
	//
        0x66 => 'StrictEquals', // SWF 6
        0x67 => 'Greater', // SWF 6
        0x68 => 'StringGreater', // SWF 6
        //
        // has Data Payload
        //
        0x81 => 'GotoFrame',
        0x83 => 'GetURL',
        0x87 => 'StoreRegister', // SWF 5
        0x88 => 'ConstantPool', // SWF 5
        0x8A => 'WaitForFrame',
        0x8B => 'SetTarget',
        0x8C => 'GoToLabel',
        0x8D => 'WaitForFrame2',
        //
        0x94 => 'With', // SWF 5
        0x96 => 'Push',
        //
        0x99 => 'Jump',
        0x9A => 'GetURL2',
        0x9B => 'DefineFunction', // SWF 5
        //
        0x9D => 'If',
        0x9E => 'Call', // why it >=0x80 ?
        0x9E => 'GotoFrame2',
        );
    static function getCodeName($code) {
        if (isset(self::$action_code_table[$code])) {
            return self::$action_code_table[$code];
        } else {
            return "Unknown";
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
                $action['SendVarsMethod'] = $reader->getUIBits(2);
                $action['(Reserved)'] = $reader->getUIBits(4);
                $action['LoadTargetFlag'] = $reader->getUIBit();
                $action['LoadVariablesFlag'] = $reader->getUIBit();
                break;
              case 0x9D: // ActionIf
                $action['Offset'] = $reader->getSI16LE();
                break;
              case 0x9F: // ActionGotoFrame2
                $action['(Reserved)'] = $reader->getUIBits(6);
                $sceneBlasFlag = $reader->getUIBit();
                $action['SceneBlasFlag'] = $sceneBlasFlag;
                $action['PlayFlag'] =  $reader->getUIBit();
                if ($sceneBlasFlag == 1) {
                    $action['SceneBias'] = $reader->getUI16LE();
                }
                break;
              default:
                $action['Data'] =  $reader->getData($length);
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
                $writer->putUIBits($action['SendVarsMethod'], 2);
                $writer->putUIBits(0, 4); // Reserved
                $writer->putUIBit($action['LoadTargetFlag']);
                $writer->putUIBit($action['LoadVariablesFlag']);
                break;
              case 0x9D: // ActionIf
                $writer->putUI16LE(2);
                $writer->putSI16LE($action['Offset']);
                break;
              case 0x9F: // ActionGotoFrame2
                if (isset($action['SceneBias'])) {
                    $sceneBlasFlag = 1;
                    $writer->putUI16LE(3);
                } else {
                    $sceneBlasFlag = 0;
                    $writer->putUI16LE(1);
                }
                $writer->putUIBits(0, 6); // Reserved
                $writer->putUIBit($sceneBlasFlag);
                $writer->putUIBit($action['PlayFlag']);
                if ($sceneBlasFlag) {
                    $writer->putUI16LE($action['SceneBias']);
                }
                break;
              default:
                $data = $action['Data'];
                $writer->putUI16LE(strlen($data));
                $writer->putData($data);
                break;
            }
        }
    }
    static function string($action, $opts = array()) {
        $code = $action['Code'];
        $str = sprintf('%s(Code=0x%02X)', self::getCodeName($code), $code);
        if (isset($action['Length'])) {
            $str .= sprintf(" (Length=%d):", $action['Length']);
            $str .= PHP_EOL."\t";
            switch ($code) {
              case 0x88: // ActonConstantPool
                $str .= " Count=".$action['Count'].PHP_EOL;
                foreach ($action['ConstantPool'] as $idx => $c) {
                    $str .= "\t[$idx] $c".PHP_EOL;
                }
                break;
              case 0x96: // ActonPush
                $str .= "   ";
                foreach ($action['Values'] as $value) {
                  unset($value['Type']);
                  list($type_name) = array_keys($value);
                  $str .= " ($type_name)".$value[$type_name];
                }
                break;
              default:
                $data_keys = array_diff(array_keys($action), array('Code', 'Length'));
                foreach ($data_keys as $key) {
                    $value = $action[$key];
                    if (is_array($value)) {
                        $new_value = array();
                        foreach ($value as $k => $v) {
                            $new_value[] = "$k:$v";
                        }
                        $value = implode(' ', $new_value);
                    }
                    $str .= "   " ."$key=$value";
                }
                break;
            }
        }
        return $str;
    }
}
