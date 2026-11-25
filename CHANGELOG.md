# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.1] - 2026-11-25

### Fixed
- Files of a `[multifile]` field are attached to a mail only when the field's mail-tag
  (e.g. `[file-fahrzeugausweis]`) is in that mail's "File attachments" box, as in
  CF7 core. Before, every uploaded file was attached to every mail, so Mail (2)
  (autoresponder to the customer) always got all photos, even without attachment tags.

## [1.1.0] - 2026-11-25

Fixes pixelated attachments (e.g. an unreadable Fahrzeugausweis).

### Changed
- **Default size of resized images: 1600 px on the longest side, JPEG quality 85.**
  Forms without `w:`/`h:` were (by mistake) always resized to 800×600 px, and portrait
  photos to only 600 px high (e.g. 450×600). They now get the new default.
- Images are resized by the **longest side** (portrait and landscape get the same
  resolution) and never upscaled.
- Images are always saved as **JPEG** with the configured quality (PNG/WebP/GIF/HEIC
  converted, transparency on white, file extension changed to `.jpg`). Before,
  JPEGs were saved at quality 1.0 (large files) and PNGs were never compressed.
- If a file is bigger than `file-limit:`, the quality is lowered step by step and only
  then the resolution.
- `total-limit:` now means the total size in bytes of the field (it was used as a
  number of files in the JS and as bytes in PHP). Use `max:` for the number of files.
- `file-limit:` and `total-limit:` accept `kb`/`mb` units; default `file-limit` is 2 MB.
- Resizing uses high-quality smoothing (with step-down for big reductions).
- `error_log()` calls are written only when `WP_DEBUG` is on.

### Added
- `quality:` tag option (1–100, default 85).
- Tag options are passed to the JS as `data-*` attributes on `.cf7-mf-container`
  (`data-max-width`, `data-max-height`, `data-quality`, `data-max-files`,
  `data-file-limit`, `data-total-limit`, …).
- EXIF orientation is applied (`createImageBitmap(…, { imageOrientation: 'from-image' })`
  with `<img>` fallback): portrait phone photos no longer arrive rotated.
- Server-side validation of `file-limit:` and `total-limit:`, and of required fields
  missing from the request.
- Browser-side check of PHP `max_file_uploads` for the whole form (PHP would silently
  drop the extra files).
- Messages (German defaults, editable in CF7): `cf7_mf_file_too_large`,
  `cf7_mf_total_too_large`, `cf7_mf_processing_failed`, `cf7_mf_max_uploads`.
- The submit button is disabled while images are being processed.

### Fixed
- `w:`, `h:`, `file-limit:` and `total-limit:` had no effect (the JS read them from an
  element that did not have them).
- Error messages in the browser were never shown (`class=".cf7-mf-feedback-message"`).
- Files beyond `max:` are no longer rejected all at once: the allowed ones are kept.

### Removed
- `public/js/cf7-mf-public copy.js` (unused copy).
- Non-functional attributes on the hidden file input (`width`, `height`, `file-limit`,
  `total-limit`, `min`, `max`, `size`).

## [1.0.0]

- Initial release.
