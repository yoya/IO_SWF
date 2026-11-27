IO_SWF
======

English version: [README.en.md](README.en.md)

This is a library for interpreting/editing the SWF binary.
It's mainly targeted for Flash Lite 1.x/2.x.
Not limited in use, free to copy and modify by MIT license.
Please contact me if you want new features.

SWF バイナリを解釈/編集する為のライブラリです。  IO_Bit が必要です。
主に Flash Lite 1.x/2.x を対象にしています。利用に制限はかけません。
コピーも改変もご自由にどうぞ。MIT ライセンスにしました。
自分の環境だと動かないとか、機能が欲しいとかいった要望があれば連絡下さい。

↓以下のブログに解析ツールとして解説があります。参考までに
- http://labs.gree.jp/blog/2012/11/6308/

# License

MIT License

# Install

```
% composer require yoya/io_swf
```

PHP 7.0.15 以降 (7.1 系は 7.1.1 以降) が必要。

# Usage

- swfdump.php (binary structure information dump)

```
% php vendor/yoya/io_swf/sample/swfdump.php -f input.swf
Signature: FWS
Version: 26
FileLength: 886751
FrameSize: Xmin: 0 Xmax: 512 Ymin: 0 Ymax: 288
FrameRate: 60
FrameCount: 1
Tags:
(omit)
```

# 設計概要

| クラス | 役割 |
|---|---|
| `IO_SWF` | SWF 全体。ヘッダとタグ列の parse / build / dump。FWS と CWS (zlib 圧縮) に対応 (ZWS は未対応) |
| `IO_SWF_Tag` | タグ 1 個。タグコードと中身のバイト列を持ち、必要になったときだけ中身を解釈する |
| `IO_SWF_Tag_*` | タグの種類ごとの `parseContent` / `buildContent` / `dumpContent` (Shape, Place, Action, Button, Jpeg, Font, Sound など) |
| `IO_SWF_Type_*` | タグの中のレコード (RECT, MATRIX, SHAPE, FILLSTYLE, CXFORM, BUTTONRECORD など) の `parse` / `build` / `string` |
| `IO_SWF_Editor` | 画像・ムービークリップ・アクション・テキストの差し替えや、SWF バージョンのダウングレードなどの編集 |
| `IO_SWF_ABC`, `IO_SWF_ABC_Code` | DoABC (AS3 のバイトコード) の解析と、AS1 (DoAction) への変換 |
| `IO_Bit` (別パッケージ) | ビット単位の読み書き |

- 遅延解釈: parse 時、各タグは中身をバイト列のまま持つ。`parseTagContent()` で中身を解釈する。
  build 時、バイト列が残っていればそのまま書き、`content` を `null` にしたタグだけ `buildContent()` で作り直す。
  編集しないタグはバイト単位で元のまま残る。
- parse → build → parse で情報は変わらない。作り直したタグは、ビット幅などを最小に詰め直すことがある。
- タグ表 (`IO_SWF_Tag::$tagMap`): タグコードごとに名前・解釈するクラス (`klass`)・登場した SWF バージョンを持つ。
  `klass` のあるタグだけ中身を解釈する。ダウングレードは、この表のバージョンを見てタグを古い種類に置き換えるか削除する。
- SWF File Format Specification v19 のタグはおおむね解釈できる。

# 機能

| 機能 | サンプル (`sample/`) |
|---|---|
| 構造のダンプ | `swfdump.php`, `swfactiondump.php`, `swfdecompile.php`, `swflistaction.php`, `swflistmovieclip.php` |
| 画像・音声・動画の取り出し | `swfgetjpegdata.php`, `swfgetpngdata.php`, `swfgetsounddata.php`, `swfgetvideodata.php`, `swfextract.php` |
| 画像の差し替え | `swfreplacebitmapdata.php` |
| ムービークリップの取り出し・差し替え | `swfgetmovieclip.php`, `swfreplacemovieclip.php` |
| アクションの書き換え | `swfsetactionvariables.php`, `swfreplaceactionstrings.php`, `swfdeleteaction.php` |
| テキストの取り出し・差し替え | `swfgeteditstring.php`, `swfreplaceeditstring.php` |
| 図形の加工 | `swfdeformeshape.php` (短い辺をまとめて頂点を減らす), `swfsliceshaperecords.php`, `swfcountshapeedges.php` |
| ヘッダの変更 | `swfversion.php`, `swfframerate.php`, `swfsetbgcolor.php`, `swfheader.php`, `swfcompress.php` |
| 指定したキャラクタの抜き出し | `swfselectbycids.php` |
| 作り直し・比較 | `swfrebuild.php` (全タグを作り直す), `swfdiff.php` (xdiff 拡張が必要) |
| ダウングレード | `swfdowngrade.php` (下記) |

## ダウングレード (swfdowngrade)

新しい SWF を、Flash Lite 1.x 向けの SWF4 などの古いバージョンに変換する。

```
% php sample/swfdowngrade.php -f input.swf > output.swf        # SWF4 へ
% php sample/swfdowngrade.php -v 6 -f input.swf > output.swf   # SWF6 へ
```

- 新しい種類のタグは、同じ働きの古い種類に置き換える (DefineShape4 → DefineShape3 など)。置き換えられないタグは削除する (`-E` で残す)。
- AS3 (DoABC) のフレームスクリプトを AS1 (DoAction) に変換する。
  - AS3 のスタックを模擬して式を組み立て、出力先のバージョンで使える命令だけで書き出す (SWF4 なら SWF4 の命令のみ)。
  - 表示プロパティ (`x`, `scaleX`, `rotation` など) は SetProperty / GetProperty に変換する。
  - `addEventListener(MouseEvent.CLICK, ハンドラ)` は、対象のムービークリップをボタン (DefineButton2) に置き換え、
    ハンドラをボタンのアクション (押した瞬間・Enter キー・5 キー) にする。
  - 変換できない命令や書き方があると、既定 (strict) では例外にする。`-S` では警告して読み飛ばす。
- Shape の state ビットは既定で最適化する。元のまま保つには `-p`。
- Web 版: `www/swftov4.php` (アップロードした SWF を SWF4 に変換して返す)。

# Test

```
% composer install
% php tests/run.php
```

IO_Bit は `vendor/` (composer install) か include_path から読み込む。

# More detail

- https://github.com/yoya/IO_SWF/wiki

## IO_Bit の利用箇所

`RECT` の座標には符号付きのビットフィールドを使い、バイト単位の項目に戻るときは `byteAlign()` で境界を揃えます。

[IO_Bit Wiki](https://github.com/yoya/IO_Bit/wiki)
