<?php

/*
 * 2010/8/11- (c) yoya@awm.jp
 */

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/SWF/Type.php';

class IO_SWF {
    // instance variable
    var $_headers = array(); // protected
    var $_tags = array();    // protected

    function parse($swfdata) {
        $reader = new IO_Bit();
        $reader->input($swfdata);

        /* SWF Header */
        $this->_headers['Signature'] = $reader->getData(3);
        $this->_headers['Version'] = $reader->getUI8();
        $this->_headers['FileLength'] = $reader->getUI32LE();
        if ($this->_headers['Signature']{0} == 'C') {
            // CWS の場合、FileLength の後ろが zlib 圧縮されている
            $uncompressed_data = gzuncompress(substr($swfdata, 8));
            if ($uncompressed_data === false) {
                return false;
            }
            $reader = new IO_Bit();
            $reader->input($uncompressed_data);
        }
        /* SWF Movie Header */
        $this->_headers['FrameSize'] = IO_SWF_Type::parseRECT($reader);
        $reader->byteAlign();
        $this->_headers['FrameRate'] = $reader->getUI16LE();
        $this->_headers['FrameCount'] = $reader->getUI16LE();
        
        /* SWF Tags */
        while (true) {
            $tag = Array();
            $tagAndLength = $reader->getUI16LE();
            $code = $tagAndLength >> 6;
            $length = $tagAndLength & 0x3f;
            if ($length == 0x3f) { // long format
                $length = $reader->getUI32LE();
                $tag['LongFormat'] = true;
            }
            $tag['Code']  = $code;
            $tag['Length'] = $length;
            $tag['Content'] = $reader->getData($length);
            $this->_tags[] = $tag;
            if ($code == 0) { // END Tag
                break;
            }
        }
        return true;
    }
    // function dump() => IO_SWF_Dumper
    
    function build() {
        $writer_head = new IO_Bit();
        $writer = new IO_Bit();

        /* SWF Header */
        $writer_head->putData($this->_headers['Signature']);
        $writer_head->putUI8($this->_headers['Version']);
        $writer_head->putUI32LE($this->_headers['FileLength']);

        /* SWF Movie Header */
	IO_SWF_Type::buildRECT($writer, $this->_headers['FrameSize']);
        $writer->byteAlign();
        $writer->putUI16LE($this->_headers['FrameRate']);
        $writer->putUI16LE($this->_headers['FrameCount']);
        
        /* SWF Tags */
        foreach ($this->_tags as $tag) {
            $code = $tag['Code'];
            $length = $tag['Length'];
            if (empty($tag['LongFormat']) && ($length < 0x3f)) {
                $tagAndLength = ($code << 6) | $length;
                $writer->putUI16LE($tagAndLength);
            } else {
                $tagAndLength = ($code << 6) | 0x3f;
                $writer->putUI16LE($tagAndLength);
                $writer->putUI32LE($length);
            }
            $writer->putData($tag['Content']);
        }
        list($fileLength, $bit_offset_dummy) = $writer->getOffset();
        $fileLength += 8; // swf header
        $this->_headers['FileLength'] = $fileLength;
        $writer_head->setUI32LE($fileLength, 4);
        if ($this->_headers['Signature']{0} == 'C') {
            return $writer_head->output() . gzcompress($writer->output());
        }
        return $writer_head->output().$writer->output();
    }
}
