<?php

/*
 * AS3 (ABC バイトコード) → AS1 (DoAction のアクション列) 変換
 *
 * AS3 のスタックを模擬し、値を「まだ出力していない式の木」として持つ。
 * 代入・呼び出し・分岐など副作用のある命令の時点で、評価順を保って AS1 を出力する。
 *
 * - AS3 は代入を「受け手 → 値 → setproperty」の順に積むが、AS1 の SetVariable は
 *   「名前 → 値」の順に積む必要がある。事前の解析パスで各受け手が何の代入先かを調べ、
 *   受け手を出力する位置で名前を積む。(StackSwap が不要になり、値が分岐をまたいでもよい)
 * - SWF4 には PushDuplicate/StackSwap/レジスタが無いので、dup・swap・ローカル変数は
 *   副作用の無い式の再評価、または一時変数で表す。
 * - 分岐の合流点 (ラベル) と分岐の直前では、スタック上の値をすべて AS1 のスタックに出力
 *   (実体化) しておく。AS1 のスタックも分岐をまたいで値を保つので、これで合流後も一致する。
 * - 出力する命令は対象 SWF バージョンで切り替える (SWF4 なら SWF4 の命令だけを使う)。
 * - 対応していない命令・パターンは、strict のときは例外、そうでなければ警告して読み飛ばす。
 *
 * スタック上の要素:
 *   ['k'=>'obj', 'id', 'base'=>'this'|'root'|'parent'|'global', 'path'=>[...], 'name'=>(global名)]
 *       ムービークリップ等の参照。AS1 では値として積まない。
 *   ['k'=>'val', 'id', 'tree', 'type'=>'string'|'number'|null, 'pure'=>bool]
 *       まだ出力していない式。
 *   ['k'=>'mat', 'id', 'type', 'src'=>tree|null]
 *       AS1 のスタックに出力済みの値 (src は再評価できる式。副作用の後は無効)。
 */

require_once dirname(__FILE__).'/../../Exception.php';

class IO_SWF_ABC_Code_Converter {
    // Math.random() を random(N) / N で表すときの N
    const RANDOM_RESOLUTION = 10000;
    // AS3 の表示プロパティ => [AS1 の GetProperty/SetProperty の番号, AS1 での倍率, 代入できるか]
    // (AS3 の scaleX/scaleY/alpha は 1.0 が等倍、AS1 の _xscale/_yscale/_alpha は 100)
    static $displayProps = [
        'x' => [0, 1, true], 'y' => [1, 1, true],
        'scaleX' => [2, 100, true], 'scaleY' => [3, 100, true],
        'currentFrame' => [4, 1, false], 'totalFrames' => [5, 1, false],
        'alpha' => [6, 100, true], 'visible' => [7, 1, true],
        'width' => [8, 1, true], 'height' => [9, 1, true],
        'rotation' => [10, 1, true], 'framesLoaded' => [12, 1, false],
        'mouseX' => [20, 1, false], 'mouseY' => [21, 1, false],
    ];
    var $code;              // IO_SWF_ABC_Code
    var $version;           // 出力する SWF のバージョン
    var $strict;
    var $debug;
    var $insts = [];        // 復号済みの命令列
    var $posOfIdx = [];     // 命令 idx => $insts 内の位置
    var $labelSet = [];     // 分岐先になる命令 idx => true
    // 解析パスで集める情報
    var $analysis = false;
    var $consumers = [];    // obj の id => ['kind'=>'set','name'=>..] | ['kind'=>'goto'] | ...
    var $localTypes = [];   // ローカル変数の番号 => 代入された値の型の一覧
    var $localAliasable = []; // ローカル変数の番号 => localAliasMode() の結果
    // 生成パスの状態
    var $actions = [];
    var $labelPos = [];     // AS3 命令 idx => AS1 アクション位置
    var $branchFix = [];    // AS1 アクション位置 (Jump/If) => 分岐先の AS3 命令 idx
    var $stack = [];
    var $locals = [];
    var $savedStack = [];   // 分岐先の命令 idx => その時点のスタック
    var $reachable = true;
    var $tempSeq = 0;
    var $warnings = [];
    // ボタンのアクション用の変換 (イベントハンドラ)
    var $thisBase = 'this';      // 'root': this をメインタイムライン (絶対パス) として扱う
    var $returnAsJump = false;   // return を末尾への Jump にする (途中に End を置かない)

    function __construct($code, $version, $opts) {
        $this->code = $code;
        $this->version = (int) $version;
        $this->strict = ! empty($opts['strict']);
        $this->debug = ! empty($opts['debug']);
        if (! empty($opts['thisBase'])) {
            $this->thisBase = $opts['thisBase'];
        }
        $this->returnAsJump = ! empty($opts['returnAsJump']);
    }

    // ------------------------------------------------------------
    // 入口
    // ------------------------------------------------------------
    function convert() {
        $this->decode();
        // 1 パス目: 受け手 (obj) の消費先を調べる
        $this->analysis = true;
        $this->run();
        // 2 パス目: AS1 を出力する
        $this->analysis = false;
        $this->run();
        $this->labelPos['__end'] = count($this->actions); // return を Jump にした場合の飛び先
        // 分岐先を AS1 のアクション位置に直す
        $labels = [];
        $branches = [];
        foreach ($this->branchFix as $actionIdx => $targetIdx) {
            if (! isset($this->labelPos[$targetIdx])) {
                throw new IO_SWF_Exception("branch target idx:$targetIdx not resolved");
            }
            $pos = $this->labelPos[$targetIdx];
            $labels[$pos] = $pos;
            $branches[$actionIdx] = $pos;
        }
        return [$this->actions, $labels, $branches];
    }

    function unsupported($msg) {
        if ($this->strict) {
            throw new IO_SWF_Exception("ABC to Action: unsupported: $msg");
        }
        if (! $this->analysis) {
            $this->warnings []= $msg;
            fprintf(STDERR, "ABC to Action: unsupported (skipped): %s\n", $msg);
        }
    }

    // ------------------------------------------------------------
    // 命令の復号
    // ------------------------------------------------------------
    function decode() {
        $code = $this->code;
        $abc = $code->abc;
        $this->insts = [];
        foreach ($code->codeArray as $idx => $c) {
            $bit = new IO_SWF_ABC_Bit();
            $bit->input($c['bytes']);
            $op = $bit->getUI8();
            $name = $code->getInstructionName($op);
            $types = $code->getInstructionArgsType($op);
            $pools = $code->getInstructionArgsPool($op);
            $args = [];
            foreach ($types as $i => $t) {
                switch ($t) {
                  case 'u30':   $v = $bit->get_u30(); break;
                  case 's24':   $v = $bit->get_s24(); break;
                  case 'ubyte': $v = $bit->getUI8(); break;
                  default:      $v = null; // lookupswitch など (未対応)
                }
                if (isset($pools[$i]) && $pools[$i] === 'multiname') {
                    $info = $abc->getMultiname($v);
                    $v = ['index' => $v,
                          'name' => isset($info['name']) ? $abc->getString_name($info['name']) : null,
                          'kind' => $info['kind']];
                } elseif (isset($pools[$i]) && $pools[$i] === 'string') {
                    $v = $abc->getString_name($v);
                }
                $args []= $v;
            }
            $inst = ['idx' => $idx, 'op' => $op, 'name' => $name, 'args' => $args];
            if (isset($code->branches[$idx])) {
                $inst['target'] = $code->branches[$idx];
            }
            $this->posOfIdx[$idx] = count($this->insts);
            $this->insts []= $inst;
        }
        $this->labelSet = [];
        foreach ($code->labels as $idx => $dummy) {
            $this->labelSet[$idx] = true;
        }
        $this->localAliasable = [];
        $this->localTypes = [];
    }

