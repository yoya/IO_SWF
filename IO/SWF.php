<?php

/*
 * 2010/8/11- (c) yoya@awm.jp - v4.1.5
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/SWF/Type/RECT.php';
require_once dirname(__FILE__).'/SWF/Type/MATRIX.php';
require_once dirname(__FILE__).'/SWF/Tag.php';

class IO_SWF {
    // instance variable
    var $_headers = array(); // protected
    var $_header_size; // XXX
    var $_tags = array();    // protected
    // for debug
    var $_swfdata = null;
    /*
     * parse
     */
    function parse($swfdata, $opts = array()) {
        $signature = substr($swfdata, 0, 3);
        switch ($signature) {
        case 'FWS':
        case 'CWS':
        case 'ZWS':
            $this->parseSWF($swfdata, $opts);
            break;
        default:
            $this->parseTag($swfdata, $opts);
            break;
        }
    }
    function parseSWF($swfdata, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($swfdata);
        $this->_swfdata  = $swfdata;
        /* SWF Header */
        $signature = $reader->getData(3);
        $this->_headers['Signature'] = $signature;
        $this->_headers['Version'] = $reader->getUI8();
        $this->_headers['FileLength'] = $reader->getUI32LE();
        if ($signature === 'FWS') {
            ;
        } else if ($signature === 'CWS') {
            // CWS の場合、FileLength の後ろが zlib 圧縮されている
            $uncompressed_data = gzuncompress(substr($swfdata, 8));
            if ($uncompressed_data === false) {
                return false;
            }
            list($byte_offset, $dummy) = $reader->getOffset();
            $reader->setOffset(0, 0);
            $swfdata = $reader->getData($byte_offset) . $uncompressed_data;
            $reader = new IO_Bit();
            $reader->input($swfdata);
            $this->_swfdata  = $swfdata;
            $reader->setOffset($byte_offset, 0);
        } else if ($signature === 'ZWS') {
            throw new IO_SWF_Exception("ZWS unsupported");
        } else {
            throw new IO_SWF_Exception("no SWF signature($signature)");
        }
        /* SWF Movie Header */
        $this->_headers['FrameSize'] = IO_SWF_Type_RECT::parse($reader);
        $reader->byteAlign();
        $this->_headers['FrameRate'] = $reader->getUI16LE();
        $this->_headers['FrameCount'] = $reader->getUI16LE();

        list($this->_header_size, $dummy) = $reader->getOffset();
        
        /* SWF Tags */
        while (true) {
            $swfInfo = array('Version' => $this->_headers['Version']);
            $tag = new IO_SWF_Tag($swfInfo);
            $tag->parse($reader, $opts);
            $this->_tags[] = $tag;
            if ($tag->code == 0) { // END Tag
                break;
            }
        }
        return true;
    }
    function parseTag($tagdata, $opts = array()) {
        $reader = new IO_Bit();
        $reader->input($tagdata);
        $swfInfo = array('Version' => 99999);
        $tag = new IO_SWF_Tag($swfInfo);
        $tag->parse($reader, $opts);
        $this->_tags[] = $tag;
    }
    function parseAllTagContent($opts) {
        foreach ($this->_tags as &$tag) {
            $tag->parseTagContent($opts);
            // keep the original binary
        }
    }
    /*
     * build
     */
    function build($opts = []) {
        $opts['preserveStyleState'] = ! empty($opts['preserveStyleState']);
        if (isset($this->_headers['Signature'])) {
            return $this->buildSWF($opts);
        } else {
            return $this->buildTags($opts);
        }
    }
    function buildSWF($opts = []) {

        $writer_head = new IO_Bit();
        $writer = new IO_Bit();

        /* SWF Header */
        $writer_head->putData($this->_headers['Signature']);
        $writer_head->putUI8($this->_headers['Version']);
        $writer_head->putUI32LE($this->_headers['FileLength']);

        /* SWF Movie Header */
        IO_SWF_Type_RECT::build($writer, $this->_headers['FrameSize']);
        $writer->byteAlign();
        $writer->putUI16LE($this->_headers['FrameRate']);
        $writer->putUI16LE($this->_headers['FrameCount']);

        /* SWF Tags */
        foreach ($this->_tags as $idx => $tag) {
            $tagData = $tag->build($opts);
            if ($tagData != false) {
                $writer->putData($tagData);
            } else {
                throw new IO_SWF_Exception("tag build failed (tag idx=$idx)");
            }
        }
        list($fileLength, $bit_offset_dummy) = $writer->getOffset();
        $fileLength += 8; // swf header
        $this->_headers['FileLength'] = $fileLength;
        $writer_head->setUI32LE($fileLength, 4);
        if ($this->_headers['Signature'][0] == 'C') {
            return $writer_head->output() . gzcompress($writer->output());
        }
        return $writer_head->output().$writer->output();
    }
    function buildTags($opts = []) {
        $writer = new IO_Bit();
        /* SWF Tags */
        foreach ($this->_tags as $idx => $tag) {
            $tagData = $tag->build($opts);
            if ($tagData != false) {
                $writer->putData($tagData);
            } else {
                throw new IO_SWF_Exception("tag build failed (tag idx=$idx)");
            }
        }
        return $writer->output();
    }
    /*
     * dump
     */
    function dump($opts = array()) {
        if (isset($this->_headers['Signature'])) {
            $this->dumpSWF($opts);
        } else {
            $this->dumpTag($opts);
        }
    }
    function dumpSWF($opts = array()) {
        if (empty($opts['hexdump']) === false) {
            $bitio = new IO_Bit();
            $bitio->input($this->_swfdata);
        }
        /* SWF Header */
        echo 'Signature: '.$this->_headers['Signature'].PHP_EOL;
        echo 'Version: '.$this->_headers['Version'].PHP_EOL;
        echo 'FileLength: '.$this->_headers['FileLength'].PHP_EOL;
        echo 'FrameSize: '. IO_SWF_Type_RECT::string($this->_headers['FrameSize'])."\n";
        echo 'FrameRate: '.($this->_headers['FrameRate'] / 0x100).PHP_EOL;
        echo 'FrameCount: '.$this->_headers['FrameCount'].PHP_EOL;

        if (empty($opts['hexdump']) === false) {
            $bitio->hexdump(0, $this->_header_size);
            $opts['bitio'] =& $bitio; // for tag
        }
        /* SWF Tags */
        $opts['indent'] = 0;
        if ($this->_headers['Version'] < 6) {
            ob_start('mb_convert_encoding_from_sjis');
        }
        echo 'Tags:'.PHP_EOL;
        $opts['FrameNum'] = 0;
        $this->dumpTag($opts);
    }
    function dumpTag($opts = array()) {
        foreach ($this->_tags as $tag) {
            try {
                $tag->dump($opts);
                if ($tag->code === 60) {  // DefineVideoStream
                    if (! isset($opts['_CodecID'])) {
                        $opts['_CodecID'] = [];
                    }
                    $opts[$tag->tag->_CharacterID] = $tag->tag->_CodecID;
                }
            } catch (IO_Bit_Exception $e) {
                echo "(tag dump failed) $e\n";
            }
            if (isset($this->_headers['Version']) && ($this->_headers['Version'] < 6)) {
                ob_flush();
            }
        }
    }
}

function mb_convert_encoding_from_sjis($a) {
    return mb_convert_encoding($a, 'UTF-8', 'SJIS-win');
}
