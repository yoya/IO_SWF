<?php

/*
 * 2011/06/03- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/../Type.php';
                              
class IO_SWF_Type_Action extends IO_SWF_Type {
    static $action_code_table = array(
        0x04 => 'ActionNextFrame',
        0x05 => 'ActionPreviousFrame',
        0x06 => 'ActionPlay',
        0x07 => 'ActionStop',
        0x08 => 'ActionToggleQuality',
        0x09 => 'ActionStopSounds',
        //
        0x81 => 'ActionGotoFrame',
        0x83 => 'ActionGetURL',
        0x88 => 'ActionConstantPool',
        0x8A => 'ActionWaitForFrame',
        0x8B => 'ActionSetTarget',
        0x8C => 'ActionGoToLabel',
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
            case 0x88: // ACtonConstantPool
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
            default:
                $data = $reader->getData($length);
                $action['Data'] = $data;
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
                $length = strlen($data);
                $writer->putUI16LE($length);
                $writer->putData($data, $length);
                break;
            case 0x88: // ActionConstantPool
                $count = count($action['ConstantPool']);
                $data = implode("\0", $action['ConstantPool'])."\0";
                $data_len = strlen($data);
                $writer->putUI16LE($data_len + 2);
                $writer->putUI16LE($count);
                $writer->putData($data, $data_len);
                break;
            case 0x8A: // ActionWaitForFrame
                $writer->putUI16LE($action['Frame']);
                $writer->putUI8($action['SkipCount']);
                break;
            case 0x8B: // ActionSetTarget
                $data = $action['TargetName']."\0";
                $length = strlen($data);
                $writer->putUI16LE($length);
                $writer->putData($data, $length);
                break;
            case 0x8C: // ActionGoToLabel
                $data = $action['Label']."\0";
                $length = strlen($data);
                $writer->putUI16LE($length);
                $writer->putData($data, $length);
                break;
            default:
                $data = $action['Data'];
                $length = strlen($data);
                $writer->putUI16LE($length);
                $writer->putData($data, $length);
                break;
            }
        }
    }
    static function string($action, $opts = array()) {
        $code = $action['Code'];
    	$str = sprintf('%s(Code=0x%02X)', self::getCodeName($code), $code);
        if (isset($action['Length'])) {
            $str .= sprintf(" (Length=%d):", $action['Length']);
            switch ($code) {
            case 0x88: // ActonConstantPool
                $str .= " Count=".$action['Count'].PHP_EOL;
                foreach ($action['ConstantPool'] as $idx => $c) {
                    $str .= "\t[$idx] $c".PHP_EOL;
                }
                break;
            default:
                foreach (array_diff($action, array('Code', 'Length')) as $key => $value) {
                    $str .= " " ."$key=$value";
                }
                break;
            }
        }
        return $str;
    }
}
