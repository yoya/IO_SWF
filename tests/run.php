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

// DefineShape(code) with no styles and only an EndShapeRecord
function minimalShape($code, $id) {
    return tag($code, pack('v', $id).chr(0).chr(0).chr(0).chr(0).chr(0));
}

function place2($cid, $depth, $name = null) {
    $flags = 0x02 | (is_null($name) ? 0 : 0x20);
    return tag(26, chr($flags).pack('v', $depth).pack('v', $cid).(is_null($name) ? '' : $name."\0"));
}

function place3($cid, $depth) {
    return tag(70, chr(0x02).chr(0x00).pack('v', $depth).pack('v', $cid));
}

function sprite($id, $tags, $frames = 1) {
    return tag(39, pack('vv', $id, $frames).$tags.tag(0, ''));
}

function tagCodes($swfdata) {
    $swf = new IO_SWF();
    $swf->parse($swfdata);
    $codes = array();
    foreach ($swf->_tags as $tag) {
        $codes[] = $tag->code;
    }
    return implode(',', $codes);
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

/*
 * ActionPush Double: high 32-bit word first
 */
// 1.5 = 0x3FF8000000000000
$push = chr(0x96).pack('v', 9).chr(6)."\x00\x00\xF8\x3F\x00\x00\x00\x00";
$action = new IO_SWF_Tag_Action();
$action->parseContent(12, $push."\x00");
check('ActionPush Double parse', 1.5, $action->_actions[0]['Values'][0]['Double']);
check('ActionPush Double build', $push."\x00", $action->buildContent(12));

/*
 * getMovieClip keeps characters referenced by PlaceObject3 and by
 * MorphShape bitmap fills
 */
$data = swf(minimalShape(2, 1).sprite(2, place3(1, 1).tag(1, '')).place2(2, 1, 'mc').tag(1, ''));
$editor = new IO_SWF_Editor();
$editor->parse($data);
check('getMovieClip PlaceObject3 reference', '2,70,1,0', tagCodes($editor->getMovieClip('mc')));

$bitmap = tag(21, pack('v', 1)."JPEGDATA"); // DefineBitsJPEG2 id=1
foreach (array(46, 84) as $code) {
    // DefineMorphShape(2) id=2 with a clipped bitmap fill of bitmap 1
    $morph = pack('v', 2).chr(0).chr(0);
    if ($code == 84) {
        $morph .= chr(0).chr(0).chr(0); // StartEdgeBounds, EndEdgeBounds, flags
    }
    $morph .= pack('V', 9).chr(1).chr(0x41).pack('v', 1).chr(0).chr(0) // Offset, MorphFillStyles
            .chr(0).chr(0x10).chr(0).chr(0x00).chr(0); // MorphLineStyles, StartEdges, EndEdges
    $data = swf($bitmap.tag($code, $morph).sprite(9, place2(2, 1).tag(1, '')).place2(9, 1, 'mc').tag(1, ''));
    $editor = new IO_SWF_Editor();
    $editor->parse($data);
    check("getMovieClip MorphShape($code) bitmap reference", "21,$code,26,1,0", tagCodes($editor->getMovieClip('mc')));
}

/*
 * replaceMovieClip: references that are not renumbered stay as they are,
 * and DefineButton2 records are renumbered
 */
function replacedMovieClip($mc) {
    $base = swf(minimalShape(2, 1).sprite(2, tag(1, '')).place2(2, 1, 'mc').tag(1, ''));
    $editor = new IO_SWF_Editor();
    $editor->parse($base);
    $editor->replaceMovieClip('mc', $mc);
    $swf = new IO_SWF();
    $swf->parse($editor->build());
    $result = array();
    foreach ($swf->_tags as $tag) {
        $tag->parseTagContent(array('Version' => 8));
        switch ($tag->code) {
          case 2:
            $result[] = "shape:".$tag->tag->_shapeId;
            break;
          case 34:
            $result[] = "button:".$tag->tag->_buttonId."/".$tag->tag->_characters[0]['CharacterID'];
            break;
          case 39:
            foreach ($tag->tag->_controlTags as $t) {
                if ($t->code == 26) {
                    $t->parseTagContent();
                    $result[] = "place:".var_export($t->tag->_characterId, true);
                }
            }
            break;
        }
    }
    return implode(' ', $result);
}
check('replaceMovieClip without id collision', 'shape:1 shape:5 place:5',
      replacedMovieClip(swf(minimalShape(2, 5).place2(5, 1).tag(1, ''))));
// DefineButton2 id=5 whose record uses shape 1 (renumbered to 3)
$button2 = tag(34, pack('v', 5).chr(0).pack('v', 0)
               .chr(0x0F).pack('vv', 1, 1).chr(0).chr(0).chr(0));
check('replaceMovieClip DefineButton2', 'shape:1 shape:3 button:5/3 place:5',
      replacedMovieClip(swf(minimalShape(2, 1).$button2.place2(5, 1).tag(1, ''))));

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
