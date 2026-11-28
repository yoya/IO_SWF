<?php
/*
 * generator of the synthetic test data (no dependency)
 * usage: php tests/data/make.php [<output_dir>]
 *   Every file is built from the SWF file format spec (v19) and the AVM2 overview
 *   with pack() and the small bit writer below; running it again writes the same bytes.
 *   Bit fields use the smallest width that holds the values (as Flash writes them).
 */

$dir = isset($argv[1]) ? $argv[1] : dirname(__FILE__);

// MSB first bit writer
class Bits {
    var $bits = '';
    function ub($value, $width) {
        if ($width > 0) {
            $s = decbin($value);
            if (strlen($s) > $width) {
                throw new Exception("value $value does not fit in $width bits");
            }
            $this->bits .= str_pad($s, $width, '0', STR_PAD_LEFT);
        }
        return $this;
    }
    function sb($value, $width) {
        return $this->ub(($value < 0) ? $value + (1 << $width) : $value, $width);
    }
    function align() {
        if (strlen($this->bits) % 8) {
            $this->bits .= str_repeat('0', 8 - strlen($this->bits) % 8);
        }
        return $this;
    }
    function bytes($data) {
        $this->align();
        foreach (str_split($data) as $c) {
            $this->ub(ord($c), 8);
        }
        return $this;
    }
    function out() {
        $this->align();
        $data = '';
        foreach (str_split($this->bits, 8) as $byte) {
            $data .= chr(bindec($byte));
        }
        return $data;
    }
}

// bits for a signed value
function sbits($v) {
    $n = ($v < 0) ? -$v - 1 : $v;
    for ($i = 1; $n; $i++) {
        $n >>= 1;
    }
    return $i;
}
// bits for signed values, 0 when all values are 0
function nbits($values) {
    $n = 0;
    foreach ($values as $v) {
        if ($v != 0) {
            $n = max($n, sbits($v));
        }
    }
    return $n;
}

function rect($xmin, $xmax, $ymin, $ymax) {
    $n = nbits(array($xmin, $xmax, $ymin, $ymax));
    $b = new Bits();
    $b->ub($n, 5)->sb($xmin, $n)->sb($xmax, $n)->sb($ymin, $n)->sb($ymax, $n);
    return $b->out();
}

// null scale / rotate: the field is absent
function matrix($scale = null, $rotate = null, $translate = array(0, 0)) {
    $b = new Bits();
    foreach (array($scale, $rotate) as $pair) {
        if (is_null($pair)) {
            $b->ub(0, 1);
        } else {
            $n = nbits($pair);
            $b->ub(1, 1)->ub($n, 5)->sb($pair[0], $n)->sb($pair[1], $n);
        }
    }
    $n = nbits($translate);
    $b->ub($n, 5)->sb($translate[0], $n)->sb($translate[1], $n);
    return $b->out();
}

// CXFORM (alpha=false) / CXFORMWITHALPHA, null: the terms are absent
function cxform($mult, $add) {
    $n = nbits(array_merge((array) $mult, (array) $add));
    $b = new Bits();
    $b->ub(is_null($add) ? 0 : 1, 1)->ub(is_null($mult) ? 0 : 1, 1)->ub($n, 4);
    foreach (array($mult, $add) as $terms) {
        foreach ((array) $terms as $v) {
            $b->sb($v, $n);
        }
    }
    return $b->out();
}

function rgb($hex) {
    return pack('H*', $hex);
}

function tag($code, $content) {
    $len = strlen($content);
    // bitmap tags always use the long header
    if (($len < 0x3f) && ! in_array($code, array(6, 19, 20, 21, 35, 36, 90))) {
        return pack('v', ($code << 6) | $len).$content;
    }
    return pack('v', ($code << 6) | 0x3f).pack('V', $len).$content;
}

function swf($signature, $version, $frameSize, $frameRate, $frameCount, $tags) {
    $body = call_user_func_array('rect', $frameSize).pack('vv', $frameRate, $frameCount).$tags.tag(0, '');
    $header = $signature.chr($version).pack('V', 8 + strlen($body));
    return $header.(($signature === 'CWS') ? gzcompress($body) : $body);
}

/*
 * shapes
 */
