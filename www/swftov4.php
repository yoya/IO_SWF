<?php

// ライブラリは fprintf(STDERR, ...) でメッセージを出すが、STDERR 定数は
// CLI でしか定義されず、Web では Fatal error になる。エラーログに流す。
if (! defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$tmp_name = $_FILES["swffile"]["tmp_name"];
$filepath = pathinfo($_FILES["swffile"]["name"]);
$filename = $filepath['filename'] . "-tov4.swf";

$swfVersion = 4;
$limitSwfVersion = $swfVersion;
$opts = [
    'eliminate' => true,
    'abcparse'  => true,   // DoABC を parse する (AS3 の SWF の変換に必要)
    'debug'     => false,  // 未定義だと警告が出力に混ざり SWF が壊れ得る
];

$swfdata = file_get_contents($tmp_name);

$swf = new IO_SWF_Editor();

try {
    $swf->parse($swfdata, $opts);
    $swf->downgrade($swfVersion, $limitSwfVersion, $opts);
    $output = $swf->build($opts);
    header("Content-Type: application/x-shockwave-flash");
    header("Content-Disposition: attachment; filename=$filename");
    echo $output;
} catch (Exception $e) {
    header("Content-Type: plain/text");
    header("Content-Disposition: attachment; filename=error.txt");
    echo $e->getMessage();
}
