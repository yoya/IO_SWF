# addEventListener → DefineButton2 変換

AS3 (SWF9 以降) を SWF4 にダウングレードするとき、`addEventListener(MouseEvent.CLICK, ハンドラ)` で
登録されたクリック処理を、SWF4 のボタン (DefineButton2 + BUTTONCONDACTION) に置き換える。
SWF4 (AS1) にはイベントリスナーが無いため、置き換えないとクリックしても何も起きない。
downgrade で常に動作する (オプション不要)。

## 仕組み

1. 検知: AS3→AS1 変換器 (`IO_SWF_ABC_Code_Converter::recordEventListener`) が、フレームスクリプト中の
   `this.a.b.addEventListener(MouseEvent.X, this.ハンドラ)` を `IO_SWF_ABC_Code_Context::$eventListeners`
   に記録する。対象とハンドラが静的なパスで書かれたものだけを扱う。
2. ハンドラの変換: `IO_SWF_ABC_Code::ABCCodetoButtonActions()` で、ハンドラのメソッドを
   ボタンのアクション列に変換する。
   - ボタンのアクションはボタンを置いたタイムラインの文脈で動くので、ハンドラの `this`
     (メインタイムライン) を絶対パスとして扱う (`this.x` → `/:x`、`this.a.b.gotoAndPlay(2)` → `/a/b:2`)。
   - 途中の return は末尾への Jump にする (アクション列の途中に End を置くと、そこで終端と解釈されるため)。
   - 分岐先はバイト位置で確定させ、末尾の End は付けない (BUTTONCONDACTION が終端を書く)。
3. ボタン化: `IO_SWF_Editor::eventListenersToButtons()` が、対象のムービークリップを全状態
   (Up/Over/Down/HitTest) に使う DefineButton2 を作り、対象の配置 (PlaceObject) のキャラクタ ID を
   ボタンに差し替える。ボタンは対象の定義タグの直後に置く。
4. 条件: 押した瞬間 + Enter キー (13)、5 キー (53) の 2 つの CONDACTION
   (`IO_SWF_Editor::$buttonConditions`)。携帯電話の Flash Lite でキー操作できるようにするため。

## 方式の選択

- 対象をボタンに置き換える方式にした。配置のタイミング (表示・削除のフレーム) と見た目がそのまま引き継がれる。
- 透明なボタンを対象の上に重ねる方式は、空いている深度の割り当てと、対象の削除への追従が必要になるため見送った。
- SWF4 ではボタンをインスタンス名で操作できない。対象を他のスクリプトから参照していると、置き換えで動かなくなる。

## 対応範囲

- `MouseEvent.CLICK` のみ。メインタイムラインのクラスで登録したものだけ
  (シンボルのクラスでは `this` の絶対パスが決まらないため)。
- それ以外は警告を出して登録を捨てる。
- ハンドラに変換できない命令があると、strict (既定) では例外にする。
