<?php

require_once dirname(__FILE__).'/../SWF.php';

class IO_SWF_Tag {
    var $code = 0;
    var $length = 0;
    var $longFormat = false;
    var $content = null;
    var $tagMap = array(
    	// code => array(name , klass)
    	2 => array('name' => 'DefineShape', 'klass' => 'DefineShape'),
    );
    function tagFactory(&$reader) {
    	 $tag = new IO_SWF_Tag();
	 $tag->parse($reader);
	 return $tag;
    }
    function parse(&$reader, $opts = array()) {
        $tagAndLength = $reader->getUI16LE();
        $this->code = $tagAndLength >> 6;
        $length = $tagAndLength & 0x3f;
        if ($length == 0x3f) { // long format
            $length = $reader->getUI32LE();
            $this->LongFormat = true;
        }
	$this->length = $length;
        $this->content = $reader->getData($length);
    }
    function dump($opts = array()) {
        $code = $this->code;
        $length = $this->length;
	if (isset($this->tagMap[$code]['name'])) {
	   $name = $this->tagMap[$code]['name'];
	} else {
	   $name = 'unknown';
	}
        echo "Code: $code($name)  Length: $length".PHP_EOL;
        switch ($code) {
          case 2: // DefineShape
          case 22: // DefineShape2
          case 32: // DefineShape3
            $shape = new IO_SWF_Shape();
            $opts = array('hasShapeId' => true);
            $shape->parse($code, $this->content, $opts);
            $shape->dump();
          break;
	}
    }
    function build($opts = array()) {
            $code = $this->code;
	    $content = $this->content;
            $this->length = strlen($this->content);
            $length = $this->length;
	    $writer = new IO_Bit();
            if (($this->longFormat === false) && ($length < 0x3f)) {
                $tagAndLength = ($code << 6) | $length;
                $writer->putUI16LE($tagAndLength);
            } else {
                $tagAndLength = ($code << 6) | 0x3f;
                $writer->putUI16LE($tagAndLength);
                $writer->putUI32LE($length);
            }
            return $writer->output() . $content;
    }
    function parseContent($content, $opts = array()) {
        return false;
    }
    function dumpContent($opts = array()) {
        return false;
    }
    function buildContent($opts = array()) {
        return false;
    }
}