    // ------------------------------------------------------------
    // 命令列の模擬実行 (解析パス・生成パス共通)
    // ------------------------------------------------------------
    function run() {
        $this->actions = [];
        $this->labelPos = [];
        $this->branchFix = [];
        $this->stack = [];
        $this->locals = [];
        $this->savedStack = [];
        $this->reachable = true;
        $this->tempSeq = 0;
        $n = count($this->insts);
        for ($p = 0; $p < $n; $p++) {
            $inst = $this->insts[$p];
            $idx = $inst['idx'];
            if (isset($this->labelSet[$idx])) {
                $this->arriveLabel($idx);
            }
            if (! $this->reachable) {
                continue; // 到達しない命令 (分岐先でもない)
            }
            if ($this->debug && ! $this->analysis) {
                fprintf(STDERR, "DEBUG: ABC to Action [%d] %s\n", $idx, $inst['name']);
            }
            $this->step($inst, $p);
        }
    }

    function arriveLabel($idx) {
        if ($this->reachable) {
            // 直前の命令から流れ込む: すべて実体化して合流させる
            $this->materializeAll();
        } else {
            // 直前が無条件の分岐/return: 分岐元で保存したスタックから始める
            $this->stack = isset($this->savedStack[$idx]) ? $this->savedStack[$idx] : [];
            $this->reachable = true;
        }
        $this->labelPos[$idx] = count($this->actions);
    }

    function step($inst, $p) {
        $op = $inst['op'];
        $a = $inst['args'];
        $id = $inst['idx'];
        switch ($inst['name']) {
          // ---- 何もしない
          case 'label': case 'popscope': case 'coerce_a': case 'coerce':
          case 'convert_d': case 'convert_b': case 'kill_noop':
            if ($inst['name'] === 'convert_d' && $this->stack) {
                $top = &$this->stack[count($this->stack) - 1];
                if ($top['k'] !== 'obj') $top['type'] = 'number';
                unset($top);
            }
            return;
          case 'kill':
            unset($this->locals[$a[0]]);
            return;
          case 'pushscope':
            $this->popNode();
            return;
          // ---- 参照
          case 'getlocal_0':
            $this->stack []= ['k' => 'obj', 'id' => $id, 'base' => $this->thisBase, 'path' => []];
            return;
          case 'getlocal_1': case 'getlocal_2': case 'getlocal_3': case 'getlocal':
            $n = ($inst['name'] === 'getlocal') ? $a[0] : (int) substr($inst['name'], -1);
            if (! isset($this->locals[$n])) {
                $this->unsupported("getlocal $n (未設定のローカル変数)");
                $this->stack []= $this->lit('', 'string', $id);
                return;
            }
            $node = $this->locals[$n];
            $node['id'] = $id;
            $this->stack []= $node;
            return;
          case 'setlocal_1': case 'setlocal_2': case 'setlocal_3': case 'setlocal':
            $n = ($inst['name'] === 'setlocal') ? $a[0] : (int) substr($inst['name'], -1);
            $this->setLocal($n, $p);
            return;
          case 'getlex':
            $nm = $a[0]['name'];
            if ($nm === 'root' || $nm === 'parent') {
                $this->stack []= ['k' => 'obj', 'id' => $id, 'base' => $nm, 'path' => []];
            } else {
                $this->stack []= ['k' => 'obj', 'id' => $id, 'base' => 'global', 'path' => [], 'name' => $nm];
            }
            return;
          case 'findpropstrict': case 'findproperty':
            $this->stack []= ['k' => 'obj', 'id' => $id, 'base' => 'global', 'path' => [], 'name' => $a[0]['name']];
            return;
          case 'getproperty':
            $this->getProperty($a[0]['name'], $id, $a[0]['kind']);
            return;
          // ---- リテラル
          case 'pushbyte':
            $v = $a[0];
            $v = ($v >= 128) ? $v - 256 : $v; // AVM2 の pushbyte は符号付き
            $this->stack []= $this->lit($v, 'number', $id);
            return;
          case 'pushshort':
            $this->stack []= $this->lit($a[0], 'number', $id);
            return;
          case 'pushstring':
            $this->stack []= $this->lit($a[0], 'string', $id);
            return;
          case 'pushdouble':
            $pool = $this->code->abc->_constant_pool['double'];
            $this->stack []= $this->lit(isset($pool[$a[0]]) ? $pool[$a[0]] : 0, 'number', $id);
            return;
          case 'pushtrue':  $this->stack []= $this->lit(true, 'bool', $id); return;
          case 'pushfalse': $this->stack []= $this->lit(false, 'bool', $id); return;
          case 'pushnull':  $this->stack []= $this->lit(null, 'null', $id); return;
          case 'pushundefined': $this->stack []= $this->lit(null, 'undefined', $id); return;
          // ---- スタック操作
          case 'dup':  $this->dup($id); return;
          case 'swap': $this->swap(); return;
          case 'pop':  $this->popStatement(); return;
          // ---- 演算
          case 'add': case 'subtract': case 'multiply': case 'divide': case 'modulo':
            $this->binary($inst['name'], $id);
            return;
          case 'increment': case 'increment_i': $this->unary('increment', $id); return;
          case 'decrement': case 'decrement_i': $this->unary('decrement', $id); return;
          case 'negate': $this->unary('negate', $id); return;
          case 'not':    $this->unary('not', $id); return;
          // ---- 呼び出し
          case 'callproperty':
            $this->callProperty($a[0]['name'], $a[1], $id);
            return;
          case 'callpropvoid':
            $this->callPropVoid($a[0]['name'], $a[1], $id);
            return;
          case 'constructprop':
            $this->constructProp($a[0]['name'], $a[1], $id);
            return;
          // ---- 代入
          case 'setproperty': case 'initproperty':
            $this->setProperty($a[0]['name'], $id);
            return;
          // ---- 分岐
          case 'jump':
            $this->materializeAll();
            $this->emitBranch(0x99, $inst['target']);
            $this->reachable = false;
            return;
          case 'iftrue': case 'iffalse':
            $this->condBranch1($inst);
            return;
          case 'ifeq': case 'ifne': case 'ifstricteq': case 'ifstrictne':
          case 'iflt': case 'ifle': case 'ifgt': case 'ifge':
          case 'ifnlt': case 'ifnle': case 'ifngt': case 'ifnge':
            $this->condBranch2($inst);
            return;
          case 'returnvoid': case 'returnvalue':
            if ($this->returnAsJump) {
                // 最後の命令なら何もしない。途中なら末尾に飛ぶ
                if ($p !== count($this->insts) - 1) {
                    $this->emitBranch(0x99, '__end');
                }
            } else {
                $this->emit(['Code' => 0x00]); // End
            }
            $this->stack = [];
            $this->reachable = false;
            return;
          default:
            $this->unsupported("instruction {$inst['name']}(0x".dechex($op).") at [$id]");
            return;
        }
    }

    // ------------------------------------------------------------
    // ノード
    // ------------------------------------------------------------
    function lit($value, $type, $id) {
        return ['k' => 'val', 'id' => $id, 'tree' => ['lit', $value, $type],
                'type' => ($type === 'string') ? 'string' : (($type === 'number') ? 'number' : null),
                'pure' => true];
    }

    function popNode() {
        if (count($this->stack) === 0) {
            $this->unsupported('stack underflow');
            return $this->lit('', 'string', -1);
        }
        return array_pop($this->stack);
    }

    // obj を値として使う (例: this.x → 変数 x の読み出し)
    function objToVal($node) {
        if ($node['k'] !== 'obj') {
            return $node;
        }
        if ($node['base'] === 'global' || count($node['path']) === 0) {
            $this->unsupported("object used as value: " . $this->describeObj($node));
            return $this->lit('', 'string', $node['id']);
        }
        $last = end($node['path']);
        if (is_string($last) && isset(self::$displayProps[$last])) {
            // 表示プロパティ: GetProperty(対象, 番号)
            $path = $node['path'];
            array_pop($path);
            list($index, $scale) = self::$displayProps[$last];
            return ['k' => 'val', 'id' => $node['id'],
                    'tree' => ['getprop', 'number', $this->clipTarget($node, $path), $index, $scale, $last],
                    'type' => 'number', 'pure' => true];
        }
        return ['k' => 'val', 'id' => $node['id'], 'tree' => ['var', $this->varTarget($node)],
                'type' => null, 'pure' => true];
    }

