<?php

/*
 * ExportAssets (56)  : Count UI16, [Tag UI16, Name STRING] x Count
 * ImportAssets (57)  : URL STRING, Count UI16, [Tag UI16, Name STRING] x Count
 * ImportAssets2 (71) : URL STRING, Reserved UI8 (=1), Reserved UI8 (=0),
 *                      Count UI16, [Tag UI16, Name STRING] x Count
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_Assets extends IO_SWF_Tag_Base {
    var $_url = null;                     // ImportAssets / ImportAssets2
    var $_reserved1 = null, $_reserved2 = null; // ImportAssets2
    var $_assets = array();               // [ ['Tag' => id, 'Name' => name], ... ]
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        if ($tagCode != 56) { // ImportAssets, ImportAssets2
            $this->_url = IO_SWF_Type_String::parse($reader);
        }
        if ($tagCode == 71) { // ImportAssets2
            $this->_reserved1 = $reader->getUI8();
            $this->_reserved2 = $reader->getUI8();
        }
        $count = $reader->getUI16LE();
        $this->_assets = array();
        for ($i = 0; $i < $count; $i++) {
            $tag = $reader->getUI16LE();
            $name = IO_SWF_Type_String::parse($reader);
            $this->_assets []= array('Tag' => $tag, 'Name' => $name);
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        if (! is_null($this->_url)) {
            echo "\tURL: {$this->_url}\n";
        }
        echo "\tAssets (count:".count($this->_assets).")\n";
        foreach ($this->_assets as $idx => $asset) {
            echo "\t[$idx] Tag: {$asset['Tag']}  Name: {$asset['Name']}\n";
        }
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if ($tagCode != 56) {
            IO_SWF_Type_String::build($writer, $this->_url);
        }
        if ($tagCode == 71) {
            $writer->putUI8(is_null($this->_reserved1) ? 1 : $this->_reserved1);
            $writer->putUI8(is_null($this->_reserved2) ? 0 : $this->_reserved2);
        }
        $writer->putUI16LE(count($this->_assets));
        foreach ($this->_assets as $asset) {
            $writer->putUI16LE($asset['Tag']);
            IO_SWF_Type_String::build($writer, $asset['Name']);
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
