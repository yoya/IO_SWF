<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_SymbolClass extends IO_SWF_Tag_Base {
    var $_Symbols;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $numSymbols = $reader->getUI16LE();
        $this->_Symbols = [];
        for ($i = 0; $i < $numSymbols; $i++) {
            $tag = $reader->getUI16LE();
            $name = IO_SWF_Type_String::parse($reader);
            $this->_Symbols [] = ["Tag" => $tag, "Name" => $name];
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $numSymbols = count($this->_Symbols);
        echo "\tSymbols (count:$numSymbols)\n";
        foreach ($this->_Symbols as $idx => $symbol) {
            $tag = $symbol["Tag"];
            $name = $symbol["Name"];
            echo "\t[$idx] Tag: $tag  name: $name\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $numSymbols = count($this->_Symbols);
        $writer->putUI16LE($numSymbols);
        foreach ($this->_Symbols as $symbol) {
            $writer->putUI16LE($symbol["Tag"]);
            IO_SWF_Type_String::build($writer, $symbol["Name"]);
        }
    	return $writer->output();
    }
}
