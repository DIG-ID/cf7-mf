/**
 * CF7 Multifile – drag & drop, preview and in-browser image resizing.
 *
 * All settings come from data-* attributes on .cf7-mf-container
 * (written by Cf7_Mf_Public::cf7_mf_file_form_tag_handler):
 *   data-max-width / data-max-height  longest / shortest side box (px)
 *   data-quality                      JPEG quality 1-100
 *   data-max-files                    max number of files in this field
 *   data-file-limit                   max bytes per file after resizing (0 = none)
 *   data-total-limit                  max bytes of all files in this field (0 = none)
 *   data-accept                       accepted types (same as the input accept attr)
 *   data-max-uploads                  PHP max_file_uploads (whole form, 0 = unknown)
 *   data-msg-*                        localized messages (CF7 "Messages" tab)
 */
(function ($) {
    "use strict";

    // Only used if an attribute is missing (e.g. cached HTML from an older version).
    const FALLBACK = {
        maxSide: 1600,
        quality: 85,
        maxFiles: 10,
        fileLimit: 2 * 1024 * 1024,
    };

    const MSG_FALLBACK = {
        maxFiles: "Sie können nicht mehr als __max_file_limit__ Dateien pro Anfrage hochladen.",
        fileLarge: "Die Datei «__file_name__» ist zu gross.",
        totalLarge: "Die Dateien sind zusammen zu gross. Bitte entfernen Sie einige Bilder.",
        type: "Sie dürfen Dateien dieses Typs nicht hochladen.",
        process: "Das Bild «__file_name__» konnte nicht verarbeitet werden. Bitte laden Sie es als JPG oder PNG hoch.",
        maxUploads: "Pro Anfrage können insgesamt höchstens __max_uploads__ Dateien gesendet werden.",
    };

    // Quality steps (subtracted from the configured quality) and scale steps
    // used when a file is still bigger than data-file-limit.
    const QUALITY_STEPS = [0, 0.1, 0.2];
    const MIN_QUALITY = 0.5;
    const SCALE_STEPS = [1, 0.85, 0.7, 0.6, 0.5];

    const EXT_TYPES = {
        jpg: "image/jpeg", jpeg: "image/jpeg", png: "image/png", gif: "image/gif",
        webp: "image/webp", heic: "image/heic", heif: "image/heif", avif: "image/avif",
        bmp: "image/bmp", tif: "image/tiff", tiff: "image/tiff",
    };

    const toInt = (value, fallback) => {
        const n = parseInt(value, 10);
        return Number.isFinite(n) && n >= 0 ? n : fallback;
    };

    const fill = (template, map) =>
        Object.keys(map).reduce((s, key) => s.split(`__${key}__`).join(map[key]), template || "");

    const fileType = (file) => {
        if (file.type) {
            return file.type.toLowerCase();
        }
        const ext = (file.name.split(".").pop() || "").toLowerCase();
        return EXT_TYPES[ext] || "";
    };

    const makeAcceptMatcher = (accept) => {
        const tokens = String(accept || "")
            .split(",")
            .map((t) => t.trim().toLowerCase())
            .filter(Boolean);

        return (file) => {
            if (!tokens.length) {
                return true;
            }
            const type = fileType(file);
            const name = file.name.toLowerCase();
            return tokens.some((t) => {
                if (t.charAt(0) === ".") {
                    return name.endsWith(t);
                }
                if (t.endsWith("/*")) {
                    return type.startsWith(t.slice(0, -1));
                }
                return type === t;
            });
        };
    };

    const jpgName = (name) => {
        const base = name.replace(/\.[^./\\]+$/, "");
        return (base || "bild") + ".jpg";
    };

    // ---- Image pipeline --------------------------------------------------

    /**
     * Decodes a file with the EXIF orientation applied.
     * Engines that do not know imageOrientation:'from-image' throw a TypeError,
     * then <img> is used (all current browsers apply EXIF orientation there).
     */
    const decodeImage = async (file) => {
        if (typeof window.createImageBitmap === "function") {
            try {
                const bitmap = await window.createImageBitmap(file, { imageOrientation: "from-image" });
                return { source: bitmap, width: bitmap.width, height: bitmap.height, release: () => bitmap.close && bitmap.close() };
            } catch (e) {
                // fall through to <img>
            }
        }

        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => resolve({
                source: img,
                width: img.naturalWidth,
                height: img.naturalHeight,
                release: () => URL.revokeObjectURL(url),
            });
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error("decode"));
            };
            img.src = url;
        });
    };

    const newCanvas = (w, h) => {
        const canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        return canvas;
    };

    const freeCanvas = (canvas) => {
        // Releases the backing store right away (helps iOS Safari memory limits).
        canvas.width = 0;
        canvas.height = 0;
    };

    /**
     * Fits (w, h) in the configured box using the longest side:
     * longest side <= max(maxWidth, maxHeight), shortest side <= min(...).
     * Never upscales.
     */
    const fitSize = (w, h, cfg) => {
        const maxLong = Math.max(cfg.maxWidth, cfg.maxHeight);
        const maxShort = Math.min(cfg.maxWidth, cfg.maxHeight);
        const scale = Math.min(1, maxLong / Math.max(w, h), maxShort / Math.min(w, h));
        return { width: Math.max(1, Math.round(w * scale)), height: Math.max(1, Math.round(h * scale)) };
    };

    /**
     * Draws the source at (tw, th) on a white background. Large reductions are done
     * in halving steps, which keeps fine detail (text on documents) sharp in every
     * browser, not only in those with a good 'high' smoothing filter.
     */
    const renderScaled = (src, sw, sh, tw, th) => {
        let current = src;
        let cw = sw;
        let ch = sh;
        const temps = [];

        while (cw / 2 >= tw && ch / 2 >= th) {
            const nw = Math.round(cw / 2);
            const nh = Math.round(ch / 2);
            const step = newCanvas(nw, nh);
            const sctx = step.getContext("2d");
            sctx.imageSmoothingEnabled = true;
            sctx.imageSmoothingQuality = "high";
            sctx.drawImage(current, 0, 0, nw, nh);
            temps.push(step);
            current = step;
            cw = nw;
            ch = nh;
        }

        const canvas = newCanvas(tw, th);
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#fff"; // JPEG has no alpha: transparent areas become white.
        ctx.fillRect(0, 0, tw, th);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = "high";
        ctx.drawImage(current, 0, 0, tw, th);

        temps.forEach(freeCanvas);
        return canvas;
    };

    const canvasToJpeg = (canvas, quality) =>
        new Promise((resolve, reject) => {
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error("encode"))), "image/jpeg", quality);
        });

    /**
     * Resizes and re-encodes an image as JPEG.
     * If the result is bigger than cfg.fileLimit: lower the quality first
     * (q, q-0.10, q-0.20), then the resolution.
     */
    const processImage = async (file, cfg) => {
        const decoded = await decodeImage(file);
        try {
            const base = fitSize(decoded.width, decoded.height, cfg);
            const baseQuality = cfg.quality / 100;
            const qualities = QUALITY_STEPS
                .map((step) => Math.round((baseQuality - step) * 100) / 100)
                .filter((q, i, all) => (q >= MIN_QUALITY || i === 0) && all.indexOf(q) === i);

            let smallest = null;

            for (const scale of SCALE_STEPS) {
                const tw = Math.max(1, Math.round(base.width * scale));
                const th = Math.max(1, Math.round(base.height * scale));
                const canvas = renderScaled(decoded.source, decoded.width, decoded.height, tw, th);
                try {
                    for (const q of qualities) {
                        const blob = await canvasToJpeg(canvas, q);
                        if (!cfg.fileLimit || blob.size <= cfg.fileLimit) {
                            return blob;
                        }
                        smallest = blob;
                    }
                } finally {
                    freeCanvas(canvas);
                }
            }

            const error = new Error("too-large");
            error.blob = smallest;
            throw error;
        } finally {
            decoded.release();
        }
    };

    // ---- Field -----------------------------------------------------------

    const initField = function () {
        const container = $(this);
        const el = this;
        const dragDropZone = container.find(".cf7-mf-drag-drop-zone");
        const previewContainer = container.find(".cf7-mf-preview");
        const hiddenInput = container.find("input[type='file']");
        const feedbackMessage = container.find(".cf7-mf-feedback-message");
        const form = container.closest("form");

        if (!hiddenInput.length) {
            return;
        }

        const d = el.dataset;
        const side = toInt(d.maxWidth, 0) || toInt(d.maxHeight, 0) || FALLBACK.maxSide;
        const cfg = {
            maxWidth: toInt(d.maxWidth, 0) || side,
            maxHeight: toInt(d.maxHeight, 0) || side,
            quality: Math.min(100, Math.max(1, toInt(d.quality, FALLBACK.quality))),
            maxFiles: toInt(d.maxFiles, 0) || FALLBACK.maxFiles,
            fileLimit: toInt(d.fileLimit, FALLBACK.fileLimit),
            totalLimit: toInt(d.totalLimit, 0),
            maxUploads: toInt(d.maxUploads, 0),
        };
        const msg = {
            maxFiles: d.msgMaxFiles || fill(MSG_FALLBACK.maxFiles, { max_file_limit: cfg.maxFiles }),
            fileLarge: d.msgFileLarge || MSG_FALLBACK.fileLarge,
            totalLarge: d.msgTotalLarge || MSG_FALLBACK.totalLarge,
            type: d.msgType || MSG_FALLBACK.type,
            process: d.msgProcess || MSG_FALLBACK.process,
            maxUploads: d.msgMaxUploads || MSG_FALLBACK.maxUploads,
        };
        const accepts = makeAcceptMatcher(d.accept || hiddenInput.attr("accept"));

        let filesArray = [];
        let queue = Promise.resolve();

        const showMessages = (messages) => {
            feedbackMessage.empty();
            if (!messages.length) {
                feedbackMessage.hide();
                return;
            }
            messages.forEach((text, i) => {
                if (i) {
                    feedbackMessage.append("<br>");
                }
                feedbackMessage.append(document.createTextNode(text));
            });
            feedbackMessage.show();
        };

        const updatePreview = () => {
            previewContainer.empty();
            filesArray.forEach((item, index) => {
                const figure = $("<figure>");
                $("<img>", { src: item.preview, alt: item.file.name }).appendTo(figure);
                $("<span>", { class: "cf7-mf-delete-icon", "data-index": index }).appendTo(figure);
                previewContainer.append(figure);
            });
        };

        const updateHiddenInput = () => {
            const dataTransfer = new DataTransfer();
            filesArray.forEach(({ file }) => dataTransfer.items.add(file));
            hiddenInput[0].files = dataTransfer.files;
        };

        const releaseItem = (item) => {
            if (item && item.preview && item.preview.indexOf("blob:") === 0) {
                URL.revokeObjectURL(item.preview);
            }
        };

        // Files selected in the whole form (all file inputs), for PHP max_file_uploads.
        const formFileCount = () => {
            let count = 0;
            form.find("input[type='file']").each(function () {
                if (this !== hiddenInput[0]) {
                    count += this.files ? this.files.length : 0;
                }
            });
            return count + filesArray.length;
        };

        const setProcessing = (on) => {
            container.toggleClass("is-processing", on);
            // Prevent sending the form while images are still being resized.
            form.find("input[type='submit'], button[type='submit']").prop("disabled", on);
        };

        const updateProgress = (current, total) => {
            const progress = total ? (current / total) * 100 : 100;
            container.find(".cf7-mf-progress-bar-wrapper").show();
            container.find(".cf7-mf-progress-bar-fill").css("width", `${progress}%`);
            container.find(".cf7-mf-progress-text").text(`${Math.round(progress)}%`);
        };

        const processFiles = async (fileList) => {
            const incoming = Array.from(fileList || []);
            const messages = [];

            if (!incoming.length) {
                updateHiddenInput(); // e.g. picker cancelled: keep current selection
                return;
            }

            setProcessing(true);

            try {
                const fieldSlots = cfg.maxFiles - filesArray.length;
                const uploadSlots = cfg.maxUploads ? cfg.maxUploads - formFileCount() : Infinity;
                const slots = Math.max(0, Math.min(fieldSlots, uploadSlots));

                let toProcess = incoming;
                if (incoming.length > slots) {
                    toProcess = incoming.slice(0, slots);
                    messages.push(uploadSlots < fieldSlots ? fill(msg.maxUploads, { max_uploads: cfg.maxUploads }) : msg.maxFiles);
                }

                for (let i = 0; i < toProcess.length; i++) {
                    const original = toProcess[i];
                    updateProgress(i, toProcess.length);

                    if (!accepts(original)) {
                        messages.push(`${original.name}: ${msg.type}`);
                        continue;
                    }

                    let file = original;
                    try {
                        if (fileType(original).indexOf("image/") === 0) {
                            const blob = await processImage(original, cfg);
                            file = new File([blob], jpgName(original.name), { type: "image/jpeg", lastModified: Date.now() });
                        } else if (cfg.fileLimit && original.size > cfg.fileLimit) {
                            throw new Error("too-large");
                        }
                    } catch (error) {
                        messages.push(fill(error.message === "too-large" ? msg.fileLarge : msg.process, { file_name: original.name }));
                        continue;
                    }

                    if (cfg.totalLimit) {
                        const total = filesArray.reduce((sum, item) => sum + item.file.size, 0);
                        if (total + file.size > cfg.totalLimit) {
                            messages.push(`${original.name}: ${msg.totalLarge}`);
                            continue;
                        }
                    }

                    const preview = file.type.indexOf("image/") === 0 ? URL.createObjectURL(file) : "";
                    filesArray.push({ file, preview });
                }

                updateProgress(toProcess.length, toProcess.length);
            } finally {
                updatePreview();
                updateHiddenInput();
                showMessages(messages);
                setProcessing(false);
                setTimeout(() => container.find(".cf7-mf-progress-bar-wrapper").hide(), 1000);
            }
        };

        // Run batches one after the other (e.g. a second drop while still resizing).
        const enqueue = (fileList) => {
            const files = Array.from(fileList || []);
            queue = queue.then(() => processFiles(files)).catch(() => {});
            return queue;
        };

        dragDropZone
            .on("dragover", (e) => {
                e.preventDefault();
                dragDropZone.addClass("drag-over");
            })
            .on("dragleave", () => {
                dragDropZone.removeClass("drag-over");
            })
            .on("drop", (e) => {
                e.preventDefault();
                dragDropZone.removeClass("drag-over");
                enqueue(e.originalEvent.dataTransfer.files);
            })
            .on("click", () => hiddenInput.trigger("click"));

        hiddenInput.on("change", (e) => {
            enqueue(e.target.files);
        });

        previewContainer.on("click", ".cf7-mf-delete-icon", function () {
            const index = parseInt($(this).data("index"), 10);
            if (!isNaN(index) && index >= 0 && index < filesArray.length) {
                releaseItem(filesArray[index]);
                filesArray.splice(index, 1);
                updatePreview();
                updateHiddenInput();
                showMessages([]);
            }
        });

        document.addEventListener("wpcf7mailsent", (event) => {
            if (event.target.contains(el)) {
                filesArray.forEach(releaseItem);
                filesArray = [];
                updatePreview();
                updateHiddenInput();
                showMessages([]);
            }
        });
    };

    $(function () {
        $(".cf7-mf-container").each(initField);
    });
})(jQuery);
