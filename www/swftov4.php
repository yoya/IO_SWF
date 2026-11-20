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

// Content-Disposition のファイル名。空白や日本語を含んでも崩れないよう、
// ASCII 版を引用符で囲み、元の名前は RFC 5987 の filename* で渡す。
function content_disposition_attachment($filename) {
    // 非 ASCII は 1 文字ずつ '_' に (不正な UTF-8 ならバイト単位で)
    $ascii = preg_replace('/[^\x20-\x7e]/u', '_', $filename);
    if (is_null($ascii)) {
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $filename);
    }
    $ascii = addcslashes($ascii, '"\\');
    return "attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($filename);
}

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
    header("Content-Disposition: " . content_disposition_attachment($filename));
    echo $output;
} catch (Exception $e) {
    header("Content-Type: text/plain; charset=UTF-8");
    header("Content-Disposition: attachment; filename=error.txt");
    echo $e->getMessage();
}