    // GetProperty/SetProperty の対象 (木)。自分自身は空文字
    function clipTarget($node, $path) {
        if ($node['base'] === 'this' && count($path) === 0) {
            return ['lit', '', 'string'];
        }
        return $this->mcPathTree($node, $path);
    }

    function describeObj($node) {
        $s = ($node['base'] === 'global') ? $node['name'] : $node['base'];
        foreach ($node['path'] as $p) $s .= is_array($p) ? '[expr]' : ".$p";
        return $s;
    }

    // パスの要素は固定の名前 (文字列) か、実行時に決まる名前 ['dyn', 木] (this[式] など)。
    // パスの文字列は、固定部分だけならリテラル、式を含めば文字列連結の木にする。
    function partsToTree($parts) {
        $merged = [];
        foreach ($parts as $p) {
            if (is_array($p)) {
                $merged []= $p[1];
            } elseif (count($merged) && is_string(end($merged))) {
                $merged[count($merged) - 1] .= $p;
            } else {
                $merged []= $p;
            }
        }
        $tree = null;
        foreach ($merged as $m) {
            $t = is_string($m) ? ['lit', $m, 'string'] : $m;
            $tree = is_null($tree) ? $t : ['add', 'string', $tree, $t];
        }
        return is_null($tree) ? ['lit', '', 'string'] : $tree;
    }

    // ムービークリップのパスの要素。this 直下の子 a.b は "a/b"、root は "/"、parent は ".."
    function mcPathParts($node, $path) {
        switch ($node['base']) {
          case 'this':   $parts = []; break;
          case 'root':   $parts = ['/']; break;
          case 'parent': $parts = ['..']; if (count($path)) $parts []= '/'; break;
          default:
            $this->unsupported("path of " . $this->describeObj($node));
            return [''];
        }
        foreach ($path as $i => $p) {
            if ($i > 0) $parts []= '/';
            $parts []= $p;
        }
        return $parts;
    }

    function mcPathTree($node, $path, $suffix = '') {
        $parts = $this->mcPathParts($node, $path);
        if ($suffix !== '') $parts []= $suffix;
        return $this->partsToTree($parts);
    }

    // 変数の参照名 (木): this.x → "x", this.a.x → "a:x", root.x → "/:x", parent.x → "..:x"
    function varTarget($node, $name = null) {
        $path = $node['path'];
        if (is_null($name)) {
            $name = array_pop($path);
        }
        if ($node['base'] === 'this' && count($path) === 0) {
            return $this->partsToTree([$name]);
        }
        $parts = $this->mcPathParts($node, $path);
        $parts []= ':';
        $parts []= $name;
        return $this->partsToTree($parts);
    }

    // 式が読む変数 (再評価してよいかの判定に使う)。名前が実行時に決まるものは '*'
    function treeReads($tree, &$reads) {
        switch ($tree[0]) {
          case 'lit': return;
          case 'var':
            $t = $tree[1];
            if (is_string($t)) {
                $reads[$t] = true;
            } elseif ($t[0] === 'lit') {
                $reads[$t[1]] = true;
            } else {
                $reads['*'] = true;
                $this->treeReads($t, $reads);
            }
            return;
          case 'getprop':
            $reads[$tree[5]] = true;
            $this->treeReads($tree[2], $reads);
            return;
          default:
            for ($i = 2; $i < count($tree); $i++) {
                if (is_array($tree[$i])) $this->treeReads($tree[$i], $reads);
            }
        }
    }

    // ------------------------------------------------------------
    // 出力
    // ------------------------------------------------------------
    function emit($action) {
        if (! $this->analysis) {
            $this->actions []= $action;
        }
    }

    function pushString($s) {
        $s = (string) $s;
        $this->emit(['Code' => 0x96, 'Length' => 1 + strlen($s) + 1,
                     'Values' => [['Type' => 0, 'String' => $s]]]);
    }

    function numberString($v) {
        if (is_int($v)) return (string) $v;
        $f = (float) $v;
        if (is_finite($f) && $f == floor($f) && abs($f) < 1e15) return (string) (int) $f;
        return rtrim(rtrim(sprintf('%.15F', $f), '0'), '.');
    }

    function pushLiteral($value, $type) {
        switch ($type) {
          case 'string': $this->pushString($value); return;
          case 'number': $this->pushString($this->numberString($value)); return;
          case 'bool':
            if ($this->version >= 5) {
                $this->emit(['Code' => 0x96, 'Length' => 1 + 1, 'Values' => [['Type' => 5, 'Boolean' => $value ? 1 : 0]]]);
            } else {
                $this->pushString($value ? '1' : '0');
            }
            return;
          case 'null': case 'undefined':
            if ($this->version >= 5) {
                $this->emit(['Code' => 0x96, 'Length' => 1, 'Values' => [['Type' => ($type === 'null') ? 2 : 3]]]);
            } else {
                $this->pushString(''); // SWF4 の未定義は空文字
            }
            return;
        }
    }

    function emitTree($tree) {
        switch ($tree[0]) {
          case 'lit':
            $this->pushLiteral($tree[1], $tree[2]);
            return;
          case 'var': // 参照名は文字列か、文字列になる木
            if (is_string($tree[1])) {
                $this->pushString($tree[1]);
            } else {
                $this->emitTree($tree[1]);
            }
            $this->emit(['Code' => 0x1C]); // GetVariable
            return;
          case 'getprop': // ['getprop', type, 対象, 番号, 倍率, AS3 の名前]
            $this->emitTree($tree[2]);
            $this->pushString((string) $tree[3]);
            $this->emit(['Code' => 0x22]); // GetProperty
            if ($tree[4] != 1) {
                $this->pushString((string) $tree[4]);
                $this->emit(['Code' => 0x0D]); // Divide
            }
            return;
          case 'randomraw': // Math.random(): 0 以上 1 未満 = random(N) / N
            $this->pushString((string) self::RANDOM_RESOLUTION);
            $this->emit(['Code' => 0x30]); // RandomNumber
            $this->pushString((string) self::RANDOM_RESOLUTION);
            $this->emit(['Code' => 0x0D]); // Divide
            return;
          case 'add':
            $this->emitTree($tree[2]);
            $this->emitTree($tree[3]);
            $this->emit(['Code' => ($tree[1] === 'string') ? 0x21 : 0x0A]); // StringAdd / Add
            return;
          case 'subtract': case 'multiply': case 'divide':
            $this->emitTree($tree[2]);
            $this->emitTree($tree[3]);
            $this->emitBinaryOp($tree[0]);
            return;
          case 'modulo':
            if ($this->version >= 5) {
                $this->emitTree($tree[2]);
                $this->emitTree($tree[3]);
                $this->emit(['Code' => 0x3F]); // Modulo
            } else {
                // SWF4 には Modulo が無い: a - b * int(a / b)
                $this->emitTree($tree[2]);
                $this->emitTree($tree[3]);
                $this->emitTree($tree[2]);
                $this->emitTree($tree[3]);
                $this->emit(['Code' => 0x0D]); // Divide
                $this->emit(['Code' => 0x18]); // ToInteger
                $this->emit(['Code' => 0x0C]); // Multiply
                $this->emit(['Code' => 0x0B]); // Subtract
            }
            return;
          case 'increment': case 'decrement': case 'negate': case 'not':
            if ($tree[0] === 'negate') {
                $this->pushString('0');
                $this->emitTree($tree[2]);
                $this->emit(['Code' => 0x0B]); // Subtract
                return;
            }
            $this->emitTree($tree[2]);
            $this->emitUnaryOp($tree[0]);
            return;
          case 'strlen':
            $this->emitTree($tree[2]);
            $this->emit(['Code' => 0x14]); // StringLength
            return;
          case 'substr':
            $this->emitTree($tree[2]);
            $this->emitTree($tree[3]);
            $this->emitTree($tree[4]);
            $this->emit(['Code' => 0x15]); // StringExtract
            return;
          case 'random':
            $this->emitTree($tree[2]);
            $this->emit(['Code' => 0x30]); // RandomNumber
            return;
        }
        throw new IO_SWF_Exception("emitTree: unknown tree " . $tree[0]);
    }

