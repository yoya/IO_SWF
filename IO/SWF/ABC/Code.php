<?php

require_once dirname(__FILE__).'/Bit.php';
require_once dirname(__FILE__).'/../Exception.php';

class IO_SWF_ABC_Code {
    var $codeData = null;
    var $codeArray = [];
    function parse($codeData) {
        $this->$codeData = $codeData;
    }
    function dump() {
        $codeLength = strlen($this->codeData);
        $codeCount = count($this->codeArray);
        echo "    code(length=$codeLength, count=$codeCount):\n";
        echo "XXXX    <code>\n";
    }
}