function fillSolid($color) {
    return "\x00".$color;
}
// records: array(ratio, color)
function fillGradient($type, $matrix, $spread, $interpolation, $records, $focal = null) {
    $data = chr($type).$matrix.chr(($spread << 6) | ($interpolation << 4) | count($records));
    foreach ($records as $r) {
        $data .= chr($r[0]).$r[1];
    }
    if (! is_null($focal)) {
        $data .= pack('v', $focal & 0xffff);
    }
    return $data;
}
function fillBitmap($type, $bitmapId, $matrix) {
    return chr($type).pack('v', $bitmapId).$matrix;
}
function styleArray($styles) {
    return chr(count($styles)).implode('', $styles);
}
function lineStyle($width, $color) {
    return pack('v', $width).$color;
}
// LINESTYLE2: $f = array(StartCap, Join, HasFill, NoHScale, NoVScale, PixelHinting, NoClose, EndCap)
function lineStyle2($width, $f, $miter, $colorOrFill) {
    list($startCap, $join, $hasFill, $noH, $noV, $pixel, $noClose, $endCap) = $f;
    return pack('v', $width).chr(($startCap << 6) | ($join << 4) | ($hasFill << 3) | ($noH << 2) | ($noV << 1) | $pixel).
        chr(($noClose << 2) | $endCap).(($join == 2) ? pack('v', $miter) : '').$colorOrFill;
}

/*
 * SHAPE records, each one of
 *   array('style', array(key => value)): keys move => (x, y), fill0, fill1, line,
 *       new => (FILLSTYLEARRAY, LINESTYLEARRAY, NumFillBits, NumLineBits)
 *   array('line', dx, dy), array('curve', cdx, cdy, adx, ady), array('end')
 */
function shapeRecords($numFillBits, $numLineBits, $records) {
    $b = new Bits();
    $b->ub($numFillBits, 4)->ub($numLineBits, 4);
    foreach ($records as $r) {
        switch ($r[0]) {
          case 'style':
            $s = $r[1];
            $b->ub(0, 1)->ub(isset($s['new']) ? 1 : 0, 1)->ub(isset($s['line']) ? 1 : 0, 1)
              ->ub(isset($s['fill1']) ? 1 : 0, 1)->ub(isset($s['fill0']) ? 1 : 0, 1)->ub(isset($s['move']) ? 1 : 0, 1);
            if (isset($s['move'])) {
                $n = nbits($s['move']);
                $b->ub($n, 5)->sb($s['move'][0], $n)->sb($s['move'][1], $n);
            }
            if (isset($s['fill0'])) {
                $b->ub($s['fill0'], $numFillBits);
            }
            if (isset($s['fill1'])) {
                $b->ub($s['fill1'], $numFillBits);
            }
            if (isset($s['line'])) {
                $b->ub($s['line'], $numLineBits);
            }
            if (isset($s['new'])) {
                list($fills, $lines, $numFillBits, $numLineBits) = $s['new'];
                $b->bytes($fills.$lines)->ub($numFillBits, 4)->ub($numLineBits, 4);
            }
            break;
          case 'line':
            list(, $dx, $dy) = $r;
            $n = max(2, nbits(array($dx, $dy)));
            $b->ub(1, 1)->ub(1, 1)->ub($n - 2, 4);
            if ($dx && $dy) {
                $b->ub(1, 1)->sb($dx, $n)->sb($dy, $n);
            } elseif ($dx) {
                $b->ub(0, 1)->ub(0, 1)->sb($dx, $n);
            } else {
                $b->ub(0, 1)->ub(1, 1)->sb($dy, $n);
            }
            break;
          case 'curve':
            $deltas = array_slice($r, 1);
            $n = max(2, nbits($deltas));
            $b->ub(1, 1)->ub(0, 1)->ub($n - 2, 4);
            foreach ($deltas as $d) {
                $b->sb($d, $n);
            }
            break;
          case 'end':
            $b->ub(0, 1)->ub(0, 5);
            break;
        }
    }
    return $b->out();
}