    function emitBinaryOp($name) {
        $map = ['subtract' => 0x0B, 'multiply' => 0x0C, 'divide' => 0x0D];
        $this->emit(['Code' => $map[$name]]);
    }

    function emitUnaryOp($name) {
        switch ($name) {
          case 'increment':
            if ($this->version >= 5) { $this->emit(['Code' => 0x50]); return; }
            $this->pushString('1'); $this->emit(['Code' => 0x0A]); return; // Add
          case 'decrement':
            if ($this->version >= 5) { $this->emit(['Code' => 0x51]); return; }
            $this->pushString('1'); $this->emit(['Code' => 0x0B]); return; // Subtract
          case 'not':
            $this->emit(['Code' => 0x12]); return;
          case 'negate': // 実体化済みの値の符号反転: -1 を掛ける
            $this->pushString('-1'); $this->emit(['Code' => 0x0C]); return;
        }
    }

    function emitBranch($code, $targetIdx) {
        if (! $this->analysis) {
            $this->branchFix[count($this->actions)] = $targetIdx;
        }
        if ($code === 0x99) {
            $this->emit(['Code' => 0x99, 'Length' => 2, 'BranchOffset' => 0]); // Jump
        } else {
            $this->emit(['Code' => 0x9D, 'Length' => 2, 'Offset' => 0]); // If
        }
        // 分岐先で使うスタック (すべて実体化済み)
        $this->savedStack[$targetIdx] = $this->stack;
    }

    // ------------------------------------------------------------
    // 実体化 (AS1 のスタックに積む)
    // ------------------------------------------------------------
    function materializeAll() {
        $this->materializeBelow(count($this->stack));
    }

    // スタックの下から $n 個を実体化する
    function materializeBelow($n) {
        for ($i = 0; $i < $n; $i++) {
            $this->materializeAt($i);
        }
    }

    function materializeAt($i) {
        $node = $this->stack[$i];
        if ($node['k'] === 'mat') {
            return;
        }
        if ($node['k'] === 'val') {
            $this->emitTree($node['tree']);
            $this->stack[$i] = ['k' => 'mat', 'id' => $node['id'], 'type' => $node['type'],
                                'src' => $node['pure'] ? $node['tree'] : null];
            return;
        }
        // obj: 代入先や goto 先として後で使われるものだけ、名前を積んでおける
        $consumer = isset($this->consumers[$node['id']]) ? $this->consumers[$node['id']] : null;
        if ($this->analysis) {
            $this->stack[$i] = ['k' => 'mat', 'id' => $node['id'], 'type' => null, 'src' => null, 'obj' => $node];
            return;
        }
        if ($consumer && $consumer['kind'] === 'set' && isset(self::$displayProps[$consumer['name']]) &&
            $node['base'] !== 'global') {
            // 表示プロパティへの代入: 対象と番号の 2 つを積む (SetProperty で使う)
            $this->emitTree($this->clipTarget($node, $node['path']));
            $this->pushString((string) self::$displayProps[$consumer['name']][0]);
        } elseif ($consumer && $consumer['kind'] === 'set') {
            $this->emitTree($this->setTarget($node, $consumer['name']));
        } elseif ($consumer && $consumer['kind'] === 'goto' && $node['base'] !== 'global' &&
                  ! ($node['base'] === 'this' && count($node['path']) === 0)) {
            $this->emitTree($this->mcPathTree($node, $node['path'], ':'));
        } elseif ($node['base'] !== 'global' && count($node['path']) > 0) {
            // 代入先でも goto 先でもない this.x などは、変数の値として出力する
            $val = $this->objToVal($node);
            $this->emitTree($val['tree']);
            $this->stack[$i] = ['k' => 'mat', 'id' => $node['id'], 'type' => null, 'src' => $val['tree']];
            return;
        } else {
            $this->unsupported("object kept on stack across branch: " . $this->describeObj($node));
        }
        $this->stack[$i] = ['k' => 'mat', 'id' => $node['id'], 'type' => null, 'src' => null, 'obj' => $node];
    }

    // 副作用 (代入など) の後は、実体化済みの値の再評価はできない
    function invalidateSources() {
        foreach ($this->stack as &$node) {
            if ($node['k'] === 'mat') {
                $node['src'] = null;
            }
        }
        unset($node);
    }

    // 演算などで、スタック上部の $count 個のオペランドを取り出す。
    // すべて未出力なら木として返し (true)、出力済みのものがあれば全部を実体化して false を返す。
    function takeOperands($count, &$nodes) {
        $base = count($this->stack) - $count;
        if ($base < 0) {
            $this->unsupported('stack underflow');
            $nodes = array_fill(0, $count, $this->lit('', 'string', -1));
            return true;
        }
        $nodes = array_slice($this->stack, $base);
        foreach ($nodes as $k => $node) {
            $nodes[$k] = $this->objToVal($node);
        }
        $pending = true;
        foreach ($nodes as $node) {
            if ($node['k'] === 'mat') {
                $pending = false;
            }
        }
        if (! $pending) {
            // 下から順に実体化する (出力済みの値より上にある未出力の値を出す)
            for ($k = 0; $k < $count; $k++) {
                $this->stack[$base + $k] = $nodes[$k];
                $this->materializeAt($base + $k);
                $nodes[$k] = $this->stack[$base + $k];
            }
        }
        array_splice($this->stack, $base);
        return $pending;
    }

    // ------------------------------------------------------------
    // 個々の命令
    // ------------------------------------------------------------
    function getProperty($name, $id, $kind = 7) {
        if (is_null($name) && ($kind === 0x1B || $kind === 0x1C)) {
            // MultinameL: 名前を実行時に決める (this[式] など)。名前、受け手の順に取り出す
            $nameNode = $this->objToVal($this->popNode());
            $node = $this->popNode();
            if ($node['k'] === 'obj' && $node['base'] !== 'global' && $nameNode['k'] === 'val') {
                $node['path'][] = ['dyn', $nameNode['tree']];
                $node['id'] = $id;
                $this->stack []= $node;
                return;
            }
            $this->unsupported("property with a runtime name on " . (($node['k'] === 'obj') ? $this->describeObj($node) : 'a value'));
            $this->stack []= $this->lit('', 'string', $id);
            return;
        }
        $node = $this->popNode();
        if ($name === 'length' && $node['k'] === 'obj' && $node['base'] !== 'global' && count($node['path']) > 0) {
            // x.length: 変数 x の値 (文字列) の長さ
            $this->stack []= $this->objToVal($node);
            $this->unaryTree('strlen', $id, 'number');
            return;
        }
        if ($node['k'] === 'obj' && $node['base'] !== 'global') {
            $node['path'][] = $name; // 子のムービークリップか変数かは使われ方で決まる
            $node['id'] = $id;
            $this->stack []= $node;
            return;
        }
        if ($node['k'] === 'obj') { // MouseEvent.CLICK など
            $node['path'][] = $name;
            $node['id'] = $id;
            $this->stack []= $node;
            return;
        }
        // 値のプロパティ
        $this->stack []= $node;
        if ($name === 'length') {
            $this->unaryTree('strlen', $id, 'number');
            return;
        }
        $this->popNode();
        $this->unsupported("property '$name' of a value");
        $this->stack []= $this->lit('', 'string', $id);
    }

