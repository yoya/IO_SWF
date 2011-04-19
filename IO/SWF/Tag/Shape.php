<?php

require_once 'IO/Bit.php';
require_once dirname(__FILE__).'/Base.php';
require_once dirname(__FILE__).'/../Type/MATRIX.php';
require_once dirname(__FILE__).'/../Type/RECT.php';
require_once dirname(__FILE__).'/../Type/RGB.php';
require_once dirname(__FILE__).'/../Type/RGBA.php';
require_once dirname(__FILE__).'/../Type/FILLSTYLEARRAY.php';
require_once dirname(__FILE__).'/../Type/LINESTYLEARRAY.php';
require_once dirname(__FILE__).'/../Type/SHAPE.php';

class IO_SWF_Tag_Shape extends IO_SWF_Tag_Base {
    var $_shapeId = null;
    var $_shapeBounds;
    var $_fillStyles = array(), $_lineStyles = array();
    var $_shapeRecords = array();

   function parseContent($tagCode, $content, $opts = array()) {
        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_shapeId = $reader->getUI16LE();
    	// 描画枠
        $this->_shapeBounds = IO_SWF_TYPE_RECT::parse($reader);

    	// 描画スタイル
        $opts = array('tagCode' => $tagCode);
        $this->_fillStyles = IO_SWF_TYPE_FILLSTYLEARRAY::parse($reader, $opts);
    	$this->_lineStyles = IO_SWF_TYPE_LINESTYLEARRAY::parse($reader, $opts);
        $this->_shapeRecords = IO_SWF_Type_SHAPE::parse($reader, $opts);
    }

    function dumpContent($tagCode, $opts = array()) {
        if (is_null($this->_shapeId) === false) {
            echo "    ShapeId: {$this->_shapeId}\n";
        }
        echo "    ShapeBounds:";
        $Xmin = $this->_shapeBounds['Xmin'] / 20;
        $Xmax = $this->_shapeBounds['Xmax'] / 20;
        $Ymin = $this->_shapeBounds['Xmin'] / 20;
        $Ymax = $this->_shapeBounds['Ymax'] / 20;
        echo "  ($Xmin, $Ymin) - ($Xmax, $Ymax)\n";

        echo "    FillStyles:\n";
        echo IO_SWF_Type_FILLSTYLEARRAY::string($this->_fillStyles);
        echo "    LineStyles:\n";
        echo IO_SWF_Type_FILLSTYLEARRAY::string($this->_lineStyles);

        echo "    ShapeRecords:\n";
        echo IO_SWF_Type_SHAPE::string($this->_shapeRecords);
    }

    function buildContent($tagCode, $opts = array()) {
        $writer = new IO_Bit();
        if (isset($opts['hasShapeId']) && $opts['hasShapeId']) {
            $writer->putUI16LE($this->_shapeId);
        }
        IO_SWF_Type_RECT::build($writer, $this->_shapeBounds);
        // 描画スタイル
        $opts = array('tagCode' => $tagCode);
        IO_SWF_Type_FILLSTYLEARRAY::build($writer, $this->_fillStyles, $opts);
        IO_SWF_Type_LINESTYLEARRAY::build($writer, $this->_lineStyles, $opts);
        $opts['fillStyleCount'] = count($this->_fillStyles);
        $opts['lineStyleCount'] = count($this->_fillStyles);
        IO_SWF_Type_SHAPE::build($writer, $this->_shapeRecords, $opts);

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
}
