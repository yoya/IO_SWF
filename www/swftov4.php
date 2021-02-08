<?php

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
$opts = [ 'preserveStyleState' => true, 'eliminate' => true ];

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