    function unaryTree($name, $id, $type) {
        if ($this->takeOperands(1, $nodes)) {
            $x = $nodes[0];
            $this->stack []= ['k' => 'val', 'id' => $id, 'tree' => [$name, $type, $x['tree']],
                              'type' => $type, 'pure' => $x['pure']];
            return;
        }
        if ($name === 'strlen') {
            $this->emit(['Code' => 0x14]); // StringLength
        } else {
            $this->emitUnaryOp($name);
        }
        $this->stack []= ['k' => 'mat', 'id' => $id, 'type' => $type, 'src' => null];
    }

    function unary($name, $id) {
        $type = ($name === 'not') ? null : 'number';
        $this->unaryTree($name, $id, $type);
    }

    function binary($name, $id) {
        $pending = $this->takeOperands(2, $nodes);
        list($x, $y) = $nodes;
        if ($name === 'add') {
            if ($x['type'] === 'string' || $y['type'] === 'string') {
                $type = 'string';
            } elseif ($x['type'] === 'number' && $y['type'] === 'number') {
                $type = 'number';
            } else {
                $type = null;
            }
        } else {
            $type = 'number';
        }
        if ($pending) {
            $tree = ($name === 'add') ? ['add', $type, $x['tree'], $y['tree']] : [$name, $type, $x['tree'], $y['tree']];
            $this->stack []= ['k' => 'val', 'id' => $id, 'tree' => $tree, 'type' => $type,
                              'pure' => $x['pure'] && $y['pure']];
            return;
        }
        // オペランドが出力済み
        switch ($name) {
          case 'add': $this->emit(['Code' => ($type === 'string') ? 0x21 : 0x0A]); break;
          case 'modulo':
            if ($this->version >= 5) {
                $this->emit(['Code' => 0x3F]);
            } else {
                $this->unsupported("modulo of already-evaluated values for SWF4 at [$id]");
            }
            break;
          default: $this->emitBinaryOp($name);
        }
        $this->stack []= ['k' => 'mat', 'id' => $id, 'type' => $type, 'src' => null];
    }

    function dup($id) {
        if (count($this->stack) === 0) {
            $this->unsupported('dup on empty stack');
            return;
        }
        $top = $this->stack[count($this->stack) - 1];
        if ($top['k'] === 'obj' || ($top['k'] === 'val' && $top['pure'])) {
            $top['id'] = "$id";
            $this->stack []= $top; // 同じ式をもう一度使う (出力時に再評価される)
            return;
        }
        if ($top['k'] === 'val') { // 副作用のある式: 一度だけ評価する
            $this->materializeAll();
            $top = $this->stack[count($this->stack) - 1];
        }
        if (! is_null($top['src'])) {
            $this->emitTree($top['src']); // 再評価して複製 (SWF4 でも可)
        } elseif ($this->version >= 5) {
            $this->emit(['Code' => 0x4C]); // PushDuplicate
        } else {
            $this->unsupported("dup of an evaluated value for SWF4 at [$id]");
        }
        $this->stack []= ['k' => 'mat', 'id' => "$id", 'type' => $top['type'], 'src' => $top['src']];
    }

    function swap() {
        $n = count($this->stack);
        if ($n < 2) {
            $this->unsupported('swap on short stack');
            return;
        }
        $x = $this->stack[$n - 2];
        $y = $this->stack[$n - 1];
        if ($x['k'] !== 'mat' && $y['k'] !== 'mat') {
            $this->stack[$n - 2] = $y;
            $this->stack[$n - 1] = $x;
            return;
        }
        $this->materializeAll();
        $x = $this->stack[$n - 2];
        $y = $this->stack[$n - 1];
        if (! is_null($x['src']) && ! is_null($y['src'])) {
            $this->emit(['Code' => 0x17]); // Pop
            $this->emit(['Code' => 0x17]);
            $this->emitTree($y['src']);
            $this->emitTree($x['src']);
        } elseif ($this->version >= 5) {
            $this->emit(['Code' => 0x4D]); // StackSwap
        } else {
            $this->unsupported('swap of evaluated values for SWF4');
        }
        $this->stack[$n - 2] = $y;
        $this->stack[$n - 1] = $x;
    }

    function popStatement() {
        $node = $this->popNode();
        if ($node['k'] === 'mat') {
            $this->emit(['Code' => 0x17]); // Pop
        } elseif ($node['k'] === 'val' && ! $node['pure']) {
            $this->materializeAll();
            $this->emitTree($node['tree']);
            $this->emit(['Code' => 0x17]);
        }
        // 未出力の副作用の無い式・obj は出力不要
    }

    // ローカル変数: SWF4 にはレジスタが無いので、式の別名か一時変数で表す。
    // 別名にできるのは、代入から使用までが一直線の場合だけ (localAliasable)。
    function setLocal($n, $p) {
        $node = $this->popNode();
        if ($node['k'] === 'obj') {
            // this.a は子のムービークリップか変数かが使われ方で決まるので、できるだけ参照のまま別名にする
            $isRef = ($node['base'] === 'global' || count($node['path']) === 0); // 変数として読めない参照
            $reads = [];
            if (! $isRef) {
                $val = $this->objToVal($node);
                $this->treeReads($val['tree'], $reads);
            }
            if ($this->canAliasLocal($n, $p, $reads)) {
                $this->locals[$n] = $node;
                return;
            }
            if ($isRef) {
                // 参照 (ムービークリップ等) は AS1 の変数に保存できない
                $this->unsupported("setlocal $n: object reference used across branches: " . $this->describeObj($node));
                $this->locals[$n] = $node;
                return;
            }
            $node = $val; // 変数の値として一時変数に保存する
        }
        if ($this->analysis) {
            $this->localTypes[$n] []= $node['type'];
        }
        $type = $this->localType($n, $node['type']);
        $tmp = '__abc_local' . $n;
        if ($node['k'] === 'val') {
            $reads = [];
            $this->treeReads($node['tree'], $reads);
            if ($node['pure'] && $this->canAliasLocal($n, $p, $reads)) {
                $this->locals[$n] = $node; // 式の別名 (使う時点で再評価しても同じ値)
                return;
            }
            // 一時変数に保存する: 名前を先に積めるのは値が未出力の場合だけ
            $this->materializeAll();
            $this->pushString($tmp);
            $this->emitTree($node['tree']);
            $this->emit(['Code' => 0x1D]); // SetVariable
            $this->invalidateSources();
            $this->locals[$n] = ['k' => 'val', 'id' => $node['id'], 'tree' => ['var', $tmp],
                                 'type' => $type, 'pure' => true];
            return;
        }
        // 出力済みの値は、その下に名前を積めないので SWF4 ではそのまま保存できない。
        // 再評価できる式なら、積んだ値を捨てて、別名にするか一時変数に保存し直す
        if (isset($node['src'])) {
            $reads = [];
            $this->treeReads($node['src'], $reads);
            $this->emit(['Code' => 0x17]); // Pop
            if ($this->canAliasLocal($n, $p, $reads)) {
                $this->locals[$n] = ['k' => 'val', 'id' => $node['id'], 'tree' => $node['src'],
                                     'type' => $node['type'], 'pure' => true];
                return;
            }
            $this->pushString($tmp);
            $this->emitTree($node['src']);
            $this->emit(['Code' => 0x1D]); // SetVariable
            $this->invalidateSources();
            $this->locals[$n] = ['k' => 'val', 'id' => $node['id'], 'tree' => ['var', $tmp],
                                 'type' => $type, 'pure' => true];
            return;
        }
        $this->unsupported("setlocal $n of an evaluated value");
        $this->emit(['Code' => 0x17]);
        $this->locals[$n] = $this->lit('', 'string', $node['id']);
    }

