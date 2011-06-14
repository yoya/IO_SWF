<?php

/*
 * 2011/6/14 (c) yoya@awm.jp
 */

require_once dirname(__FILE__).'/../SWF.php';

class IO_SWF_Info extends IO_SWF {
    // var $_headers = array(); // protected
    // var $_tags = array();    // protected
    function dump($opts = array()) {
        if (empty($opts['hexdump']) === false) {
            $bitio = new IO_Bit();
            $bitio->input($this->_swfdata);
        }
        /* SWF Header */
        echo 'Signature: '.$this->_headers['Signature'].PHP_EOL;
        echo 'Version: '.$this->_headers['Version'].PHP_EOL;
        echo 'FileLength: '.$this->_headers['FileLength'].PHP_EOL;
        echo 'FrameSize: '. IO_SWF_Type_RECT::string($this->_headers['FrameSize'])."\n";
        echo 'FrameRate: '.($this->_headers['FrameRate'] / 0x100).PHP_EOL;
        echo 'FrameCount: '.$this->_headers['FrameCount'].PHP_EOL;

        if (empty($opts['hexdump']) === false) {
            $bitio->hexdump(0, $this->_header_size);
            $opts['bitio'] =& $bitio; // for tag
        }

        /* SWF Tags */
        
        echo 'Tags:'.PHP_EOL;
        foreach ($this->_tags as $tag) {
    	    $tag->dump($opts);
        }
    }
    function countShapeEdges($opts = array()) {
        $count_table = array();
        foreach ($this->_tags as $tag) {
            $code = $tag->code;
	    switch ($code) {
	    case 2: // DefineShape
	    case 22: // DefineShape2
	    case 32: // DefineShape3
	    case 46: // DefineMorphShape
	      $shape = new IO_SWF_Tag_Shape();
	      $opts = array('hasShapeId' => true);
	      $shape->parseContent($code, $tag->content, $opts);
	      list($shape_id, $edges_count) = $shape->countEdges();
	      $count_table[$shape_id] = $edges_count;
	    }
	}
	return $count_table;
    }
}
