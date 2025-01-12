<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$options = getopt("f:v:l::ESdp");

function usage() {
    fprintf(STDERR, "Usage: php swfdowngrade.php -f <swf_file> -v <swf_version> [-l <limit_tag_swf_version>] [-E] [-S]\n");
    fprintf(STDERR, "    -f <swf_file>\n");
    fprintf(STDERR, "    -v <swf_version>\n");
    fprintf(STDERR, "    -l <limit_tag_swf_version>\n");
    fprintf(STDERR, "    -E  # disable eliminate mode\n");
    fprintf(STDERR, "    -S  # disable strict mode\n");
    fprintf(STDERR, "    -d  # debub mode\n");
    fprintf(STDERR, "    -p  # enable preserveStyleState\n");
    fprintf(STDERR, "ex) php swfdowngrade.php -v 4 -f test.swf\n");
}

// 指定したキーが全て含まれれば true。
function array_key_contain_all($arr, $keys) {
    $inter_keys = array_intersect_key(array_keys($arr), $keys);
    return count($inter_keys) === count($keys);
}

if (! array_key_contain_all($options, ['f', 'v'])) {
    fprintf(STDERR, "ERROR: require options f and v \n");
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
    'eliminate'          => ! isset($options['E']),  // 未対応タグを残すか
    'strict'             => ! isset($options['S']),  // 続行するかどうか
    'debug'              => isset($options['d']),    // debug mode
    'preserveStyleState' => isset($options['p']),
];

if (is_readable($filename) === false) {
    fprintf(STDERR, "ERROR: can't open file:$filename\n");
    usage();
    exit (1);
}
if (is_numeric($swfVersion) === false) {
    fprintf(STDERR, "ERROR: swfVersion:$swfVersion is not numeric.\n");
    usage();
    exit (1);
}
if (is_numeric($limitSwfVersion) === false) {
    fprintf(STDERR, "ERROR: limitSwfVersion:$limitSwfVersion is not numeric.\n");
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