    // 命令がローカル変数 $n を読むか ('get')、書くか ('set')、捨てるか ('kill')
    function localAccess($inst, $n) {
        $nm = $inst['name'];
        if (($nm === 'getlocal' && $inst['args'][0] === $n) || $nm === "getlocal_$n") return 'get';
        if (($nm === 'setlocal' && $inst['args'][0] === $n) || $nm === "setlocal_$n") return 'set';
        if ($nm === 'kill' && $inst['args'][0] === $n) return 'kill';
        return null;
    }

    // ローカル変数 $n を「式の別名」(使う時点で再評価) で表せる形か。
    //   'straight': どの代入も、代入から最後の使用までが一直線 (途中に分岐の合流点が無い)
    //   'entry':    代入が 1 つだけで、メソッドの先頭から一直線の位置にある (必ず 1 回だけ実行される)
    //   null:       どちらでもない。使う時点でどの代入の値かが決まらない (分岐ごとの代入、ループ) ので、
    //               すべての代入を同じ一時変数に保存する
    function localAliasMode($n) {
        if (array_key_exists($n, $this->localAliasable)) {
            return $this->localAliasable[$n];
        }
        $straight = true;
        $defs = [];
        $def = null;     // 直前の代入の位置
        $lastUse = null; // その代入の値を最後に使う位置
        $count = count($this->insts);
        for ($q = 0; $q <= $count; $q++) {
            $kind = ($q < $count) ? $this->localAccess($this->insts[$q], $n) : 'end';
            if ($kind === 'get' && ! is_null($def)) {
                $lastUse = $q;
            } elseif ($kind === 'set' || $kind === 'kill' || $kind === 'end') {
                if (! is_null($def) && ! is_null($lastUse) && $this->hasLabel($def + 1, $lastUse)) {
                    $straight = false;
                }
                $def = ($kind === 'set') ? $q : null;
                $lastUse = null;
                if ($kind === 'set') {
                    $defs []= $q;
                }
            }
        }
        if ($straight) {
            $mode = 'straight';
        } elseif (count($defs) === 1 && ! $this->hasLabel(0, $defs[0])) {
            $mode = 'entry';
        } else {
            $mode = null;
        }
        return $this->localAliasable[$n] = $mode;
    }

    // 位置 $from 〜 $to の命令に、分岐の合流点 (ラベル) があるか
    function hasLabel($from, $to) {
        for ($r = $from; $r <= $to; $r++) {
            if (isset($this->labelSet[$this->insts[$r]['idx']])) {
                return true;
            }
        }
        return false;
    }

    // 位置 $p の setlocal の値 (読む変数は $reads) を、式の別名にしてよいか
    function canAliasLocal($n, $p, $reads) {
        switch ($this->localAliasMode($n)) {
          case 'straight': // 最後の使用までに、読む変数への代入が無い
            return ! $this->assignedBeforeLastUse($n, $p, $reads);
          case 'entry':    // 分岐やループをまたいで使うので、以降のどこにも代入が無い
            return ! $this->assignedBeforeLastUse($n, $p, $reads, true);
        }
        return false;
    }

    // 一時変数に保存したローカル変数の型。別名にできない変数は、どの代入の値かが決まらないので、
    // すべての代入の型が同じときだけその型にする (型は解析パスで集める)
    function localType($n, $type) {
        if ($this->analysis || ! is_null($this->localAliasMode($n)) || ! isset($this->localTypes[$n])) {
            return $type;
        }
        foreach ($this->localTypes[$n] as $t) {
            if ($t !== $type) {
                return null;
            }
        }
        return $type;
    }

    // setlocal から、そのローカル変数を最後に使う (kill されるまでの) 間に、
    // 式が読む変数への代入があるか (あれば別名ではなく一時変数に保存する)。
    // $whole なら、使用の位置によらず、以降のどこかに代入があるか (表示プロパティは代入以外でも
    // 変わるので、読んでいれば代入ありとする)
    function assignedBeforeLastUse($n, $p, $reads, $whole = false) {
        if (count($reads) === 0) {
            return false;
        }
        if ($whole && count(array_intersect_key($reads, self::$displayProps))) {
            return true;
        }
        $names = [];
        foreach ($reads as $t => $dummy) {
            $parts = explode(':', $t);
            $names[end($parts)] = true;
        }
        $assigned = false;
        for ($q = $p + 1; $q < count($this->insts); $q++) {
            $inst = $this->insts[$q];
            $nm = $inst['name'];
            if (! $whole && in_array($this->localAccess($inst, $n), ['kill', 'set'])) {
                return false;
            }
            // 名前が実行時に決まる代入 (this[式] = 値) は、どの変数にも代入しうる
            if (($nm === 'setproperty' || $nm === 'initproperty') &&
                (isset($names['*']) || is_null($inst['args'][0]['name']) || isset($names[$inst['args'][0]['name']]))) {
                if ($whole) {
                    return true;
                }
                $assigned = true;
            }
            if ($assigned && (($nm === 'getlocal' && $inst['args'][0] === $n) || $nm === "getlocal_$n")) {
                return true;
            }
        }
        return false;
    }

    function setProperty($name, $id) {
        $n = count($this->stack);
        if ($n < 2) {
            $this->unsupported("setproperty $name: stack underflow");
            $this->stack = [];
            return;
        }
        $recv = $this->stack[$n - 2];
        if ($this->analysis && $recv['k'] === 'obj') {
            $this->consumers[$recv['id']] = ['kind' => 'set', 'name' => $name];
        }
        if ($this->analysis && $recv['k'] === 'mat' && isset($recv['obj'])) {
            $this->consumers[$recv['id']] = ['kind' => 'set', 'name' => $name];
        }
        // 受け手より下を先に評価する (AS3 ではこれらは先に評価済み)
        $this->materializeBelow($n - 2);
        $value = $this->objToVal($this->stack[$n - 1]);
        $recvObj = ($recv['k'] === 'obj') ? $recv : (isset($recv['obj']) ? $recv['obj'] : null);
        if (isset(self::$displayProps[$name]) && $recvObj && $recvObj['base'] !== 'global') {
            // 表示プロパティ: SetProperty(対象, 番号, 値)
            list($index, $scale, $settable) = self::$displayProps[$name];
            if (! $settable) {
                $this->unsupported("setproperty of read-only property $name");
            }
            if ($recv['k'] === 'obj') { // 受け手が実体化済みなら対象と番号は積まれている
                $this->emitTree($this->clipTarget($recv, $recv['path']));
                $this->pushString((string) $index);
            }
            if ($value['k'] === 'val') {
                $this->emitTree($value['tree']);
            }
            if ($scale != 1) {
                $this->pushString((string) $scale);
                $this->emit(['Code' => 0x0C]); // Multiply
            }
            $this->emit(['Code' => 0x23]); // SetProperty
            array_splice($this->stack, $n - 2);
            $this->invalidateSources();
            return;
        }
        if ($recv['k'] === 'obj') {
            if ($recv['base'] === 'global') {
                $this->unsupported("setproperty $name on " . $this->describeObj($recv));
            }
            $this->emitTree($this->setTarget($recv, $name));
        } elseif (! isset($recv['obj'])) {
            $this->unsupported("setproperty $name on a value");
        }
        // 受け手が実体化済みなら、名前は積まれている
        if ($value['k'] === 'val') {
            $this->emitTree($value['tree']);
        }
        $this->emit(['Code' => 0x1D]); // SetVariable
        array_splice($this->stack, $n - 2);
        $this->invalidateSources();
    }

    // 代入先の参照名 (木)
    function setTarget($recv, $name) {
        if ($recv['base'] === 'this' && count($recv['path']) === 0) {
            return ['lit', $name, 'string'];
        }
        $parts = $this->mcPathParts($recv, $recv['path']);
        $parts []= ':' . $name;
        return $this->partsToTree($parts);
    }

