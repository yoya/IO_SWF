<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require 'IO/SWF/Editor.php';
}

$options = getopt("f:p");

if (! isset($options['f']))  {
    fprintf(STDERR, "Usage: php swfrebuild.php -f <swf_file> [-p]\n");
    echo "    -f <swf_file>\n";
    echo "    -p  # enable preserveStyleState\n";
    fprintf(STDERR, "ex) php swfrebuild.php test.swf\n");
    exit(1);
}
$filename = $options['f'];
assert(is_readable($filename));

$opts = [
    'preserveStyleState' => isset($options['p'])
];

$swfdata = file_get_contents($filename);

$swf = new IO_SWF_Editor();

$swf->parse($swfdata);

$swf->rebuild();

echo $swf->build($opts);

exit(0);
