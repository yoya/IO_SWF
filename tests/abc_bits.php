<?php

require_once dirname(__FILE__).'/../IO/SWF/ABC/Bit.php';

// https://ja.osdn.net/projects/happyabc/scm/git/happyabc/blobs/master/swflib/bytesOutTest.ml

test_u30([0], 0);
test_u30([0x7F],  0x7F);
// "2byte" >::
test_u30([0xFF,0x30], 0x187F);
test_u30([0xFF,0x01], 0xFF);
test_u30([0xFF,0x7F], 0x3FFF);
// "3byte/15-21bit" >::
test_u30([0xFF,0xFF,0x01], 0x7FFF);
test_u30([0xFF,0xFF,0x7F], 0x1FFFFF);
// "4 byte/22-28bit" >::
test_u30([0xFF,0xFF,0xFF,0x01], 0x003FFFFF);
test_u30([0xFF,0xFF,0xFF,0x7F], 0x0FFFFFFF);
// "5 byte/29-35bit" >::
test_u30([0xFF,0xFF,0xFF,0xFF,0x01], 0x1FFFFFFF);
test_u30([0xFF,0xFF,0xFF,0xFF,0x03] ,0x3FFFFFFF);

//  "s32" >::
test_s32([0x00], 0x00);
test_s32([0x20], 0x20);
test_s32([0xF6,0xFF,0xFF,0xFF,0x0F], -10);

exit (0);

function decArray2bin($arr) { // [0x12, 0x34] => "\x12\x34"
    return implode(array_map("chr", $arr));
}

function test_u30($arr, $value) {
    $bit = new IO_SWF_ABC_Bit();
    $bit->input(decArray2bin($arr));
    assert($bit->get_u30() == $value);
}

function test_s32($arr, $value) {
    $bit = new IO_SWF_ABC_Bit();
    $bit->input(decArray2bin($arr));
    assert($bit->get_s32() == $value);
}