function place2($depth, $cid = null, $opts = array()) {
    $flags = (isset($opts['clipDepth']) ? 0x40 : 0) | (isset($opts['name']) ? 0x20 : 0) |
        (isset($opts['ratio']) ? 0x10 : 0) | (isset($opts['cxform']) ? 0x08 : 0) |
        (isset($opts['matrix']) ? 0x04 : 0) | (is_null($cid) ? 0 : 0x02) | (empty($opts['move']) ? 0 : 0x01);
    return tag(26, chr($flags).pack('v', $depth).(is_null($cid) ? '' : pack('v', $cid)).
               (isset($opts['matrix']) ? $opts['matrix'] : '').(isset($opts['cxform']) ? $opts['cxform'] : '').
               (isset($opts['ratio']) ? pack('v', $opts['ratio']) : '').(isset($opts['name']) ? $opts['name']."\0" : '').
               (isset($opts['clipDepth']) ? pack('v', $opts['clipDepth']) : ''));
}

function showFrame() {
    return tag(1, '');
}

$shapesTags = '';
// SetBackgroundColor
$shapesTags .= tag(9, rgb('336699'));
// DefineBitsLossless id=10, format 3 (colormapped), 3x2, 2 colors, rows padded to 4 bytes
$shapesTags .= tag(20, pack('v', 10).chr(3).pack('vv', 3, 2).chr(2 - 1).
                   gzcompress(rgb('ff0000').rgb('0000ff')."\x00\x01\x00\x00"."\x01\x00\x01\x00"));
// DefineBitsLossless id=11, format 5 (PIX24: reserved, R, G, B), 2x1
$shapesTags .= tag(20, pack('v', 11).chr(5).pack('vv', 2, 1).gzcompress("\x00\x10\x20\x30"."\x00\x40\x50\x60"));
// DefineBitsLossless id=14, format 4 (PIX15), 1x1 #f80000, row padded to 4 bytes
$shapesTags .= tag(20, pack('v', 14).chr(4).pack('vv', 1, 1).gzcompress(pack('n', 0x7C00)."\x00\x00"));
// DefineBitsLossless2 id=12, format 5 (premultiplied ARGB), 2x2: opaque red, half red, transparent, opaque green
$shapesTags .= tag(36, pack('v', 12).chr(5).pack('vv', 2, 2).
                   gzcompress("\xff\xff\x00\x00"."\x80\x80\x00\x00"."\x00\x00\x00\x00"."\xff\x00\xff\x00"));
// DefineBitsLossless2 id=13, format 3, 1x1, 2 RGBA colors, row padded to 4 bytes
$shapesTags .= tag(36, pack('v', 13).chr(3).pack('vv', 1, 1).chr(2 - 1).
                   gzcompress(rgb('00ff00ff').rgb('00000000')."\x00\x00\x00\x00"));
// DefineShape id=1: solid fill, line, straight edges (horizontal, vertical, general)
$shapesTags .= tag(2, pack('v', 1).rect(0, 400, 0, 400).
                   styleArray(array(fillSolid(rgb('ff0000')))).styleArray(array(lineStyle(20, rgb('000000')))).
                   shapeRecords(1, 1, array(array('style', array('move' => array(20, 20), 'fill1' => 1, 'line' => 1)),
                                            array('line', 360, 0), array('line', 0, 360), array('line', -360, -360),
                                            array('end'))));
// DefineShape2 id=2: curved edge, new styles in a StyleChangeRecord
$newStyles = array(styleArray(array(fillSolid(rgb('0000ff')), fillSolid(rgb('ffff00')))),
                   styleArray(array(lineStyle(40, rgb('808080')))), 2, 1);
$shapesTags .= tag(22, pack('v', 2).rect(0, 400, 0, 400).
                   styleArray(array(fillSolid(rgb('00ff00')))).styleArray(array()).
                   shapeRecords(1, 0, array(array('style', array('move' => array(100, 0), 'fill0' => 1)),
                                            array('curve', 100, 0, 0, 100), array('line', -100, -100),
                                            array('style', array('new' => $newStyles, 'move' => array(0, 0),
                                                                 'fill0' => 1, 'fill1' => 0, 'line' => 0)),
                                            array('style', array('fill0' => 2, 'line' => 1)),
                                            array('line', 200, 0), array('line', 0, 200), array('line', -200, -200),
                                            array('end'))));
// DefineShape3 id=3: RGBA, linear / radial gradients, clipped bitmap fill of bitmap 10
$fills = array(fillGradient(0x10, matrix(array(0x4000, 0x4000), null, array(400, 300)), 0, 0,
                            array(array(0, rgb('ff000080')), array(255, rgb('0000ffff')))),
               fillGradient(0x12, matrix(), 1, 1,
                            array(array(0, rgb('ffffffff')), array(128, rgb('80808080')), array(255, rgb('00000000')))),
               fillBitmap(0x41, 10, matrix(array(20 << 16, 20 << 16), array(0x100, -0x100))));
