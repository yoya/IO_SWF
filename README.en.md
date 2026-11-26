IO_SWF
======

This is a library for interpreting/editing the SWF binary. It requires IO_Bit.
It's mainly targeted for Flash Lite 1.x/2.x.
Not limited in use, free to copy and modify by MIT license.
Please contact me if it doesn't work in your environment or you want new features.

The following blog post (in Japanese) explains how to use it as an analysis tool.
- http://labs.gree.jp/blog/2012/11/6308/

# License

MIT License

# Install

```
% composer require yoya/io_swf
```

Requires PHP 7.0.15 or later (7.1.1 or later on 7.1).

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

# Design overview

| Class | Role |
|---|---|
| `IO_SWF` | The whole SWF. parse / build / dump of the header and the tag list. Supports FWS and CWS (zlib compressed); ZWS is not supported |
| `IO_SWF_Tag` | A single tag. Holds the tag code and the content bytes, and interprets the content only when needed |
| `IO_SWF_Tag_*` | `parseContent` / `buildContent` / `dumpContent` for each kind of tag (Shape, Place, Action, Button, Jpeg, Font, Sound, etc.) |
| `IO_SWF_Type_*` | `parse` / `build` / `string` for records inside tags (RECT, MATRIX, SHAPE, FILLSTYLE, CXFORM, BUTTONRECORD, etc.) |
| `IO_SWF_Editor` | Editing: replacing images, movie clips, actions and text, downgrading the SWF version, etc. |
| `IO_SWF_ABC`, `IO_SWF_ABC_Code` | Parsing DoABC (AS3 bytecode) and converting it to AS1 (DoAction) |
| `IO_Bit` (separate package) | Bit-level reading and writing |

- Lazy parsing: on parse, each tag keeps its content as raw bytes. `parseTagContent()` interprets the content.
  On build, tags that still have their raw bytes are written as is, and only tags whose `content` is set to `null` are rebuilt with `buildContent()`.
  Tags that are not edited stay byte-for-byte identical.
- parse → build → parse does not change the information. Rebuilt tags may have bit widths and the like packed to the minimum.
- Tag map (`IO_SWF_Tag::$tagMap`): for each tag code, holds the name, the class that interprets it (`klass`) and the SWF version it appeared in.
  Only tags with a `klass` have their content interpreted. Downgrading uses the versions in this map to replace tags with older kinds or remove them.
- Most tags in the SWF File Format Specification v19 can be interpreted.

# Features

| Feature | Samples (`sample/`) |
|---|---|
| Structure dump | `swfdump.php`, `swfactiondump.php`, `swfdecompile.php`, `swflistaction.php`, `swflistmovieclip.php` |
| Extracting images, sounds and videos | `swfgetjpegdata.php`, `swfgetpngdata.php`, `swfgetsounddata.php`, `swfgetvideodata.php`, `swfextract.php` |
| Replacing images | `swfreplacebitmapdata.php` |
| Extracting / replacing movie clips | `swfgetmovieclip.php`, `swfreplacemovieclip.php` |
| Rewriting actions | `swfsetactionvariables.php`, `swfreplaceactionstrings.php`, `swfdeleteaction.php` |
| Extracting / replacing text | `swfgeteditstring.php`, `swfreplaceeditstring.php` |
| Shape processing | `swfdeformeshape.php` (merges short edges to reduce vertices), `swfsliceshaperecords.php`, `swfcountshapeedges.php` |
| Changing the header | `swfversion.php`, `swfframerate.php`, `swfsetbgcolor.php`, `swfheader.php`, `swfcompress.php` |
| Extracting specified characters | `swfselectbycids.php` |
| Rebuilding / comparing | `swfrebuild.php` (rebuilds all tags), `swfdiff.php` (needs the xdiff extension) |
| Downgrading | `swfdowngrade.php` (see below) |

## Downgrading (swfdowngrade)

Converts a newer SWF to an older version, such as SWF4 for Flash Lite 1.x.

```
% php sample/swfdowngrade.php -f input.swf > output.swf        # to SWF4
% php sample/swfdowngrade.php -v 6 -f input.swf > output.swf   # to SWF6
```

- Newer kinds of tags are replaced with older kinds that do the same job (DefineShape4 → DefineShape3, etc.). Tags that cannot be replaced are removed (`-E` keeps them).
- AS3 (DoABC) frame scripts are converted to AS1 (DoAction).
  - The AS3 stack is simulated to build expressions, which are written out using only the actions available in the target version (only SWF4 actions for SWF4).
  - Display properties (`x`, `scaleX`, `rotation`, etc.) are converted to SetProperty / GetProperty.
  - `addEventListener(MouseEvent.CLICK, handler)` replaces the target movie clip with a button (DefineButton2),
    and the handler becomes the button's action (on press, the Enter key and the 5 key).
  - Unsupported instructions or patterns raise an exception by default (strict). With `-S`, they are skipped with a warning.
- Shape state bits are optimized by default. Use `-p` to keep the original ones.
- Web version: `www/swftov4.php` (converts an uploaded SWF to SWF4 and returns it).

# Test

```
% composer install
% php tests/run.php
```

IO_Bit is loaded from `vendor/` (composer install) or the include_path.

# More detail

- https://github.com/yoya/IO_SWF/wiki
