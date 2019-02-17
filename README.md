IO_SWF
======

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

# Usage

- sfdump.php (binary structure dump)

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
