<?php

/*
  IO_SWF_ABC_Bit class
  (c) 2020/01/29 yoya@awm.jp
  ref) https://www.adobe.com/content/dam/acom/en/devnet/pdf/avm2overview.pdf
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Exception.php';
    require_once 'IO/Bit.php';
}

class IO_SWF_ABC_Bit extends IO_Bit {
    function get_u8() {
        return $this->getUI8();
    }
    function get_u16() {
        return $this->getUI16LE();
    }
    function get_s24() {
        $v = $this->getUI16LE();
        $v = ($this->getUI8() << 16) + $v;
        return ($v < 0x800000)? $v: ($v - 0x1000000); // 2-negative
    }
    function get_u30() {
        $v = $this->get_u32();
        if ($v > 0x3FFFFFFF) {
            throw new IO_SWF_Exception("Error: get_u30() v:$v > 0x3FFFFFFF");
        }
        return $v;
    }
    function get_u32() {
        $v = 0; $s = 0; $i = 0;
        for ($i = 0; $i < 5; $i++) {
            $b = $this->getUI8();
            $v |= ($b & 0x7F) << $s;
            if (! ($b & 0x80)) {
                break;
            }
            $s += 7;
        }
        if ($v > 0xFFFFFFFF) {
            throw new IO_SWF_Exception("Error: get_u32() v:$v > 0xFFFFFFFF");
        }
        return $v;
    }
    function get_s32() {
        $v = $this->get_u32();
        return ($v < 0x80000000)? $v: ($v - 0x100000000); // 2-negative
    }
    function get_d64() {
        $d = $this->getData(8);
        return unpack("E", $d)[0];  // double (big-endian)
    }
}
