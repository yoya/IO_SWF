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
        // Opecode only
        0x04 => 'ActionNextFrame',
        0x05 => 'ActionPreviousFrame',
        0x06 => 'ActionPlay',
        0x07 => 'ActionStop',
        0x08 => 'ActionToggleQuality',
        0x09 => 'ActionStopSounds',
        //
        0x0A => 'ActionAdd',
        0x0B => 'ActionSubstract',
        0x0C => 'ActionMultiply',
        0x0D => 'ActionDivide',
        0x0E => 'ActionEquals',
        0x0F => 'ActionLess',
        0x10 => 'ActionAnd',
        0x11 => 'ActionOr',
        0x12 => 'ActionNot',
        0x13 => 'ActionStringEquals',
        0x14 => 'ActionStringLength',
        0x15 => 'ActionStringExtract',
        //
        0x17 => 'ActionPop',
        0x18 => 'ActionToInteger',
        //
        0x1C => 'ActionGetVariable',
        0x1D => 'ActionSetVariable',
        //
        0x20 => 'ActionSetTarget2',
        0x21 => 'ActionStringAdd',
        0x22 => 'ActionGetProperty',
        0x23 => 'ActionSetProperty',
        0x24 => 'ActionCloneSprite',
        0x25 => 'ActionRemoveSprite',
        0x26 => 'ActionTrace',
        //
        0x27 => 'ActionStartDrag',
        0x28 => 'ActionEndDrag',
        0x29 => 'ActionStringLess',
        //
        0x30 => 'ActionRandomNumber',
        0x31 => 'ActionMBStringLength',
        0x32 => 'ActionCharToAscii',
        0x33 => 'ActionAsciiToChar',
        0x34 => 'ActionGetTime',
        0x35 => 'ActionMBStringExtract',
        0x36 => 'ActionMBCharToAscii',
        0x37 => 'ActionMBAsciiToChar',

        //
        // has Operand
        0x81 => 'ActionGotoFrame',
        0x83 => 'ActionGetURL',
        0x88 => 'ActionConstantPool',
        0x8A => 'ActionWaitForFrame',
        0x8B => 'ActionSetTarget',
        0x8C => 'ActionGoToLabel',
        0x8D => 'ActionWaitForFrame2',
        0x96 => 'ActionPush',
        //
        0x99 => 'ActionJump',
        0x9A => 'ActionGetURL2',
        //
        0x9D => 'ActionIf',
        0x9E => 'ActionCall', // why it >=0x80 ?
        0x9E => 'ActionGotoFrame2',
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
                $strs = explode("\0", $data);
                $action['UrlString'] = $strs[0];
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
                        $value['Integer'] = $values_reader->getUI32();
                        break;
                    case 8: // Constant8
                        $value['Constant8'] = $values_reader->getUI8();
                        break;
                    case 9: // Constant16
                        $value['Constant16'] = $values_reader->getUI16();
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
            case 0x9D: // ActionIf
                $action['Offset'] = $reader->getSI16LE();
                break;
            case 0x9F: // ActionGotoFrame2
                $action['(Reserved)'] = $reader->getUIBits(6);
                $sceneBlasFlag = $reader->getUIBit();
                $action['SceneBlasFlag'] = $sceneBlasFlag;
                $action['PlayFlag'] =  $reader->getUIBit();
                if ($sceneBlasFlag) {
                    $action['SceneBias'] = $reader->getUI16LE();
                }
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
                            $length = $pos + 1;
                            $str = substr($str, 0, $pos);
                        }
                        $values_writer->putData($str);
                        break;
                    case 1: // Float
                        IO_SWF_Type_Float::build($values_writer, $value['Float']);
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
                        $values_writer->putUI32($value['Integer']);
                        break;
                    case 8: // Constant8
                        $values_writer->putUI8($value['Constant8']);
                        break;
                    case 9: // Constant16
                        $values_writer->putUI16($value['Constant16']);
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
            default:
                $data_keys = array_diff(array_keys($action), array('Code', 'Length'));
                foreach ($data_keys as $key) {
                    $value = $action[$key];
                    if (is_array($value)) {
                        $new_value = array();
                        foreach ($value as $k => $v) {
                            if (is_array($v)) {
                                $new_v = array();
                                foreach ($v as $k2 => $v2) {
                                    $new_v []= "$k2:$v2";
                                }
                                $v = implode(' ', $new_v);
                            }
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