$shapesTags .= tag(32, pack('v', 3).rect(0, 800, 0, 600).
                   styleArray($fills).styleArray(array(lineStyle(10, rgb('000000ff')))).
                   shapeRecords(2, 1, array(array('style', array('move' => array(10, 10), 'fill0' => 1, 'line' => 1)),
                                            array('line', 780, 0), array('line', 0, 580),
                                            array('line', -780, 0), array('line', 0, -580),
                                            array('style', array('move' => array(100, 100), 'fill0' => 2)),
                                            array('line', 100, 0), array('line', 0, 100), array('line', -100, -100),
                                            array('style', array('move' => array(300, 300), 'fill0' => 3, 'line' => 0)),
                                            array('line', 60, 0), array('line', 0, 60), array('line', -60, -60),
                                            array('end'))));
// DefineShape4 id=4: EdgeBounds, focal gradient, LINESTYLE2 (miter join, fill)
$lines = array(lineStyle2(30, array(1, 2, 0, 1, 0, 1, 1, 2), 0x0300, rgb('112233ff')),
               lineStyle2(40, array(0, 1, 1, 0, 1, 0, 0, 0), null, fillSolid(rgb('44556677'))));
$shapesTags .= tag(83, pack('v', 4).rect(-20, 420, -20, 420).rect(0, 400, 0, 400).chr(0x00).
                   styleArray(array(fillGradient(0x13, matrix(), 0, 0,
                                                 array(array(0, rgb('ff0000ff')), array(255, rgb('0000ffff'))), -128))).
                   styleArray($lines).
                   shapeRecords(1, 2, array(array('style', array('move' => array(0, 400), 'fill1' => 1, 'line' => 1)),
                                            array('line', 400, 0), array('line', 0, -400),
                                            array('style', array('line' => 2)),
                                            array('curve', -200, 0, -200, 400),
                                            array('end'))));
$shapesTags .= place2(1, 1).place2(2, 2).place2(3, 3, array('matrix' => matrix(null, null, array(200, 100)))).place2(4, 4);
$shapesTags .= showFrame();
$shapes = swf('FWS', 8, array(0, 4000, 0, 3000), 24 << 8, 1, $shapesTags);

/*
 * fonts and edit texts
 */
// DefineFont2 id=1: HasLayout, WideCodes, Bold, Latin, 2 glyphs, kerning
$glyphs = array(shapeRecords(1, 0, array(array('style', array('move' => array(100, -700), 'fill1' => 1)),
                                         array('line', 600, 0), array('line', 0, 700),
                                         array('line', -600, 0), array('line', 0, -700), array('end'))),
                shapeRecords(1, 0, array(array('style', array('fill1' => 1)),
                                         array('line', 512, -800), array('line', 512, 800),
                                         array('curve', -512, 100, -512, -100), array('end'))));
$offsetTable = pack('v', 6).pack('v', 6 + strlen($glyphs[0]));
$font2 = pack('v', 1).chr(0x85).chr(1).chr(4).'Test'.pack('v', 2).
    $offsetTable.pack('v', 6 + strlen($glyphs[0]) + strlen($glyphs[1])).$glyphs[0].$glyphs[1].
    pack('vv', 0x41, 0x3042).pack('vvv', 800, 200, 40).pack('vv', 800, 1024).
    rect(100, 700, -700, 0).rect(0, 1024, -800, 100).pack('v', 1).pack('vvv', 0x41, 0x3042, -50 & 0xffff);
// DefineFont3 id=2: no glyphs, no layout (OffsetTable is empty, CodeTableOffset is 2)
$font3 = pack('v', 2).chr(0).chr(0).chr(5).'Empty'.pack('v', 0).pack('v', 2);
// DefineFont3 id=3: no glyphs, HasLayout, WideOffsets, WideCodes, no name
$font3wide = pack('v', 3).chr(0x8C).chr(0).chr(0).pack('v', 0).pack('V', 4).pack('vvv', 1024, 256, 0).pack('v', 0);
// DefineEditText id=4: HasText, WordWrap, Multiline, HasTextColor, HasMaxLength, HasFont, HasLayout, Border, UseOutlines
$edit4 = pack('v', 4).rect(-40, 2000, -40, 400).chr(0xE7).chr(0x29).pack('vv', 1, 240).rgb('102030ff').pack('v', 32).
    chr(1).pack('vvv', 20, 40, 0).pack('v', -20 & 0xffff)."msg\0"."Hello\0";
