<?php

require_once dirname(__FILE__).'/../SWF.php';

abstract class IO_SWF_Tag {
    abstract function parse($tagCode, $content, $opts);
    abstract function dump($opts);
    abstract function build($tagCode, $opts);
}
