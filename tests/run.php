<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 *   IO_Bit is loaded from vendor/ (composer install) or include_path.
 */

$root = dirname(dirname(__FILE__));
if (is_readable($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}
set_include_path($root.PATH_SEPARATOR.get_include_path());
require_once 'IO/SWF/Editor.php';

$failed = 0;
$passed = 0;

function check($name, $expect, $actual) {
    global $failed, $passed;
    if ($expect === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name".PHP_EOL;
    echo "  expect: ".var_export($expect, true).PHP_EOL;
    echo "  actual: ".var_export($actual, true).PHP_EOL;
}

// tag header + content
function tag($code, $content) {
    $len = strlen($content);
    if ($len < 0x3f) {
        return pack('v', ($code << 6) | $len).$content;
    }
    return pack('v', ($code << 6) | 0x3f).pack('V', $len).$content;
}

// uncompressed SWF: RECT(nbits=0), FrameRate 12, FrameCount 1, tags, End
function swf($tags, $version = 8) {
    $body = chr(0).pack('v', 12 << 8).pack('v', 1).$tags.tag(0, '');
    return 'FWS'.chr($version).pack('V', 8 + strlen($body)).$body;
}

/*
 * SHAPE build: a StyleChangeRecord that is skipped (MoveTo to the current
 * position) must not leave its TypeFlag bit in the stream.
 */
// DefineShape id=1, no styles, records: MoveTo(0,0), StraightEdge(+1,+1), End
$content = pack('v', 1).chr(0).chr(0).chr(0).chr(0)."\x04\x18\x54\x00";
$shape = new IO_SWF_Tag_Shape();
$shape->parseContent(2, $content);
$built = $shape->buildContent(2);
$shape2 = new IO_SWF_Tag_Shape();
try {
    $shape2->parseContent(2, $built);
    $records = $shape2->_shapeRecords;
} catch (Exception $e) {
    $records = $e->getMessage();
}
check('shape skip no-op StyleChange: record count', 2, is_array($records) ? count($records) : $records);
check('shape skip no-op StyleChange: edge', array(1, 1, 1),
      is_array($records) ? array($records[0]['TypeFlag'], $records[0]['X'], $records[0]['Y']) : null);

/*
 * DoAction: Jump offsets follow the real length of edited actions
 */
// Jump over Push "cd", Stop, End
$push = chr(0x96).pack('v', 4).chr(0)."cd\0";
$content = chr(0x99).pack('v', 2).pack('v', strlen($push)).$push.chr(0x07).chr(0x00);
$action = new IO_SWF_Tag_Action();
$action->parseContent(12, $content);
$action->replaceActionStrings(array('cd' => 'longer-string'));
$action2 = new IO_SWF_Tag_Action();
$action2->parseContent(12, $action->buildContent(12));
check('action jump after replaceActionStrings: offset', $action2->_byteSizeTable[1],
      $action2->_actions[0]['BranchOffset']);
check('action jump after replaceActionStrings: target', 2,
      isset($action2->_branches[0]) ? $action2->_branches[0] : null);

/*
 * Editor::getSoundData() finds a DefineSound
 */
$editor = new IO_SWF_Editor();
$editor->parse(swf(tag(14, pack('v', 5).chr(0x2E).pack('V', 1)."ABC")));
check('getSoundData', 'ABC', $editor->getSoundData(5));
check('getSoundData unknown id', false, $editor->getSoundData(6));

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