// DefineEditText id=5: ReadOnly, HasFontClass, AutoSize, NoSelect, WasStatic, HTML, no text
$edit5 = pack('v', 5).rect(0, 1000, 0, 200).chr(0x08).chr(0xD6)."MyFont\0"."v2\0";
$textTags = tag(48, $font2).tag(75, $font3).tag(75, $font3wide).tag(37, $edit4).tag(37, $edit5).
    place2(1, 4, array('name' => 'txt')).place2(2, 5).showFrame();
$text = swf('FWS', 8, array(0, 2000, 0, 1000), 0x0C80, 1, $textTags);

/*
 * sprites and display list (zlib compressed)
 */
function squareShape($code, $id, $size, $color) {
    return tag($code, pack('v', $id).rect(0, $size, 0, $size).styleArray(array(fillSolid(rgb($color)))).styleArray(array()).
               shapeRecords(1, 0, array(array('style', array('fill1' => 1)),
                                        array('line', $size, 0), array('line', 0, $size),
                                        array('line', -$size, 0), array('line', 0, -$size), array('end'))));
}
$innerSprite = tag(39, pack('vv', 3, 1).place2(1, 1).showFrame().tag(0, ''));
$mcControl = place2(1, 3, array('matrix' => matrix(null, null, array(100, 50)), 'name' => 'inner')).
    // PlaceObject: character 2, depth 2, scaled, CXFORM with multiply and add terms
    tag(4, pack('vv', 2, 2).matrix(array(0x20000, 0x8000)).cxform(array(256, 128, 64), array(-10, 0, 10))).
    tag(12, "\x07\x00"). // DoAction: Stop
    showFrame().
    place2(1, null, array('move' => true, 'matrix' => matrix(null, null, array(-20, 300)), 'ratio' => 5)).
    // PlaceObject3: character 1, depth 3, HasBlendMode (3: multiply), HasCacheAsBitmap (1)
    tag(70, chr(0x02).chr(0x06).pack('vv', 3, 1).chr(3).chr(1)).
    tag(43, "second\0"). // FrameLabel
    showFrame().
    tag(5, pack('vv', 2, 2)). // RemoveObject: character 2, depth 2
    tag(28, pack('v', 3)).   // RemoveObject2: depth 3
    showFrame().tag(0, '');
$spriteTags = squareShape(2, 1, 200, 'ff0000').squareShape(2, 2, 100, '0000ff').$innerSprite.
    tag(39, pack('vv', 4, 3).$mcControl).
    place2(1, 4, array('cxform' => cxform(null, array(0, 0, 0, -128)), 'name' => 'mc')).
    place2(2, 2, array('clipDepth' => 3)).showFrame().
    place2(1, null, array('move' => true, 'matrix' => matrix(null, null, array(10, 10)))).
    tag(28, pack('v', 2)).showFrame();
$sprite = swf('CWS', 8, array(0, 4000, 0, 3000), 30 << 8, 2, $spriteTags);

/*
 * AS1/AS2 actions and buttons (SWF 5)
 */
function action($code, $payload = null) {
    if ($code < 0x80) {
        return chr($code);
    }
    return chr($code).pack('v', strlen($payload)).$payload;
}
function push($values) {
    $data = '';
    foreach ($values as $v) {
        list($type, $value) = $v;
        $data .= chr($type);
        switch ($type) {
          case 0: $data .= $value."\0"; break;            // String
          case 1: $data .= pack('g', $value); break;      // Float (little-endian)
          case 4: case 5: case 8: $data .= chr($value); break; // Register, Boolean, Constant8
          case 6: $d = pack('e', $value); $data .= substr($d, 4, 4).substr($d, 0, 4); break; // Double: high word first
          case 7: $data .= pack('V', $value); break;      // Integer
          case 9: $data .= pack('v', $value); break;      // Constant16
        }
    }
    return action(0x96, $data);
}
// DoAction (SWF 4 actions)
$a = array();
$a[] = action(0x81, pack('v', 1));                              // GotoFrame 1
$a[] = action(0x83, "http://example.com/\0_blank\0");           // GetURL
$a[] = action(0x8B, "/mc\0");                                   // SetTarget
$a[] = action(0x8C, "lbl\0");                                   // GoToLabel
$a[] = action(0x8B, "\0");                                      // SetTarget ""
$a[] = action(0x8A, pack('v', 1).chr(1));                       // WaitForFrame 1, SkipCount 1
$a[] = action(0x06);                                            // Play
$a[] = push(array(array(0, 'v'), array(1, 1.5), array(2, null), array(3, null), array(4, 1),
                  array(5, 1), array(6, 3.25), array(7, 7)));  // [7] Push
