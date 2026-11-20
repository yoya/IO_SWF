<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
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
    // DefineMorphShape, DefineMorphShape2
    var $_startBounds, $_endBounds;
    var $_offset;
    var $_morphFillStyles, $_morphLineStyles;
    var $_startEdge, $_endEdge;
    // DefineMorphShape2 only
    var $_startEdgeBounds, $_endEdgeBounds;
    // DefineShape4
    var $_edgeBounds;
    var $_reserved;
    var $_usesFillWindingRule;
    var $_usesNonScalingStrokes;
    var $_usesScalingStrokes;

   function parseContent($tagCode, $content, $opts = array()) {
       // DefineMorphShape, DefineMorphShape2
        $isMorph = ($tagCode == 46) || ($tagCode == 84);

        $reader = new IO_Bit();
    	$reader->input($content);
        $this->_shapeId = $reader->getUI16LE();

        $opts = $opts + [ 'tagCode' => $tagCode, 'isMorph' => $isMorph,
                          'shapeId' => $this->_shapeId ];
        if ($isMorph === false) {
        	// 描画スタイル
            $this->_shapeBounds = IO_SWF_TYPE_RECT::parse($reader);
            if ($tagCode == 83) { // DefineShape4
                $this->_edgeBounds = IO_SWF_TYPE_RECT::parse($reader);
                $this->_reserved = $reader->getUIBits(5);
                $this->_usesFillWindingRule = $reader->getUIBit();
                $this->_usesNonScalingStrokes = $reader->getUIBit();
                $this->_usesScalingStrokes = $reader->getUIBit();
            }
            $this->_fillStyles = IO_SWF_TYPE_FILLSTYLEARRAY::parse($reader, $opts);
        	$this->_lineStyles = IO_SWF_TYPE_LINESTYLEARRAY::parse($reader, $opts);
        	// 描画枠
            $this->_shapeRecords = IO_SWF_Type_SHAPE::parse($reader, $opts);
        } else {
            $this->_startBounds = IO_SWF_TYPE_RECT::parse($reader);
            $this->_endBounds = IO_SWF_TYPE_RECT::parse($reader);
            if ($tagCode == 84) { // DefineMorphShape2
                $this->_startEdgeBounds = IO_SWF_TYPE_RECT::parse($reader);
                $this->_endEdgeBounds = IO_SWF_TYPE_RECT::parse($reader);
                $this->_reserved = $reader->getUIBits(6);
                $this->_usesNonScalingStrokes = $reader->getUIBit();
                $this->_usesScalingStrokes = $reader->getUIBit();
            }
            $reader->byteAlign(); // Offset(UI32) is byte-aligned
            list($offset_offset, $dummy) = $reader->getOffset();
            $this->_offset = $reader->getUI32LE();
        	// 描画スタイル
            $this->_morphFillStyles = IO_SWF_TYPE_FILLSTYLEARRAY::parse($reader, $opts);
        	$this->_morphLineStyles = IO_SWF_TYPE_LINESTYLEARRAY::parse($reader, $opts);
        	// 描画枠
            $this->_startEdge = IO_SWF_Type_SHAPE::parse($reader, $opts);
            $reader->byteAlign(); // EndEdges is byte-aligned (Offset points to it)
            list($end_edge_offset, $dummy) = $reader->getOffset();
            if ($offset_offset + $this->_offset + 4 != $end_edge_offset) {
                trigger_error("offset_offset($offset_offset) + this->_offset({$this->_offset}) + 4 != end_edge_offset($end_edge_offset)", E_USER_WARNING);
                $reader->setOffset($offset_offset + $this->_offset + 4, 0);
            }
            $this->_endEdge   = IO_SWF_Type_SHAPE::parse($reader, $opts);
        }
    }

    function dumpContent($tagCode, $opts = array()) {
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        if (is_null($this->_shapeId) === false) {
            echo "    ShapeId: {$this->_shapeId}\n";
        }
        $opts = $opts + [ 'tagCode' => $tagCode, 'isMorph' => $isMorph,
                          'shapeId' => $this->_shapeId ];
        if ($isMorph === false) {
            echo "    ShapeBounds: ". IO_SWF_Type_RECT::string($this->_shapeBounds)."\n";
            if ($tagCode == 83) { // DefineShape4
                echo "    EdgeBounds: ".IO_SWF_TYPE_RECT::string($this->_edgeBounds)."\n";
                echo "    UsesFillWindingRule:".$this->_usesFillWindingRule." UsesNonScalingStrokes:".$this->_usesNonScalingStrokes." UsesScalingStrokes:".$this->_usesScalingStrokes."\n";
            }
            echo "    FillStyles:\n";
            echo IO_SWF_Type_FILLSTYLEARRAY::string($this->_fillStyles, $opts);
            echo "    LineStyles:\n";
            echo IO_SWF_Type_LINESTYLEARRAY::string($this->_lineStyles, $opts);

            echo "    ShapeRecords:\n";
            echo IO_SWF_Type_SHAPE::string($this->_shapeRecords, $opts);
        } else {
            echo "    StartBounds: ". IO_SWF_Type_RECT::string($this->_startBounds)."\n";
            echo "    EndBounds: ". IO_SWF_Type_RECT::string($this->_endBounds)."\n";
            if ($tagCode == 84) { // DefineMorphShape2
                echo "    StartEdgeBounds: ". IO_SWF_Type_RECT::string($this->_startEdgeBounds)."\n";
                echo "    EndEdgeBounds: ". IO_SWF_Type_RECT::string($this->_endEdgeBounds)."\n";
                echo "    UsesNonScalingStrokes:{$this->_usesNonScalingStrokes} UsesScalingStrokes:{$this->_usesScalingStrokes}\n";
            }
            echo "    Offset:{$this->_offset}\n";
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
        // DefineMorphShape(46) or DefineMorphShape2(84)
        $isMorph = ($tagCode == 46) || ($tagCode == 84);
        $writer = new IO_Bit();
        if (empty($opts['noShapeId'])) {
            $writer->putUI16LE($this->_shapeId);
        }
        $opts = $opts + [ 'tagCode' => $tagCode,
                          'shapeId' => $this->_shapeId ];
        if ($isMorph === false) {
            IO_SWF_Type_RECT::build($writer, $this->_shapeBounds);
            if ($tagCode == 83) { // DefineShape4
                IO_SWF_TYPE_RECT::build($writer, $this->_edgeBounds);
                $writer->putUIBits($this->_reserved, 5);
                $writer->putUIBit($this->_usesFillWindingRule);
                $writer->putUIBit($this->_usesNonScalingStrokes);
                $writer->putUIBit($this->_usesScalingStrokes);
            }
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
            if ($tagCode == 84) { // DefineMorphShape2
                IO_SWF_Type_RECT::build($writer, $this->_startEdgeBounds);
                IO_SWF_Type_RECT::build($writer, $this->_endEdgeBounds);
                $writer->putUIBits($this->_reserved, 6);
                $writer->putUIBit($this->_usesNonScalingStrokes);
                $writer->putUIBit($this->_usesScalingStrokes);
            }

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

    // 閾値より短い直線を、隣の直線にマージして頂点を 1 つ減らす。
    // エッジ座標は絶対値なので、短い直線 i を消すのは頂点を 1 つ消すことになる。
    //   前の直線にマージ: 前の直線の終点を i の終点に移す (i の始点が消える)
    //   次の直線にマージ: i を消すだけ (i の終点が消え、次の直線は i の始点から始まる)
    // 両隣とも直線なら、消える頂点とマージ後の線分との距離 (形のずれ) が小さい方を選ぶ。
    // 曲線の隣にはマージしない。マージした数を返す (0 になるまで呼び出し側が繰り返す)。
    function deformeShapeRecordUnit_2_line($threshold, $startIndex, $endIndex) {
        $threshold_2 = $threshold * $threshold;
        $moveRecord = $this->_shapeRecords[$startIndex];
        // 範囲内に残っているエッジ (前のパスでマージ済みのものは飛ばす) と始点・終点
        $edges = array();
        $startPos = array();
        $endPos = array();
        $x = $moveRecord['MoveX'];
        $y = $moveRecord['MoveY'];
        for ($i = $startIndex + 1; $i <= $endIndex; $i++) {
            if (! isset($this->_shapeRecords[$i])) {
                continue;
            }
            $record = $this->_shapeRecords[$i];
            $startPos[$i] = array($x, $y);
            if ($record['StraightFlag']) {
                $x = $record['X'];
                $y = $record['Y'];
            } else {
                $x = $record['AnchorX'];
                $y = $record['AnchorY'];
            }
            $endPos[$i] = array($x, $y);
            $edges []= $i;
        }
        // 短い直線を短い順に処理する
        $shortList = array();
        foreach ($edges as $i) {
            if ($this->_shapeRecords[$i]['StraightFlag']) {
                $d2 = self::distance2($startPos[$i], $endPos[$i]);
                if ($d2 < $threshold_2) {
                    $shortList[$i] = $d2;
                }
            }
        }
        asort($shortList);
        $positionOf = array_flip($edges); // record index => $edges 内の位置
        $removed = array();
        $deforme_number = 0;
        foreach (array_keys($shortList) as $i) {
            if (isset($removed[$i])) {
                continue;
            }
            // 先にマージした結果で長くなっていれば対象外
            if (self::distance2($startPos[$i], $endPos[$i]) >= $threshold_2) {
                continue;
            }
            $prev = self::neighborEdge($edges, $positionOf[$i], -1, $removed);
            $next = self::neighborEdge($edges, $positionOf[$i], +1, $removed);
            if (! is_null($prev) && ! $this->_shapeRecords[$prev]['StraightFlag']) {
                $prev = null; // 曲線にはマージしない
            }
            if (! is_null($next) && ! $this->_shapeRecords[$next]['StraightFlag']) {
                $next = null;
            }
            if (is_null($prev) && is_null($next)) {
                continue;
            }
            // 消える頂点とマージ後の線分との距離 (形のずれ)
            $errorPrev = is_null($prev) ? INF :
                self::pointSegmentDistance2($startPos[$i], $startPos[$prev], $endPos[$i]);
            $errorNext = is_null($next) ? INF :
                self::pointSegmentDistance2($endPos[$i], $startPos[$i], $endPos[$next]);
            if ($errorPrev <= $errorNext) { // 同じなら従来どおり前にマージ
                $this->_shapeRecords[$prev]['X'] = $endPos[$i][0];
                $this->_shapeRecords[$prev]['Y'] = $endPos[$i][1];
                $endPos[$prev] = $endPos[$i];
            } else {
                $startPos[$next] = $startPos[$i];
            }
            unset($this->_shapeRecords[$i]);
            $removed[$i] = true;
            $deforme_number++;
        }
        return $deforme_number;
    }

    // $edges の $pos から $dir 方向に、まだ残っている隣のエッジの record index を返す
    static function neighborEdge($edges, $pos, $dir, $removed) {
        for ($p = $pos + $dir; ($p >= 0) && ($p < count($edges)); $p += $dir) {
            if (! isset($removed[$edges[$p]])) {
                return $edges[$p];
            }
        }
        return null;
    }

    static function distance2($p, $q) {
        $dx = $q[0] - $p[0];
        $dy = $q[1] - $p[1];
        return $dx * $dx + $dy * $dy;
    }

    // 点 $p と線分 $a-$b の距離の 2 乗
    static function pointSegmentDistance2($p, $a, $b) {
        $abx = $b[0] - $a[0];
        $aby = $b[1] - $a[1];
        $len2 = $abx * $abx + $aby * $aby;
        if ($len2 == 0) {
            return self::distance2($p, $a);
        }
        $t = (($p[0] - $a[0]) * $abx + ($p[1] - $a[1]) * $aby) / $len2;
        $t = max(0, min(1, $t));
        $closest = array($a[0] + $t * $abx, $a[1] + $t * $aby);
        return self::distance2($p, $closest);
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

    function countRecords() {
        if (isset($this->_shapeRecords)) {
            $shapeRecords = $this->_shapeRecords;
        } elseif (isset($this->_startEdge)) {
            $shapeRecords = $this->_startEdge;
        } else {
            $shapeRecords = array(); // nothing to do.
        }
        $straightCount = 0;
        $curvedCount = 0;
        $fillStyle0 = 0;
        $fillStyle1 = 0;
        $lineStyle = 0;
        foreach ($shapeRecords as $shapeRecordIndex => $shapeRecord) {
            if (isset($shapeRecord['StraightFlag'])) {
                if ($shapeRecord['StraightFlag']) {
                    $straightCount++;
                } else {
                    $curvedCount++;
                }
            } else { // ChangeRecord || EndRecord
                if (($straightCount > 0) ||
                    ($curvedCount > 0)) {
                    $counts[] = Array(
                        'FillStyle0' => $fillStyle0,
                        'FillStyle1' => $fillStyle1,
                        'LineStyle' => $lineStyle,
                        'Straight' => $straightCount,
                        'Curved' => $curvedCount,
                        );
                    $straightCount = 0;
                    $curvedCount = 0;
                }
                if (isset($shapeRecord['EndOfShape']) === false) {
                    $fillStyle0 = $shapeRecord['FillStyle0'];
                    $fillStyle1 = $shapeRecord['FillStyle1'];
                    $lineStyle = $shapeRecord['LineStyle'];
                }
            }
        }
        return array($this->_shapeId, $counts);
    }

    function sliceRecords($start, $end) {
        if (isset($this->_shapeRecords)) {
            $shapeRecords =& $this->_shapeRecords;
        } elseif (isset($this->_startEdge)) {
            $shapeRecords =& $this->_startEdge;
        } else {
            $shapeRecords = array(); // nothing to do.
        }
        $fillStyle0 = 0;
        $fillStyle1 = 0;
        $lineStyle = 0;
        $changeStyleIndex = 0;
        $edges = false;
        foreach ($shapeRecords as $shapeRecordIndex => &$shapeRecord) {
            if (isset($shapeRecord['StraightFlag'])) {
                if (($changeStyleIndex < $start) ||
                    ($end < $changeStyleIndex)) {
                    unset($shapeRecords[$shapeRecordIndex]);
                }
                $edges = true;
            } else { // ChangeRecord || EndRecord
                if (isset($shapeRecord['EndOfShape']) === false) {
                    if ($edges) {
                        $changeStyleIndex++;
                        $edges = false;
                    }

                    $fillStyle0 = $shapeRecord['FillStyle0'];
                    $fillStyle1 = $shapeRecord['FillStyle1'];
                    $lineStyle = $shapeRecord['LineStyle'];
                }
            }
        }
        return true;
    }
}
