<?php

/*
 * 2010/8/12- (c) yoya@awm.jp
 */

require_once dirname(__FILE__).'/../SWF.php';
require_once dirname(__FILE__).'/Shape.php';

class IO_SWF_Dumper extends IO_SWF {
    // instance variable
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected

    function dump() {
        /* SWF Header */
        echo 'Signature: '.$this->_headers['Signature'].PHP_EOL;
        echo 'Version: '.$this->_headers['Version'].PHP_EOL;
        echo 'FileLength: '.$this->_headers['FileLength'].PHP_EOL;
        echo 'FrameSize: '.PHP_EOL;
        echo "\tXmin: ".($this->_headers['FrameSize']['Xmin'] / 20).PHP_EOL;
        echo "\tXmax: ".($this->_headers['FrameSize']['Xmax'] / 20).PHP_EOL;
        echo "\tYmin: ".($this->_headers['FrameSize']['Ymin'] / 20).PHP_EOL;
        echo "\tYmax: ".($this->_headers['FrameSize']['Ymax'] / 20).PHP_EOL;
        echo 'FrameRate: '.($this->_headers['FrameRate'] / 0x100).PHP_EOL;
        echo 'FrameCount: '.$this->_headers['FrameCount'].PHP_EOL;

        /* SWF Tags */
        
        echo 'Tags:'.PHP_EOL;
        foreach ($this->_tags as $tag) {
            $code = $tag['Code'];
            $length = $tag['Length'];
            echo "\tCode: $code  Length: $length".PHP_EOL;
	    switch ($code) {
	      case 2: // DefineShape
	      case 22: // DefineShape2
	      case 32: // DefineShape3
		$shape = new IO_SWF_Shape();
		$shape->parse($code, $tag['Content']);
		$shape->dump();
		break;
	    }
        }
    }
}
