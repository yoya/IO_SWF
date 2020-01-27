<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/RECT.php';
require_once dirname(__FILE__).'/../Type/RGBA.php';
require_once dirname(__FILE__).'/../Type/String.php';

class IO_SWF_Tag_EditText extends IO_SWF_Tag_Base {
   var $CharacterID;
   var $Bounds;
   var $WordWrap, $Multiline, $Password, $ReadOnly;
   var $AutoSize, $NoSelect, $Border, $WasStatic, $HTML, $UseOutlines;
   var $FontID = null, $FontClass = null, $FontHeight = null;
   var $TextColor = null, $MaxLength = null;
   var $Align = null, $LeftMargin, $RightMargin, $Indent, $Leading;
   var $VariableName, $InitialText = null;
   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->CharacterID = $reader->getUI16LE();
        $this->Bounds = IO_SWF_Type_RECT::parse($reader);
        // ----
        $reader->byteAlign();
        $hasText         = $reader->getUIBit();
        $this->WordWrap  = $reader->getUIBit();
        $this->Multiline = $reader->getUIBit();
        $this->Password  = $reader->getUIBit();
        $this->ReadOnly  = $reader->getUIBit();
        $hasTextColor    = $reader->getUIBit();
        $hasMaxLength    = $reader->getUIBit();
        $hasFont         = $reader->getUIBit();
        // ----
        $hasFontClass      = $reader->getUIBit();
        $this->AutoSize    = $reader->getUIBit();
        $hasLayout         = $reader->getUIBit();
        $this->NoSelect    = $reader->getUIBit();
        $this->Border      = $reader->getUIBit();
        $this->WasStatic   = $reader->getUIBit();
        $this->HTML        = $reader->getUIBit();
        $this->UseOutlines = $reader->getUIBit();
        if ($hasFont) {
            $this->FontID = $reader->getUI16LE();
        }
        if ($hasFontClass) {
            $this->FontClass = IO_SWF_Type_String::parse($reader);
        }
        if ($hasFont) {
            $this->FontHeight = $reader->getUI16LE();
        }
        if ($hasTextColor) {
            $this->TextColor = IO_SWF_Type_RGBA::parse($reader);
        }
        if ($hasMaxLength) {
            $this->MaxLength = $reader->getUI16LE();
        }
        if ($hasLayout) {
            $this->Align       = $reader->getUI8();
            $this->LeftMargin  = $reader->getUI16LE();
            $this->RightMargin = $reader->getUI16LE();
            $this->Indent      = $reader->getUI16LE();
            $this->Leading     = $reader->getSI16LE();
        }
        
        $this->VariableName = IO_SWF_Type_String::parse($reader);
        if ($hasText) {
            $this->InitialText = IO_SWF_Type_String::parse($reader);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        echo "\tCharacterID:{$this->CharacterID}\n";
        echo "\t".IO_SWF_Type_RECT::string($this->Bounds)."\n";
        echo "\tWordWrap:{$this->WordWrap} Multiline:{$this->Multiline} Password:{$this->Password} ReadOnly:{$this->ReadOnly}\n";
        if (is_null($this->FontID) === false) {
            echo "\tFontID:{$this->FontID} FontHeight:".($this->FontHeight/20)."\n";
        }
        if (is_null($this->FontClass) === false) {
            echo "\tFontClass:{$this->FontClass}({".bin2hex($this->FontClass).")\n";
        }
        if (is_null($this->TextColor) === false) {
            echo "\tTextColor".IO_SWF_Type_RGBA::string($this->TextColor)."\n";
        }
        if (is_null($this->MaxLength) === false) {
            echo "\tMaxLength:{$this->MaxLength}\n";
        }
        if (is_null($this->Align) === false) {
            echo "\tAlign:{$this->Align} LeftMargin:{$this->LeftMargin} RightMargin:{$this->RightMargin} Indent:{$this->Indent} Leading:".($this->Leading/20)."\n";
        }
        echo "\tVariableName:{$this->VariableName}\n";
        if (is_null($this->InitialText) == false) {
            echo "\tInitialText:{$this->InitialText}\n";
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        $writer->putUI16LE($this->CharacterID);
        IO_SWF_Type_RECT::build($writer, $this->Bounds);
        // ----
        $hasText = is_null($this->InitialText)?0:1;
        $hasTextColor = is_null($this->TextColor)?0:1;
        $hasMaxLength = is_null($this->MaxLength)?0:1;
        $hasFont = is_null($this->FontID)?0:1;
        $hasFontClass = is_null($this->FontClass)?0:1;
        $hasLayout = is_null($this->Align)?0:1;
        // ----
        $writer->byteAlign();
        $writer->putUIBit($hasText);
        $writer->putUIBit($this->WordWrap);
        $writer->putUIBit($this->Multiline);
        $writer->putUIBit($this->Password);
        $writer->putUIBit($this->ReadOnly);
        $writer->putUIBit($hasTextColor);
        $writer->putUIBit($hasMaxLength);
        $writer->putUIBit($hasFont);
        // ----
        $writer->putUIBit($hasFontClass);
        $writer->putUIBit($this->AutoSize);
        $writer->putUIBit($hasLayout);
        $writer->putUIBit($this->NoSelect);
        $writer->putUIBit($this->Border);
        $writer->putUIBit($this->WasStatic);
        $writer->putUIBit($this->HTML);
        $writer->putUIBit($this->UseOutlines);
        if ($hasFont) {
            $writer->putUI16LE($this->FontID);
        }
        if ($hasFontClass) {
            IO_SWF_Type_String::build($writer, $this->FontClass);
        }
        if ($hasFont) {
            $writer->putUI16LE($this->FontHeight);
        }
        if ($hasTextColor) {
            IO_SWF_Type_RGBA::build($writer, $this->TextColor);
        }
        if ($hasMaxLength) {
            $writer->putUI16LE($this->MaxLength);
        }
        if ($hasLayout) {
            $writer->putUI8($this->Align);
            $writer->putUI16LE($this->LeftMargin);
            $writer->putUI16LE($this->RightMargin);
            $writer->putUI16LE($this->Indent);
            $writer->putSI16LE($this->Leading);
        }
        IO_SWF_Type_String::build($writer, $this->VariableName);
        if ($hasText) {
            IO_SWF_Type_String::build($writer, $this->InitialText);
        }
    	return $writer->output();
    }
}
