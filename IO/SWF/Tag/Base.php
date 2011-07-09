<?php

abstract class IO_SWF_Tag_Base {
    var $swfInfo;
    function __construct($swfInfo) {
        $this->swfInfo = $swfInfo;
    }
    abstract function parseContent($tagCode, $content, $opts = array());
    abstract function dumpContent($tagCode, $opts = array());
    abstract function buildContent($tagCode, $opts = array());
}
