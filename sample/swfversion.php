<?php

require 'IO/SWF/Editor.php';
// require 'IO/SWF/Type/Action.php';

$tagMap = IO_SWF_Tag::$tagMap;

$options = getopt("f:v:");

if ((isset($options['f']) === false) ||
    (is_readable($options['f']) === false)) { 
    echo "Usage: php swfversion.php -f <swf_file> -v <version>\n";
    echo "ex) php swfversion.php -f test.swf -v 4\n";
    exit(1);
}

if (isset($options['v'])) {
    $check_version = $options['v'];
} else {
    $check_version = null;
}

$swffile = $options['f'];
$swfdata = file_get_contents($swffile);

$swf = new IO_SWF_Editor();

try {
    $swf->parse($swfdata);
} catch (Exception $e) {
    echo "ERROR: $swffile parse failed\n";
    exit (1);
}

$version = $swf->_headers['Version'];

if (is_null($check_version)) {
   $check_version = $version;
}

echo "SWF Version:$version  Check Version:$check_version\n";

foreach ($swf->_tags as $tag) {
    $code = $tag->code;
    $tag_name = $tag->getTagInfo($code, "name");
    $tag_ver = $tag->getTagInfo($code, "version");
        if (is_null($tag_ver)) {
        continue;
    }
    if ($check_version < $tag_ver) {
        echo "$tag_name($code):$tag_ver\n";
    }
    if (($code === 12) || ($code === 59)) { // DoAction or DoInitAction
        if ($tag->parseTagContent()) {        
            $actions = $tag->tag->_actions;
            foreach ($actions as $action) {
                $actionCode = $action['Code'];
                $actionVersion = IO_SWF_Type_Action::getCodeVersion($actionCode);
                if ($check_version < $actionVersion) {
                    $actionName = IO_SWF_Type_Action::getCodeName($actionCode);
		    $hexCode = strtoupper(dechex($actionCode));
                    echo "    $actionName(0x$actionCode):$actionVersion\n";
                }
            }
        } else {
            echo "Illegal Action Contents\n";
        }
    }
}

exit(0);
