<?php

/*
 * SOUNDINFO (StartSound / StartSound2 / DefineButtonSound で使う)
 *   Reserved UB[2], SyncStop UB[1], SyncNoMultiple UB[1], HasEnvelope UB[1],
 *   HasLoops UB[1], HasOutPoint UB[1], HasInPoint UB[1],
 *   InPoint UI32 (HasInPoint), OutPoint UI32 (HasOutPoint),
 *   LoopCount UI16 (HasLoops), EnvPoints UI8 (HasEnvelope),
 *   EnvelopeRecords SOUNDENVELOPE[EnvPoints] (HasEnvelope)
 * SOUNDENVELOPE: Pos44 UI32, LeftLevel UI16, RightLevel UI16
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/../Type.php';

class IO_SWF_Type_SOUNDINFO implements IO_SWF_Type {
    static function parse(&$reader, $opts = array()) {
        $info = array();
        $info['Reserved'] = $reader->getUIBits(2);
        $info['SyncStop'] = $reader->getUIBit();
        $info['SyncNoMultiple'] = $reader->getUIBit();
        $hasEnvelope = $reader->getUIBit();
        $hasLoops = $reader->getUIBit();
        $hasOutPoint = $reader->getUIBit();
        $hasInPoint = $reader->getUIBit();
        // Has* フラグは build 時に値の有無から決めるので、値だけ持つ
        if ($hasInPoint) {
            $info['InPoint'] = $reader->getUI32LE();
        }
        if ($hasOutPoint) {
            $info['OutPoint'] = $reader->getUI32LE();
        }
        if ($hasLoops) {
            $info['LoopCount'] = $reader->getUI16LE();
        }
        if ($hasEnvelope) {
            $envPoints = $reader->getUI8();
            $records = array();
            for ($i = 0; $i < $envPoints; $i++) {
                $records []= array(
                    'Pos44'      => $reader->getUI32LE(),
                    'LeftLevel'  => $reader->getUI16LE(),
                    'RightLevel' => $reader->getUI16LE(),
                );
            }
            $info['EnvelopeRecords'] = $records;
        }
        return $info;
    }
    static function build(&$writer, $info, $opts = array()) {
        $hasInPoint = isset($info['InPoint']);
        $hasOutPoint = isset($info['OutPoint']);
        $hasLoops = isset($info['LoopCount']);
        $hasEnvelope = isset($info['EnvelopeRecords']);
        $writer->putUIBits(isset($info['Reserved']) ? $info['Reserved'] : 0, 2);
        $writer->putUIBit($info['SyncStop']);
        $writer->putUIBit($info['SyncNoMultiple']);
        $writer->putUIBit($hasEnvelope ? 1 : 0);
        $writer->putUIBit($hasLoops ? 1 : 0);
        $writer->putUIBit($hasOutPoint ? 1 : 0);
        $writer->putUIBit($hasInPoint ? 1 : 0);
        if ($hasInPoint) {
            $writer->putUI32LE($info['InPoint']);
        }
        if ($hasOutPoint) {
            $writer->putUI32LE($info['OutPoint']);
        }
        if ($hasLoops) {
            $writer->putUI16LE($info['LoopCount']);
        }
        if ($hasEnvelope) {
            $writer->putUI8(count($info['EnvelopeRecords']));
            foreach ($info['EnvelopeRecords'] as $record) {
                $writer->putUI32LE($record['Pos44']);
                $writer->putUI16LE($record['LeftLevel']);
                $writer->putUI16LE($record['RightLevel']);
            }
        }
    }
    static function string($info, $opts = array()) {
        $text = "SyncStop:{$info['SyncStop']} SyncNoMultiple:{$info['SyncNoMultiple']}";
        if (isset($info['InPoint'])) {
            $text .= " InPoint:{$info['InPoint']}";
        }
        if (isset($info['OutPoint'])) {
            $text .= " OutPoint:{$info['OutPoint']}";
        }
        if (isset($info['LoopCount'])) {
            $text .= " LoopCount:{$info['LoopCount']}";
        }
        if (isset($info['EnvelopeRecords'])) {
            $text .= " Envelope(".count($info['EnvelopeRecords'])."):";
            foreach ($info['EnvelopeRecords'] as $r) {
                $text .= " [Pos44:{$r['Pos44']} L:{$r['LeftLevel']} R:{$r['RightLevel']}]";
            }
        }
        return $text;
    }
}
