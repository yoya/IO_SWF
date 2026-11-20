<?php

/*
 * DefineSceneAndFrameLabelData (86)
 *   SceneCount EncodedU32
 *   [Offset EncodedU32, Name STRING] x SceneCount
 *   FrameLabelCount EncodedU32
 *   [FrameNum EncodedU32, FrameLabel STRING] x FrameLabelCount
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/String.php';
require_once dirname(__FILE__).'/../Type/EncodedU32.php';

class IO_SWF_Tag_SceneAndFrameLabel extends IO_SWF_Tag_Base {
    var $_scenes = array();       // [ ['Offset' => frame, 'Name' => name], ... ]
    var $_frameLabels = array();  // [ ['FrameNum' => frame, 'FrameLabel' => label], ... ]
    var $_rest = null;

    function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($content);
        $sceneCount = IO_SWF_Type_EncodedU32::parse($reader);
        $this->_scenes = array();
        for ($i = 0; $i < $sceneCount; $i++) {
            $offset = IO_SWF_Type_EncodedU32::parse($reader);
            $name = IO_SWF_Type_String::parse($reader);
            $this->_scenes []= array('Offset' => $offset, 'Name' => $name);
        }
        $frameLabelCount = IO_SWF_Type_EncodedU32::parse($reader);
        $this->_frameLabels = array();
        for ($i = 0; $i < $frameLabelCount; $i++) {
            $frameNum = IO_SWF_Type_EncodedU32::parse($reader);
            $label = IO_SWF_Type_String::parse($reader);
            $this->_frameLabels []= array('FrameNum' => $frameNum, 'FrameLabel' => $label);
        }
        if ($reader->hasNextData(1)) {
            $this->_rest = $reader->getDataUntil(false);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tScenes (count:".count($this->_scenes).")\n";
        foreach ($this->_scenes as $idx => $scene) {
            echo "\t[$idx] Offset: {$scene['Offset']}  Name: {$scene['Name']}\n";
        }
        echo "\tFrameLabels (count:".count($this->_frameLabels).")\n";
        foreach ($this->_frameLabels as $idx => $fl) {
            echo "\t[$idx] FrameNum: {$fl['FrameNum']}  FrameLabel: {$fl['FrameLabel']}\n";
        }
        if (! is_null($this->_rest)) {
            echo "\t(trailing data: ".strlen($this->_rest)." bytes)\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        IO_SWF_Type_EncodedU32::build($writer, count($this->_scenes));
        foreach ($this->_scenes as $scene) {
            IO_SWF_Type_EncodedU32::build($writer, $scene['Offset']);
            IO_SWF_Type_String::build($writer, $scene['Name']);
        }
        IO_SWF_Type_EncodedU32::build($writer, count($this->_frameLabels));
        foreach ($this->_frameLabels as $fl) {
            IO_SWF_Type_EncodedU32::build($writer, $fl['FrameNum']);
            IO_SWF_Type_String::build($writer, $fl['FrameLabel']);
        }
        if (! is_null($this->_rest)) {
            $writer->putData($this->_rest);
        }
        return $writer->output();
    }
}