$a[] = action(0x1D);                                            // SetVariable
$a[] = push(array(array(5, 1)));                                // [9] Push true
$skipped = push(array(array(0, 'skipped'))).action(0x26);       // Push, Trace
$a[] = action(0x9D, pack('v', strlen($skipped)));               // [10] If -> [13]
$a[] = push(array(array(0, 'skipped')));
$a[] = action(0x26);
$a[] = action(0x9A, chr(0x42));                                 // [13] GetURL2 LoadTarget, POST
$a[] = action(0x9F, chr(0x03).pack('v', 2));                    // GotoFrame2 SceneBias 2, Play
$a[] = action(0x07);                                            // Stop
$back = 0;
for ($i = 9; $i < count($a); $i++) {
    $back += strlen($a[$i]);
}
$a[] = action(0x99, pack('v', (-($back + 5)) & 0xffff));        // [16] Jump -> [9]
$a[] = action(0x00);                                            // End
$doAction4 = implode('', $a);
// DoAction (SWF 5 / 6 actions)
$body = push(array(array(0, 'x'))).action(0x1C).action(0x3E);  // Push "x", GetVariable, Return
$b = array();
$b[] = action(0x88, pack('v', 3)."a\0b\0f\0");                  // ConstantPool
$b[] = action(0x9B, "f\0".pack('v', 1)."x\0".pack('v', strlen($body))); // DefineFunction f(x)
$b[] = $body;
$b[] = push(array(array(7, 1), array(7, 1), array(8, 2)));      // Push 1, 1, "f"
$b[] = action(0x3D);                                            // CallFunction
$b[] = action(0x87, chr(0));                                    // StoreRegister 0
$b[] = action(0x17);                                            // Pop
$b[] = push(array(array(9, 0), array(8, 1)));                   // Push "a", "b"
$b[] = action(0x47);                                            // Add2
$b[] = action(0x17);                                            // Pop
// DefineFunction2 g(r:1 y), RegisterCount 2, PreloadThis, PreloadGlobal, codeSize 1 (Return)
$b[] = action(0x8E, "g\0".pack('v', 1).chr(2).chr(0x01).chr(0x01).chr(1)."y\0".pack('v', 1));
$b[] = action(0x3E);
$b[] = action(0x00);
$doAction5 = implode('', $b);
// DefineButton id=2: one record (all states, shape 1, depth 1), actions GetURL
$button = pack('v', 2).chr(0x0F).pack('vv', 1, 1).matrix(null, null, array(20, 20))."\x00".
    action(0x83, "next.html\0\0")."\x00";
// DefineButton2 id=3: TrackAsMenu, 2 records with CXFORMWITHALPHA, 2 BUTTONCONDACTIONs
$records = chr(0x03).pack('vv', 1, 1).matrix().cxform(array(256, 128, 128, 256), null).
    chr(0x08).pack('vv', 1, 2).matrix().cxform(null, null)."\x00";
$cond1Actions = push(array(array(0, 'a'))).action(0x26)."\x00";
$cond1 = pack('v', 4 + strlen($cond1Actions)).chr(0x04).chr(0x00).$cond1Actions; // OverUpToOverDown
$cond2 = pack('v', 0).chr(0x00).chr(13 << 1).action(0x07)."\x00";                // KeyPress 13 (enter)
$button2 = pack('v', 3).chr(0x01).pack('v', 2 + strlen($records)).$records.$cond1.$cond2;
$actionTags = squareShape(2, 1, 200, '00ff00').tag(12, $doAction4).tag(12, $doAction5).tag(7, $button).tag(34, $button2).
    place2(1, 2).place2(2, 3, array('matrix' => matrix(null, null, array(400, 0)))).showFrame().
    tag(12, "\x07\x00").showFrame();