    // 受け手と引数を取り出す。引数は値として返す
    function popCall($argc, &$recv) {
        $args = [];
        for ($i = 0; $i < $argc; $i++) {
            array_unshift($args, $this->popNode());
        }
        $recv = $this->popNode();
        return $args;
    }

    function callProperty($name, $argc, $id) {
        $n = count($this->stack);
        $recv = $this->stack[$n - 1 - $argc];
        if ($name === 'MovieClip' && $argc === 1) { // キャスト
            $args = $this->popCall($argc, $recv);
            $this->stack []= $args[0];
            return;
        }
        if ($name === 'random' && $argc === 0) {
            $this->popCall($argc, $recv);
            $this->stack []= ['k' => 'val', 'id' => $id, 'tree' => ['randomraw'], 'type' => 'number', 'pure' => false];
            return;
        }
        if ($name === 'floor' && $argc === 1) {
            // Math.floor(Math.random() * N) → random(N)
            $arg = $this->stack[$n - 1];
            if ($arg['k'] === 'val' && $arg['tree'][0] === 'multiply' && $arg['tree'][2][0] === 'randomraw') {
                $this->popCall($argc, $recv);
                $this->stack []= ['k' => 'val', 'id' => $id, 'tree' => ['random', 'number', $arg['tree'][3]],
                                  'type' => 'number', 'pure' => false];
                return;
            }
            $this->popCall($argc, $recv);
            $this->unsupported("Math.floor except floor(random()*N)");
            $this->stack []= $this->lit('', 'string', $id);
            return;
        }
        if ($name === 'substr' && $argc === 2 && $recv['k'] !== 'obj' || ($name === 'substr' && $argc === 2 && $recv['k'] === 'obj' && $recv['base'] !== 'global')) {
            // 受け手と引数 2 つをオペランドとして扱う
            $pending = $this->takeOperands(3, $nodes);
            if (! $pending) {
                $this->unsupported("substr of already-evaluated values at [$id]");
                $this->emit(['Code' => 0x15]); // StringExtract
                $this->stack []= ['k' => 'mat', 'id' => $id, 'type' => 'string', 'src' => null];
                return;
            }
            list($s, $start, $len) = $nodes;
            // AS3 の substr は 0 始まりで負の開始位置は末尾から。AS1 の StringExtract は 1 始まり
            if ($start['tree'][0] === 'lit' && $start['tree'][2] === 'number' && $start['tree'][1] < 0) {
                if (! $s['pure']) {
                    $this->unsupported("substr with negative start on an expression with side effects at [$id]");
                }
                // 長さ + 開始位置 + 1
                $index = ['add', 'number', ['strlen', 'number', $s['tree']],
                          ['lit', $start['tree'][1] + 1, 'number']];
            } elseif ($start['tree'][0] === 'lit' && $start['tree'][2] === 'number') {
                $index = ['lit', $start['tree'][1] + 1, 'number'];
            } else {
                $index = ['add', 'number', $start['tree'], ['lit', 1, 'number']];
            }
            $this->stack []= ['k' => 'val', 'id' => $id,
                              'tree' => ['substr', 'string', $s['tree'], $index, $len['tree']],
                              'type' => 'string', 'pure' => $s['pure'] && $start['pure'] && $len['pure']];
            return;
        }
        $this->popCall($argc, $recv);
        $this->unsupported("callproperty $name/$argc");
        $this->stack []= $this->lit('', 'string', $id);
    }

    function constructProp($name, $argc, $id) {
        $args = $this->popCall($argc, $recv);
        if ($name === 'URLRequest' && $argc === 1) {
            $this->stack []= ['k' => 'urlreq', 'id' => $id, 'arg' => $args[0]];
            return;
        }
        $this->unsupported("constructprop $name/$argc");
        $this->stack []= $this->lit('', 'string', $id);
    }

    // 受け手が this (自分自身のタイムライン) か
    function isSelf($recv, $name) {
        if ($recv['k'] !== 'obj') return false;
        if ($recv['base'] === 'this' && count($recv['path']) === 0) return true;
        // findpropstrict play; callpropvoid play など
        return ($recv['base'] === 'global' && $recv['name'] === $name && count($recv['path']) === 0);
    }

    function callPropVoid($name, $argc, $id) {
        $n = count($this->stack);
        if ($n < $argc + 1) {
            $this->unsupported("callpropvoid $name: stack underflow");
            $this->stack = [];
            return;
        }
        $recv = $this->stack[$n - 1 - $argc];
        switch ($name) {
          case 'gotoAndPlay':
          case 'gotoAndStop':
            if ($argc !== 1) { // gotoAndPlay(frame, scene) など
                $this->popCall($argc, $recv);
                $this->unsupported("$name with $argc arguments");
                return;
            }
            if ($this->analysis && ($recv['k'] === 'obj' || isset($recv['obj']))) {
                $this->consumers[$recv['id']] = ['kind' => 'goto'];
            }
            $this->materializeBelow($n - 1 - $argc);
            $frame = $this->objToVal($this->stack[$n - 1]);
            array_splice($this->stack, $n - 1 - $argc);
            $playFlag = ($name === 'gotoAndPlay') ? 1 : 0;
            if ($recv['k'] === 'obj' && $this->isSelf($recv, $name) &&
                $frame['k'] === 'val' && $frame['tree'][0] === 'lit' &&
                in_array($frame['tree'][2], ['number', 'string'])) {
                // this.gotoAndPlay(定数): GotoFrame / GotoLabel + Play/Stop
                if ($frame['tree'][2] === 'string') {
                    $label = (string) $frame['tree'][1];
                    $this->emit(['Code' => 0x8C, 'Length' => strlen($label) + 1, 'Label' => $label]); // GotoLabel
                } else {
                    // GotoFrame のフレーム番号は 0 始まり (AS3 の gotoAndPlay は 1 始まり)
                    $this->emit(['Code' => 0x81, 'Length' => 2, 'Frame' => max(0, (int) $frame['tree'][1] - 1)]); // GotoFrame
                }
                $this->emit(['Code' => $playFlag ? 0x06 : 0x07]); // Play / Stop
                $this->invalidateSources();
                return;
            }
            // GotoFrame2: スタックの "パス:フレーム" を使う
            if ($recv['k'] === 'obj') {
                // "パス:" (自分自身なら無し)。パスに実行時の名前があれば文字列連結の木になる
                $prefix = $this->isSelf($recv, $name) ? [] : array_merge($this->mcPathParts($recv, $recv['path']), [':']);
                if ($frame['k'] === 'val' && $frame['tree'][0] === 'lit' && $frame['tree'][2] !== 'bool') {
                    $f = ($frame['tree'][2] === 'number') ? $this->numberString($frame['tree'][1]) : (string) $frame['tree'][1];
                    $this->emitTree($this->partsToTree(array_merge($prefix, [$f])));
                } else {
                    if ($prefix) $this->emitTree($this->partsToTree($prefix));
                    if ($frame['k'] === 'val') $this->emitTree($frame['tree']);
                    if ($prefix) $this->emit(['Code' => 0x21]); // StringAdd
                }
            } else { // 受け手は実体化済み ("パス:" が積まれている)
                if ($frame['k'] === 'val') $this->emitTree($frame['tree']);
                $this->emit(['Code' => 0x21]); // StringAdd
            }
            $this->emit(['Code' => 0x9F, 'Length' => 1, 'SceneBiasFlag' => 0, 'PlayFlag' => $playFlag]); // GotoFrame2
            $this->invalidateSources();
            return;
          case 'play':
          case 'stop':
            $this->materializeBelow($n - 1 - $argc);
            array_splice($this->stack, $n - 1 - $argc);
            $code = ($name === 'play') ? 0x06 : 0x07;
            if ($this->isSelf($recv, $name)) {
                $this->emit(['Code' => $code]);
            } elseif ($recv['k'] === 'obj' && $recv['base'] !== 'global') {
                $target = $this->mcPathTree($recv, $recv['path']);
                if ($target[0] === 'lit') {
                    $t = (string) $target[1];
                    $this->emit(['Code' => 0x8B, 'Length' => strlen($t) + 1, 'TargetName' => $t]); // SetTarget
                } else { // 実行時に決まるパス
                    $this->emitTree($target);
                    $this->emit(['Code' => 0x20]); // SetTarget2
                }
                $this->emit(['Code' => $code]);
                $this->emit(['Code' => 0x8B, 'Length' => 1, 'TargetName' => '']);
            } else {
                $this->unsupported("$name on an evaluated value");
            }
            $this->invalidateSources();
            return;
          case 'navigateToURL':
            $this->materializeBelow($n - 1 - $argc);
            $req = $this->stack[$n - 1];
            array_splice($this->stack, $n - 1 - $argc);
            if ($argc < 1 || $req['k'] !== 'urlreq') {
                $this->unsupported('navigateToURL without new URLRequest(...)');
                return;
            }
            $url = $this->objToVal($req['arg']);
            if ($url['k'] === 'val') {
                $this->emitTree($url['tree']);
            } else {
                $this->unsupported('navigateToURL with an evaluated URL');
            }
            $this->pushString(''); // target (既存の変換と同じく空文字)
            $this->emit(['Code' => 0x9A, 'Length' => 1, 'LoadVariablesFlag' => 0,
                         'LoadTargetFlag' => 0, 'SendVarsMethod' => 0]); // GetURL2
            $this->invalidateSources();
            return;
          case 'trace':
            $this->materializeBelow($n - 1 - $argc);
            $msg = ($argc >= 1) ? $this->objToVal($this->stack[$n - 1]) : $this->lit('', 'string', $id);
            array_splice($this->stack, $n - 1 - $argc);
            if ($msg['k'] === 'val') $this->emitTree($msg['tree']);
            $this->emit(['Code' => 0x26]); // Trace
            return;
          case 'addEventListener':
            // AS1 にはイベントリスナーが無いので、ここでは登録を記録するだけ。
            // Editor が、対象のムービークリップをボタンにしてハンドラをボタンのアクションにする。
            if (! $this->analysis && $argc >= 2) {
                $this->recordEventListener($recv, $this->stack[$n - $argc], $this->stack[$n - $argc + 1]);
            }
            array_splice($this->stack, $n - 1 - $argc);
            return;
        }
        $this->popCall($argc, $recv);
        $this->unsupported("callpropvoid $name/$argc");
    }

