<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$options = getopt("f:v:l::ES");

function usage() {
    echo "Usage: php swfdowngrade.php -f <swf_file> -v <swf_version> [-l <limit_tag_swf_version>] [-E] [-S]\n";
    echo "    -f <swf_file>\n";
    echo "    -v <swf_version>\n";
    echo "    -l <limit_tag_swf_version>\n";
    echo "    -E  # disable eliminate mode\n";
    echo "    -S  # disable strict mode\n";
    echo "ex) php swfdowngrade.php -v 4 -f test.swf\n";
}

// 指定したキーが全て含まれれば true。
function array_key_contain_all($arr, $keys) {
    $inter_keys = array_intersect_key(array_keys($arr), $keys);
    return count($inter_keys) === count($keys);
}

if (! array_key_contain_all($options, ['f', 'v'])) {
    echo "ERROR: require options f and v \n";
    usage();
    exit (1);
}

$filename = $options['f'];

if ($filename === '-') {
    $filename = "php://stdin";
}

$swfVersion = $options['v'];

if (isset($options['l'])) {
    $limitSwfVersion = $options['l'];
} else {
    $limitSwfVersion = $swfVersion;
}

$opts = [
    'preserveStyleState' => true,
    'eliminate'          => ! isset($options['E']),  // 未対応タグを残すか
    'strict'             => ! isset($options['S']),  // 続行するかどうか
];

if (is_readable($filename) === false) {
    echo "ERROR: can't open file:$filename\n";
    usage();
    exit (1);
}
if (is_numeric($swfVersion) === false) {
    echo "ERROR: swfVersion:$swfVersion is not numeric.\n";
    usage();
    exit (1);
}
if (is_numeric($limitSwfVersion) === false) {
    echo "ERROR: limitSwfVersion:$limitSwfVersion is not numeric.\n";
    usage();
    exit (1);
}

$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$opts['abcparse'] = true;
$swf->parse($swfdata, $opts);

$swf->downgrade($swfVersion, $limitSwfVersion, $opts);

echo $swf->build($opts);

exit(0);
