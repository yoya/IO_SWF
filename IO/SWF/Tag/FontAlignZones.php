<?php

/*
 * DefineFontAlignZones (73)
 *   FontID UI16
 *   CSMTableHint UB[2], Reserved UB[6]
 *   ZoneTable ZONERECORD[]   (タグの終わりまで。グリフ数ぶん)
 * ZONERECORD
 *   NumZoneData UI8, ZoneData ZONEDATA[NumZoneData],
 *   Reserved UB[6], ZoneMaskY UB[1], ZoneMaskX UB[1]
 * ZONEDATA
 *   AlignmentCoordinate FLOAT16, Range FLOAT16
 *   (FLOAT16 は往復でビットが変わらないよう UI16 の生値で保持し、dump 時だけ数値化する)
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';

class IO_SWF_Tag_FontAlignZones extends IO_SWF_Tag_Base {
    static $CSMTableHintNoteList = array('thin', 'medium', 'thick', 'reserved');
    var $CharacterID;    // FontID
    var $CSMTableHint;
    var $Reserved;
    var $ZoneTable = array();
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->CSMTableHint = $reader->getUIBits(2);
        $this->Reserved = $reader->getUIBits(6);
        $this->ZoneTable = array();
        while ($reader->hasNextData(1)) {
            list($start, $dummy) = $reader->getOffset();
            $numZoneData = $reader->getUI8();
            // NumZoneData 個の ZONEDATA (4 バイト) + マスク (1 バイト) が無ければ打ち切る
            if (! $reader->hasNextData($numZoneData * 4 + 1)) {
                $reader->setOffset($start, 0);
                break;
            }
            $zoneData = array();
            for ($i = 0; $i < $numZoneData; $i++) {
                $zoneData []= array(
                    'AlignmentCoordinate' => $reader->getUI16LE(),
                    'Range' => $reader->getUI16LE(),
                );
            }
            $record = array('ZoneData' => $zoneData);
            $record['Reserved'] = $reader->getUIBits(6);
            $record['ZoneMaskY'] = $reader->getUIBit();
            $record['ZoneMaskX'] = $reader->getUIBit();
            $this->ZoneTable []= $record;
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    // IEEE 754 半精度 (符号 1, 指数 5, 仮数 10) を数値にする (dump 用)
    static function float16ToFloat($bits) {
        $sign = ($bits & 0x8000) ? -1 : 1;
        $exp = ($bits >> 10) & 0x1f;
        $frac = $bits & 0x3ff;
        if ($exp == 0) {
            return $sign * $frac * pow(2, -24);
        }
        if ($exp == 0x1f) {
            return $frac ? NAN : $sign * INF;
        }
        return $sign * (1 + $frac / 1024) * pow(2, $exp - 15);
    }

    function dumpContent($tagCode, $opts = array()) {
        $hint = self::$CSMTableHintNoteList[$this->CSMTableHint];
        echo "    FontID: {$this->CharacterID}  CSMTableHint: {$this->CSMTableHint}($hint)\n";
        echo "    ZoneTable: (".count($this->ZoneTable)." records)\n";
        foreach ($this->ZoneTable as $idx => $record) {
            echo "      [$idx] MaskX:{$record['ZoneMaskX']} MaskY:{$record['ZoneMaskY']}";
            foreach ($record['ZoneData'] as $zd) {
                printf(" (Align:%g Range:%g)",
                       self::float16ToFloat($zd['AlignmentCoordinate']),
                       self::float16ToFloat($zd['Range']));
            }
            echo "\n";
        }
        if (! is_null($this->_rest)) {
            echo "    (trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        $writer->putUIBits($this->CSMTableHint, 2);
        $writer->putUIBits($this->Reserved, 6);
        foreach ($this->ZoneTable as $record) {
            $writer->putUI8(count($record['ZoneData']));
            foreach ($record['ZoneData'] as $zd) {
                $writer->putUI16LE($zd['AlignmentCoordinate']);
                $writer->putUI16LE($zd['Range']);
            }
            $writer->putUIBits($record['Reserved'], 6);
            $writer->putUIBit($record['ZoneMaskY']);
            $writer->putUIBit($record['ZoneMaskX']);
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
