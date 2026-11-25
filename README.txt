=== CF7 Multifile ===
Contributors: digid
Tags: contact form 7, upload, multiple files, image resize, drag and drop
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds a [multifile] form-tag to Contact Form 7: drag & drop, preview and in-browser image resizing before upload.

== Description ==

The `[multifile]` / `[multifile*]` tag lets visitors add several files at once (click or drag & drop),
shows a preview and lets them remove files before sending.

Images are processed **in the browser** before upload:

* resized so the longest side is at most `w:` px (never upscaled), keeping the aspect ratio;
* rotated according to the EXIF orientation (portrait phone photos arrive upright);
* re-encoded as JPEG with the configured `quality:` (PNG, WebP, GIF… become `.jpg`;
  transparent areas become white; EXIF metadata such as GPS location is removed);
* if a file is still bigger than `file-limit:`, the quality is lowered step by step
  (e.g. 0.85 → 0.75 → 0.65) and only then the resolution.

The files are sent as attachments of the Contact Form 7 mail. List the fields in the
"File attachments" box of the mail tab, e.g. `[your-image-file][file-fahrzeugausweis]`
(see https://contactform7.com/file-uploading-and-attachment/).

== Tag options ==

`[multifile* name filetypes:image/* w:1600 h:1600 quality:85 min:1 max:10 file-limit:2mb total-limit:8mb]`

* `w:` / `h:` – maximum longest side in px. If only one is given it applies to both.
  With both, the image must fit in that box in either orientation
  (longest side ≤ the larger value, shortest side ≤ the smaller one). Default: **1600**.
* `quality:` – JPEG quality 1–100. Default: **85**.
* `min:` / `max:` – minimum / maximum number of files in this field. Default: 0 / **10**.
  A required field (`multifile*`) needs at least one file.
* `file-limit:` – maximum size of each file **after** resizing. Bytes, or with unit:
  `file-limit:500kb`, `file-limit:2mb`. Default: **2 MB**. The browser lowers quality /
  resolution to stay under it; the server rejects larger files.
* `total-limit:` – maximum size of all files of this field together (bytes, `kb` or `mb`).
  Default: none. Checked in the browser and on the server.
* `filetypes:` – accepted types, as in CF7 (e.g. `image/*` or `image/jpeg|image/png`).
* `capture:user` / `capture:environment` – passed to the file input (mobile camera).

Notes:

* `total-limit:` used to be read (wrongly) as a number of files by the JS. It now means bytes;
  use `max:` for the number of files.
* CF7's own `limit:` option is **not** used by this tag (it is ignored).
* PHP accepts at most `max_file_uploads` files per request (default **20**, whole form).
  The browser now refuses files beyond that limit with a message instead of letting PHP
  drop them silently. Raise it on the server if a form allows more files in total.

== Messages ==

Editable per form in the CF7 "Messages" tab:

* `cf7_mb_min_file` – too few files (`__min_file_limit__`)
* `cf7_mb_max_file` – too many files (`__max_file_limit__`)
* `cf7_mf_file_too_large` – a file is bigger than `file-limit:` (`__file_name__`, `__file_limit__`)
* `cf7_mf_total_too_large` – all files together are bigger than `total-limit:` (`__total_limit__`)
* `cf7_mf_processing_failed` – the browser cannot read the image, e.g. HEIC in Chrome (`__file_name__`)
* `cf7_mf_max_uploads` – more files than PHP `max_file_uploads` in the form (`__max_uploads__`)

== Example (Auto Fischer) ==

Ankauf form (up to 23 files; mail stays under 10 MB, see CHANGELOG 1.1.0):

`[multifile* your-image-file filetypes:image/* w:1600 quality:80 file-limit:280kb min:2 max:10]`
`[multifile* file-auto-innen filetypes:image/* w:1600 quality:80 file-limit:280kb min:2 max:10]`
`[multifile* file-fahrzeugausweis filetypes:image/* w:2400 quality:85 file-limit:500kb min:1 max:3]`

Werkstatt form (one document photo, must be readable):

`[multifile file-fahrzeugausweis filetypes:image/jpeg|image/png w:2400 quality:85 max:1]`

== Mail size budget ==

Attachments are base64-encoded in the mail (+~37 %). Worst case ≈
(files × file-limit) × 1.37 + ~15 KB of text. Example for the Ankauf form:
(20 × 280 KB + 3 × 500 KB) × 1.37 ≈ 9.7 MB.

== Installation ==

1. Upload the `cf7-mf` folder to `/wp-content/plugins/`.
2. Activate the plugin (Contact Form 7 must be active).
3. Add a `[multifile]` tag to a form and the field to the mail's "File attachments".

== Changelog ==

See CHANGELOG.md.
