<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SWF/Editor.php';
}

if (($argc != 3)) {
    fprintf(STDERR, "Usage: php swfgettagdata.php <swf_file> <cid>\n");
    fprintf(STDERR, "ex) php swfgettagdata.php colorformat.swf 2\n");
    exit(1);
}

assert(is_readable($argv[1]));
assert(isset($argv[2]));

$swfdata = file_get_contents($argv[1]);
$cid = $argv[2];

$swf = new IO_SWF_Editor();
$swf->parse($swfdata);

$swf->setCharacterId();  // tag バイナリから cid を抽出

$tag = $swf->getTagByCharacterId($cid);
if (is_null($tag)) {
    fprintf(STDERR, "getTagByCharacterId(%d) failed\n", $cid);
    exit(1);
}

echo $tag->build();  // tag length content のバイナリ出力

exit(0);
