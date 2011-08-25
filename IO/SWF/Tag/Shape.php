<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/RECT.php';
require_once dirname(__FILE__).'/../Type/FILLSTYLEARRAY.php';
require_once dirname(__FILE__).'/../Type/LINESTYLEARRAY.php';
require_once dirname(__FILE__).'/../Type/SHAPE.php';

class IO_SWF_Tag_Shape extends IO_SWF_Tag_Base {
    var $_shapeId = null;
    // DefineShape, DefineShape2, DefineShape3
    var $_shapeBounds;
    var $_fillStyles, $_lineStyles;
    var $_shapeRecords;
    // DefineMorphShape
    var $_startBounds, $_endBounds;
    var $_offset;
    var $_morphFillStyles, $_morphLineStyles;
    var $_startEdge, $_endEdges;

   function parseContent($tagCode, $content, $opts = array()) {

        $isMorph = ($tagCode == 46) || ($tagCode == 84);

        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_shapeId = $reader->getUI16LE();

        $opts = array('tagCode' => $tagCode, 'isMorph' => $isMorph);

        if ($isMorph === false) {
        	// 描画スタイル
            $this->_shapeBounds = IO_SWF_TYPE_RECT::parse($reader);
            $this->_fillStyles = IO_SWF_TYPE_FILLSTYLEARRAY::parse($reader, $opts);
        	$this->_lineStyles = IO_SWF_TYPE_LINESTYLEARRAY::parse($reader, $opts);
        	// 描画枠
            $this->_shapeRecords = IO_SWF_Type_SHAPE::parse($reader, $opts);
        } else {
            $this->_startBounds = IO_SWF_TYPE_RECT::parse($reader);
            $this->_endBounds = IO_SWF_TYPE_RECT::parse($reader);
            list($offset_offset, $dummy) = $reader->getOffset();
            $this->_offset = $reader->getUI32LE();
        	// 描画スタイル
            $this->_morphFillStyles = IO_SWF_TYPE_FILLSTYLEARRAY::parse($reader, $opts);
        	$this->_morphLineStyles = IO_SWF_TYPE_LINESTYLEARRAY::parse($reader, $opts);
        	// 描画枠
            $this->_startEdge = IO_SWF_Type_SHAPE::parse($reader, $opts);
            list($end_edge_offset, $dummy) = $reader->getOffset();
            if ($offset_offset + $this->_offset + 4 != $end_edge_offset) {
                // warn!
                $reader->setOffset($offset_offset + $this->_offset + 4, 9);
            }
            $this->_endEdge   = IO_SWF_Type_SHAPE::parse($reader, $opts);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        if (is_null($this->_shapeId) === false) {
            echo "    ShapeId: {$this->_shapeId}\n";
        }
        $opts = array('tagCode' => $tagCode, 'isMorph' => $isMorph);

        if ($isMorph === false) {
            echo "    ShapeBounds: ". IO_SWF_Type_RECT::string($this->_shapeBounds)."\n";
            echo "    FillStyles:\n";
            echo IO_SWF_Type_FILLSTYLEARRAY::string($this->_fillStyles, $opts);
            echo "    LineStyles:\n";
            echo IO_SWF_Type_LINESTYLEARRAY::string($this->_lineStyles, $opts);

            echo "    ShapeRecords:\n";
            echo IO_SWF_Type_SHAPE::string($this->_shapeRecords, $opts);
        } else {
            echo "    StartBounds: ". IO_SWF_Type_RECT::string($this->_startBounds)."\n";
            echo "    EndBounds: ". IO_SWF_Type_RECT::string($this->_endBounds)."\n";
            echo "    FillStyles:\n";
            echo IO_SWF_Type_FILLSTYLEARRAY::string($this->_morphFillStyles, $opts);
            echo "    LineStyles:\n";
            echo IO_SWF_Type_LINESTYLEARRAY::string($this->_morphLineStyles, $opts);

            echo "    StartEdge:\n";
            echo IO_SWF_Type_SHAPE::string($this->_startEdge, $opts);
            echo "    endEdge:\n";
            echo IO_SWF_Type_SHAPE::string($this->_endEdge, $opts);
        }
    }

    function buildContent($tagCode, $opts = array()) {
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        $writer = new IO_Bit();
        if (empty($opts['noShapeId'])) {
            $writer->putUI16LE($this->_shapeId);
        }
        $opts = array('tagCode' => $tagCode);

        if ($isMorph === false) {
            IO_SWF_Type_RECT::build($writer, $this->_shapeBounds);
            // 描画スタイル
            IO_SWF_Type_FILLSTYLEARRAY::build($writer, $this->_fillStyles, $opts);
            IO_SWF_Type_LINESTYLEARRAY::build($writer, $this->_lineStyles, $opts);
        	// 描画枠
            $opts['fillStyleCount'] = count($this->_fillStyles);
            $opts['lineStyleCount'] = count($this->_lineStyles);
            IO_SWF_Type_SHAPE::build($writer, $this->_shapeRecords, $opts);
        } else {
            IO_SWF_Type_RECT::build($writer, $this->_startBounds);
            IO_SWF_Type_RECT::build($writer, $this->_endBounds);

            $writer->byteAlign();
            list($offset_offset, $dummy) = $writer->getOffset();
            $this->_offset = $writer->putUI32LE(0); // at first, write dummy
            
            // 描画スタイル
            IO_SWF_Type_FILLSTYLEARRAY::build($writer, $this->_morphFillStyles, $opts);
            IO_SWF_Type_LINESTYLEARRAY::build($writer, $this->_morphLineStyles, $opts);
        	// 描画枠
            $opts['fillStyleCount'] = count($this->_morphFillStyles);
            $opts['lineStyleCount'] = count($this->_morphLineStyles);

            // StartEdge
            IO_SWF_Type_SHAPE::build($writer, $this->_startEdge, $opts);

            // EndEdge
            $writer->byteAlign();
            list($end_edge_offset, $dummy) = $writer->getOffset();
            $this->_offset = $end_edge_offset - $offset_offset - 4;
            $writer->setUI32LE($this->_offset, $offset_offset);

            IO_SWF_Type_SHAPE::build($writer, $this->_endEdge, $opts);
        }
        return $writer->output();
    }

    function deforme($threshold) {
        $startIndex = null;
        foreach ($this->_shapeRecords as $shapeRecordIndex => $shapeRecord) {
            if (($shapeRecord['TypeFlag'] == 0) && (isset($shapeRecord['EndOfShape']) === false)) {
                // StyleChangeRecord
                $endIndex = $shapeRecordIndex - 1;
                if (is_null($startIndex) === false) {
                    $this->deformeShapeRecordUnit($threshold, $startIndex, $endIndex);
                }
                $startIndex = $shapeRecordIndex;
            }
            if (isset($shapeRecord['EndOfShape']) && ($shapeRecord['EndOfShape']) == 0) {
                // EndShapeRecord
                $endIndex = $shapeRecordIndex - 1;
               $this->deformeShapeRecordUnit($threshold, $startIndex, $endIndex);
            }
        }
        $this->_shapeRecords = array_values($this->_shapeRecords);
    }

    function deformeShapeRecordUnit($threshold, $startIndex, $endIndex) {
//        return $this->deformeShapeRecordUnit_1($threshold, $startIndex, $endIndex);
        return $this->deformeShapeRecordUnit_2($threshold, $startIndex, $endIndex);
    }
    function deformeShapeRecordUnit_1($threshold, $startIndex, $endIndex) {
        $threshold_2 = $threshold * $threshold;
        $shapeRecord = $this->_shapeRecords[$startIndex];
        $prevIndex = null;
        $currentDrawingPositionX = $shapeRecord['MoveX'];
        $currentDrawingPositionY = $shapeRecord['MoveY'];
        for ($i = $startIndex + 1 ;$i <= $endIndex; $i++) {
            $shapeRecord = & $this->_shapeRecords[$i];
            if ($shapeRecord['StraightFlag'] == 0) {
                // 曲線に対する処理
                $diff_x = $shapeRecord['ControlX'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['ControlY'] - $currentDrawingPositionY;
                $distance_2_control = $diff_x * $diff_x + $diff_y * $diff_y;
                $diff_x = $shapeRecord['AnchorX'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['AnchorY'] - $currentDrawingPositionY;
                $distance_2_anchor = $diff_x * $diff_x + $diff_y * $diff_y;
//                if (max($distance_2_control, $distance_2_anchor) > $threshold_2) {
                if (($distance_2_control +  $distance_2_anchor) > $threshold_2) {
                    // 何もしない
                    $prevIndex = $i;
                    $prevDrawingPositionX = $currentDrawingPositionX;
                    $prevDrawingPositionY = $currentDrawingPositionY;
                    $currentDrawingPositionX = $shapeRecord['AnchorX'];
                    $currentDrawingPositionY = $shapeRecord['AnchorY'];
                    continue; // skip
                }
                // 直線に変換する
                $shapeRecord['StraightFlag'] = 1; // to Straight
                $shapeRecord['X'] = $shapeRecord['AnchorX'];
                $shapeRecord['Y'] = $shapeRecord['AnchorY'];
                unset($shapeRecord['ControlX'], $shapeRecord['ControlY']);
                unset($shapeRecord['AnchorX'], $shapeRecord['AnchorY']);
            }
            if (is_null($prevIndex)) {
                // 何もしない
                $prevIndex = $i;
                $prevDrawingPositionX = $currentDrawingPositionX;
                $prevDrawingPositionY = $currentDrawingPositionY;
                $currentDrawingPositionX = $shapeRecord['X'];
                $currentDrawingPositionY = $shapeRecord['Y'];
                continue; // skip
            }
            $diff_x = $shapeRecord['X'] - $prevDrawingPositionX;
            $diff_y = $shapeRecord['Y'] - $prevDrawingPositionY;
            $distance_2 = $diff_x * $diff_x + $diff_y * $diff_y;
            if ($distance_2 > $threshold_2) {
                // 何もしない
                $prevIndex = $i;
                $prevDrawingPositionX = $currentDrawingPositionX;
                $prevDrawingPositionY = $currentDrawingPositionY;
                $currentDrawingPositionX = $shapeRecord['X'];
                $currentDrawingPositionY = $shapeRecord['Y'];
                continue; // skip
            }
            // 前の直線にくっつける。
            $prevShapeRecord = & $this->_shapeRecords[$prevIndex];
            $prevShapeRecord['X'] = $shapeRecord['X'];
            $prevShapeRecord['Y'] = $shapeRecord['Y'];
            $currentDrawingPositionX = $shapeRecord['X'];
            $currentDrawingPositionY = $shapeRecord['Y'];
            unset($this->_shapeRecords[$i]);
        }
    }

    function deformeShapeRecordUnit_2($threshold, $startIndex, $endIndex) {
        $this->deformeShapeRecordUnit_2_curve($threshold, $startIndex, $endIndex);
        while ($this->deformeShapeRecordUnit_2_line($threshold, $startIndex, $endIndex));
    }

    function deformeShapeRecordUnit_2_curve($threshold, $startIndex, $endIndex) {
        $threshold_2 = $threshold * $threshold;
        $shapeRecord = $this->_shapeRecords[$startIndex];
        $currentDrawingPositionX = $shapeRecord['MoveX'];
        $currentDrawingPositionY = $shapeRecord['MoveY'];
        for ($i = $startIndex + 1 ;$i <= $endIndex; $i++) {
            $shapeRecord = & $this->_shapeRecords[$i];
            if ($shapeRecord['StraightFlag'] == 0) {
            // 曲線に対する処理
                $diff_x = $shapeRecord['ControlX'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['ControlY'] - $currentDrawingPositionY;
                $distance_2_control = $diff_x * $diff_x + $diff_y * $diff_y;
                $diff_x = $shapeRecord['AnchorX'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['AnchorY'] - $currentDrawingPositionY;
                $distance_2_anchor = $diff_x * $diff_x + $diff_y * $diff_y;
                if (($distance_2_control +  $distance_2_anchor) > $threshold_2) {
                    // 何もしない
                    $currentDrawingPositionX = $shapeRecord['AnchorX'];
                    $currentDrawingPositionY = $shapeRecord['AnchorY'];
                    continue; // skip
                }
                // 直線に変換する
                $shapeRecord['StraightFlag'] = 1; // to Straight
                $shapeRecord['X'] = $shapeRecord['AnchorX'];
                $shapeRecord['Y'] = $shapeRecord['AnchorY'];
                unset($shapeRecord['ControlX'], $shapeRecord['ControlY']);
                unset($shapeRecord['AnchorX'], $shapeRecord['AnchorY']);
                $currentDrawingPositionX = $shapeRecord['X'];
                $currentDrawingPositionY = $shapeRecord['Y'];
            }
        }
    }

    function deformeShapeRecordUnit_2_line($threshold, $startIndex, $endIndex) {
        $threshold_2 = $threshold * $threshold;
        $shapeRecord = $this->_shapeRecords[$startIndex];
        $prevIndex = null;
        $currentDrawingPositionX = $shapeRecord['MoveX'];
        $currentDrawingPositionY = $shapeRecord['MoveY'];
        $distance_list_short = array();
        $distance_table_all = array();
        for ($i = $startIndex + 1 ;$i <= $endIndex; $i++) {
            $shapeRecord = & $this->_shapeRecords[$i];
            if ($shapeRecord['StraightFlag'] == 0) {
                $diff_x = $shapeRecord['ControlX'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['ControlY'] - $currentDrawingPositionY;
                $distance_2_control = $diff_x * $diff_x + $diff_y * $diff_y;
                $diff_x = $shapeRecord['AnchorX'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['AnchorY'] - $currentDrawingPositionY;
                $distance_2_anchor = $diff_x * $diff_x + $diff_y * $diff_y;
//                $distance_list[$i] = $distance_2_control +  $distance_2_anchor;
                $distance_table_all[$i] = $distance_2_control +  $distance_2_anchor;
                $currentDrawingPositionX = $shapeRecord['AnchorX'];
                $currentDrawingPositionY = $shapeRecord['AnchorY'];
            } else {
                $diff_x = $shapeRecord['X'] - $currentDrawingPositionX;
                $diff_y = $shapeRecord['Y'] - $currentDrawingPositionY;
            $distance_2 = $diff_x * $diff_x + $diff_y * $diff_y;
            if ($distance_2 < $threshold_2) {
               $distance_list_short[] = $i;
            }
            $distance_table_all[$i] = $distance_2;
            $currentDrawingPositionX = $shapeRecord['X'];
            $currentDrawingPositionY = $shapeRecord['Y'];
        }
    }
    sort($distance_list_short);
    $deforme_number = 0;
    foreach ($distance_list_short as $i) {
        $distance_2 = $distance_table_all[$i];
        if ($distance_2 > $threshold_2) {
            continue; // 一定距離以上の線分は処理しない
        }
        if (empty($distance_list_all[$i-1]) && empty($distance_list_all[$i+1])) {
            // 隣の線分が吸収され済みor曲線の場合は処理しない

        }
        $index_to_merge;
        if (empty($distance_list_all[$i-1])) {
            if (empty($distance_list_all[$i+1])) {
                // 隣の線分が吸収されている場合は処理しない
                continue;           
            } else {
                $index_to_merge = $i+1;
            }
        } else {
            if (empty($distance_list_all[$i+1])) {
                $index_to_merge = $i-1;
            } else {
                $index_to_merge = $i-1; // XXX 後で選択する処理を入れる
            }
        }
        // line merge 処理
        $shapeRecord = $this->_shapeRecords[$i];
        $shapeRecord_toMerge = & $this->_shapeRecords[$index_to_merge];
        if ($i > $index_to_merge) {
            if ($shapeRecord['StraightFlag']) {
                $shapeRecord_toMerge['X'] = $shapeRecord['X'];
                $shapeRecord_toMerge['Y'] = $shapeRecord['Y'];
            } else {
                $shapeRecord_toMerge['AnchorX'] = $shapeRecord['X'];
                $shapeRecord_toMerge['AnchorY'] = $shapeRecord['Y'];
            }
        }
        $distance_list_all[$index_to_merge] += $distance_list_all[$i];
//        unset($distance_list_all[$i]);
          unset($this->_shapeRecords[$i]);
          $deforme_number += 1;
        }
        return $deforme_number;
    }
    function countEdges() {
        $edges_count = 0;
	if (isset($this->_shapeRecords)) {
	    $shapeRecords = $this->_shapeRecords;
	} elseif (isset($this->_startEdge)) {
	    $shapeRecords = $this->_startEdge;
	} else {
	    $shapeRecords = array(); // nothing to do.
	}
	foreach ($shapeRecords as $shapeRecordIndex => $shapeRecord) {
	    if (isset($shapeRecord['StraightFlag'])) { // XXX
	        $edges_count++; 
	    }
	}
	return array($this->_shapeId, $edges_count);
    }
}
