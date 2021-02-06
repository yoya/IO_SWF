<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$tmp_name = $_FILES["swffile"]["tmp_name"];
$filename = $_FILES["swffile"]["name"];

$swfVersion = 4;
$limitSwfVersion = $swfVersion;
$opts = [ 'preserveStyleState' => true, 'eliminate' => true ];

$swfdata = file_get_contents($tmp_name);

$swf = new IO_SWF_Editor();
$swf->parse($swfdata, $opts);
$swf->downgrade($swfVersion, $limitSwfVersion, $opts);

$filepath = pathinfo($filename);
$filename = $filepath['filename'] . "-tov4.swf";
header("Content-Type: application/x-shockwave-flash");
header("Content-Disposition: attachment; filename=$filename");
echo $swf->build($opts);