    // target.addEventListener(MouseEvent.X, this.handler) を記録する
    function recordEventListener($recv, $event, $handler) {
        $ctx = $this->code->codeContext;
        $static = function($node) {
            if ($node['k'] !== 'obj') return null;
            foreach ($node['path'] as $p) if (! is_string($p)) return null;
            return $node['path'];
        };
        $target = $static($recv);
        $eventPath = $static($event);
        $handlerPath = $static($handler);
        if (is_null($target) || $recv['base'] !== 'this' ||
            is_null($eventPath) || $event['base'] !== 'global' || count($eventPath) !== 1 ||
            is_null($handlerPath) || $handler['base'] !== 'this' || count($handlerPath) !== 1) {
            fprintf(STDERR, "ABC to Action: addEventListener of unsupported form is ignored\n");
            return;
        }
        if (is_null($ctx)) {
            return;
        }
        $ctx->eventListeners []= ['spriteId' => $ctx->spriteId, 'class' => $ctx->name,
                                  'target' => $target, 'event' => $event['name'] . '.' . $eventPath[0],
                                  'handler' => $handlerPath[0]];
    }

    // ------------------------------------------------------------
    // 条件分岐
    // ------------------------------------------------------------
    function condBranch1($inst) {
        $cond = $this->objToVal($this->popNode());
        $this->stack []= $cond;
        $this->materializeAll(); // 条件の値も含めて出力 (下の値を先に)
        array_pop($this->stack);
        if ($inst['name'] === 'iffalse') {
            $this->emit(['Code' => 0x12]); // Not
        }
        $this->emitBranch(0x9D, $inst['target']);
    }

    function condBranch2($inst) {
        $name = $inst['name'];
        $n = count($this->stack);
        if ($n < 2) {
            $this->unsupported("$name: stack underflow");
            $this->stack = [];
            return;
        }
        // オペランドより下を先に出力する
        $this->materializeBelow($n - 2);
        $x = $this->objToVal($this->stack[$n - 2]); // a (先に積まれた方)
        $y = $this->objToVal($this->stack[$n - 1]); // b
        array_splice($this->stack, $n - 2);
        $neg = false;
        switch ($name) {
          case 'ifeq': case 'ifne': case 'ifstricteq': case 'ifstrictne':
            $this->emitOperands($x, $y, false);
            $strict = (strpos($name, 'strict') !== false);
            if ($this->version >= 6 && $strict) {
                $this->emit(['Code' => 0x66]); // StrictEquals
            } elseif ($this->version >= 5) {
                $this->emit(['Code' => 0x49]); // Equals2
            } elseif ($x['type'] === 'string' || $y['type'] === 'string') {
                $this->emit(['Code' => 0x13]); // StringEquals
            } else {
                $this->emit(['Code' => 0x0E]); // Equals
            }
            $neg = ($name === 'ifne' || $name === 'ifstrictne');
            break;
          // a < b → Less(a, b)。a > b は b < a としてオペランドを入れ替える
          case 'iflt':  $this->emitLess($x, $y, false); $neg = false; break; // a < b
          case 'ifnlt': $this->emitLess($x, $y, false); $neg = true;  break; // !(a < b)
          case 'ifge':  $this->emitLess($x, $y, false); $neg = true;  break; // a >= b ⇔ !(a < b)
          case 'ifnge': $this->emitLess($x, $y, false); $neg = false; break; // !(a >= b) ⇔ a < b
          case 'ifgt':  $this->emitLess($x, $y, true);  $neg = false; break; // a > b ⇔ b < a
          case 'ifngt': $this->emitLess($x, $y, true);  $neg = true;  break; // !(a > b)
          case 'ifle':  $this->emitLess($x, $y, true);  $neg = true;  break; // a <= b ⇔ !(b < a)
          case 'ifnle': $this->emitLess($x, $y, true);  $neg = false; break; // !(a <= b) ⇔ b < a
        }
        if ($neg) {
            $this->emit(['Code' => 0x12]); // Not
        }
        $this->emitBranch(0x9D, $inst['target']);
    }

    // 2 つのオペランドを出力する。$swap なら b, a の順
    function emitOperands($x, $y, $swap) {
        $xp = ($x['k'] === 'val');
        $yp = ($y['k'] === 'val');
        if ($xp && $yp) {
            if ($swap) { $this->emitTree($y['tree']); $this->emitTree($x['tree']); }
            else { $this->emitTree($x['tree']); $this->emitTree($y['tree']); }
            return true;
        }
        // a が出力済み: b を出力すると a, b の順になる
        if (! $xp && $yp) {
            $this->emitTree($y['tree']);
        }
        if (! $swap) {
            return true;
        }
        if ($this->version >= 5) {
            $this->emit(['Code' => 0x4D]); // StackSwap
            return true;
        }
        return false; // SWF4 では入れ替えられない
    }

    function emitLess($x, $y, $swap) {
        if ($this->emitOperands($x, $y, $swap)) {
            $this->emit(['Code' => 0x0F]); // Less
            return;
        }
        // SWF4 で a, b の順にしか積めない場合: b < a ⇔ (a - b) * -1 < 0 (数値として比較)
        $this->emit(['Code' => 0x0B]); // Subtract
        $this->pushString('-1');
        $this->emit(['Code' => 0x0C]); // Multiply
        $this->pushString('0');
        $this->emit(['Code' => 0x0F]); // Less
    }
}
