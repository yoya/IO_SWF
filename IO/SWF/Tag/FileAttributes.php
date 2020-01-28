<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_FileAttributes extends IO_SWF_Tag_Base {
   var $Reserved, $UseDirectBlit, $UseGPU, $HasMetadata;
   var $ActionScript3, $Reserved2, $UseNetwork;
   var $Reserved3;
   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->Reserved      = $reader->getUIBit();
        $this->UseDirectBlit = $reader->getUIBit();
        $this->UseGPU        = $reader->getUIBit();
        $this->HasMetadata   = $reader->getUIBit();
        $this->ActionScript3 = $reader->getUIBit();
        $this->Reserved2     = $reader->getUIBits(2);
        $this->UseNetwork    = $reader->getUIBit();
        $this->Reserved3     = $reader->getUIBits(24);
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tReserved:{$this->Reserved} UseDirectBlit:{$this->UseDirectBlit} UseGPU:{$this->UseGPU} HasMetadata:{$this->HasMetadata}\n";
        echo "\tActionScript3:{$this->ActionScript3} Reserved2:{$this->Reserved2} UseNetwork:{$this->UseNetwork} Reserved3:{$this->Reserved3}\n";
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUIBit($this->Reserved);
        $writer->putUIBit($this->UseDirectBlit);
        $writer->putUIBit($this->UseGPU);
        $writer->putUIBit($this->HasMetadata);
        $writer->putUIBit($this->ActionScript3);
        $writer->putUIBits($this->Reserved2, 2);
        $writer->putUIBit($this->UseNetwork);
        $writer->putUIBits($this->Reserved3, 24);
    	return $writer->output();
    }
}