$action = swf('FWS', 5, array(0, 2000, 0, 1500), 12 << 8, 2, $actionTags);

/*
 * AS3: DoABC with a minimal ABC (zlib compressed)
 */
function u30($v) {
    $data = '';
    do {
        $byte = $v & 0x7F;
        $v >>= 7;
        $data .= chr($byte | ($v ? 0x80 : 0));
    } while ($v);
    return $data;
}
function s32($v) {
    return u30(($v < 0) ? $v + 0x100000000 : $v);
}
function abcString($s) {
    return u30(strlen($s)).$s;
}
$cpool = u30(3).s32(-5).s32(1000).                               // int: [*, -5, 1000]
    u30(2).u30(0xFFFFFFFF).                                       // uint: [*, 4294967295]
    u30(3).pack('e', 1.5).pack('e', -0.25).                       // double: [*, 1.5, -0.25]
    u30(7).abcString('pkg').abcString('Main').abcString('').abcString('Object').
    abcString('frame1').abcString('pkg:Main').                    // string [1..6]
    u30(4).chr(0x16).u30(1).chr(0x16).u30(3).chr(0x18).u30(6).    // namespace: pkg, "", protected pkg:Main
    u30(2).u30(2).u30(1).u30(2).                                  // ns_set: [1, 2]
    u30(5).chr(0x07).u30(1).u30(2).chr(0x07).u30(2).u30(4).       // multiname: pkg::Main, ::Object,
    chr(0x07).u30(2).u30(5).chr(0x09).u30(4).u30(1);              //   ::frame1, Multiname(Object, ns_set 1)
$methods = u30(4).str_repeat(u30(0).u30(0).u30(0).chr(0), 4);     // script init, iinit, cinit, frame1
$instance = u30(1).u30(2).chr(0x09).u30(3).u30(0).u30(1).        // Main extends Object, sealed, protectedNs 3, iinit 1
    u30(1).u30(3).chr(0x01).u30(1).u30(3);                        // trait frame1: Method disp_id 1, method 3
$class = u30(2).u30(0);                                           // cinit 2
$script = u30(0).u30(1).u30(1).chr(0x04).u30(1).u30(0);          // init 0, trait Main: Class slot 1, classi 0
$frame1Code = "\xD0\x30"."\x2F\x01\x29"."\x2F\x02\x29"."\x2D\x01\x29"."\x2E\x01\x29"."\x24\xFB\x29".
    "\x2C\x05\x29"."\x26"."\x12\x01\x00\x00"."\x02"."\x47";
$bodies = array(0 => "\xD0\x30\x47", 1 => "\xD0\x30\xD0\x49\x00\x47", 2 => "\xD0\x30\x47", 3 => $frame1Code);
$methodBodies = u30(4);
foreach ($bodies as $method => $code) {
    $methodBodies .= u30($method).u30(2).u30(1).u30(1).u30(2).u30(strlen($code)).$code.u30(0).u30(0);
}
$abcData = pack('vv', 16, 46).$cpool.$methods.u30(0).u30(1).$instance.$class.u30(1).$script.$methodBodies;
$abcTags = tag(69, chr(0x09).str_repeat("\x00", 3)).             // FileAttributes: ActionScript3, UseNetwork
    tag(9, rgb('ffffff')).
    tag(82, pack('V', 1)."frame1\0".$abcData).                     // DoABC, kDoAbcLazyInitializeFlag
    tag(76, pack('v', 1).pack('v', 0)."pkg.Main\0").              // SymbolClass: 0 => pkg.Main
    showFrame();
$abc = swf('CWS', 10, array(0, 11000, 0, 8000), 24 << 8, 1, $abcTags);

/*
 * truncated: shapes.swf cut in the middle of DefineShape3 (FileLength is kept)
 */
$truncated = substr($shapes, 0, strpos($shapes, pack('v', (32 << 6) | 0x3f)) + 20);

$files = array('shapes.swf' => $shapes, 'text.swf' => $text, 'sprite.swf' => $sprite,
               'action.swf' => $action, 'abc.swf' => $abc, 'truncated.swf' => $truncated);
foreach ($files as $name => $data) {
    file_put_contents("$dir/$name", $data);
    echo "$name: ".strlen($data)." bytes".PHP_EOL;
}
