(function ($) {
    "use strict";

    $(document).ready(function () {
        // Helper: Convert file to data URL
        const fileToDataURL = (file) => {
            console.log('Converting to DataURL:', file.name);
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = (error) => {
                    console.error('FileReader error:', error);
                    reject(error);
                };
                reader.readAsDataURL(file);
            });
        };

        // Helper: Resize image maintaining aspect ratio
        const resizeImage = (file, maxWidth, maxHeight, maxSizeKB) => {
            console.log('Resizing image:', {
                file: file.name,
                maxWidth,
                maxHeight,
                maxSizeKB
            });

            return new Promise(async (resolve, reject) => {
                try {
                    const img = new Image();
                    const dataUrl = await fileToDataURL(file);
                    
                    img.onload = () => {
                        const canvas = document.createElement("canvas");
                        let newWidth = img.width;
                        let newHeight = img.height;

                        // Calculate new dimensions
                        if (newWidth > newHeight) {
                            if (newWidth > maxWidth) {
                                newHeight *= maxWidth / newWidth;
                                newWidth = maxWidth;
                            }
                        } else {
                            if (newHeight > maxHeight) {
                                newWidth *= maxHeight / newHeight;
                                newHeight = maxHeight;
                            }
                        }

                        console.log('New dimensions:', {
                            width: Math.round(newWidth),
                            height: Math.round(newHeight)
                        });

                        canvas.width = newWidth;
                        canvas.height = newHeight;
                        const ctx = canvas.getContext("2d");
                        ctx.drawImage(img, 0, 0, newWidth, newHeight);

                        canvas.toBlob(
                            (blob) => {
                                const blobSize = blob.size / 1024;
                                console.log('Blob size:', Math.round(blobSize) + 'KB');
                                
                                if (blobSize > maxSizeKB) {
                                    console.log('Compressing image further');
                                    canvas.toBlob(
                                        resolve,
                                        file.type,
                                        0.7 // Compression quality
                                    );
                                } else {
                                    resolve(blob);
                                }
                            },
                            file.type,
                            1
                        );
                    };

                    img.onerror = (error) => {
                        console.error('Image loading error:', error);
                        reject(error);
                    };

                    img.src = dataUrl;
                } catch (error) {
                    console.error('Resize error:', error);
                    reject(error);
                }
            });
        };

        // Initialize file handlers
        $(".cf7-mf-container").each(function () {
            const container = $(this);
            const dragDropZone = container.find(".cf7-mf-drag-drop-zone");
            const previewContainer = container.find(".cf7-mf-preview");
            const hiddenInput = container.find("input[type='file']");
            const feedbackMessage = container.find(".cf7-mf-feedback-message");

            // Get configuration from data attributes
            const maxWidth = parseInt(dragDropZone.data("width")) || 800;
            const maxHeight = parseInt(dragDropZone.data("height")) || 600;
            const maxFileSize = parseInt(dragDropZone.data("file-limit")) || 1024;
            const totalLimit = parseInt(dragDropZone.data("total-limit")) || 10;
            const acceptedTypes = dragDropZone.data("accept") || "image/*";

            // Initialize files array
            container.data('filesArray', []);

            // Update preview
            const updatePreview = (filesArray) => {
                console.group('Updating Preview');
                previewContainer.empty();
                
                filesArray.forEach((item, index) => {
                    console.log(`Adding preview for: ${item.file.name}`);
                    const figure = $("<figure>").html(`
                        <img src="${item.preview}" alt="Preview">
                        <span class="cf7-mf-delete-icon" data-index="${index}"></span>
                    `);
                    previewContainer.append(figure);
                });
                console.groupEnd();
            };

            // Update hidden input
            const updateHiddenInput = (filesArray) => {
                console.log('Updating hidden input:', filesArray.length, 'files');
                const dataTransfer = new DataTransfer();
                filesArray.forEach(({ file }) => dataTransfer.items.add(file));
                hiddenInput[0].files = dataTransfer.files;
            };

            // Process files
            const processFiles = async (files) => {
                console.group('Processing Files');
                try {
                    let filesArray = container.data('filesArray') || [];
                    
                    // Validate total files
                    if (filesArray.length + files.length > totalLimit) {
                        throw new Error(`Maximum ${totalLimit} files allowed`);
                    }

                    // Update progress
                    const updateProgress = (current, total) => {
                        const progress = (current / total) * 100;
                        const progressBar = container.find('.cf7-mf-progress-bar-fill');
                        const progressText = container.find('.cf7-mf-progress-text');
                        
                        container.find('.cf7-mf-progress-bar-wrapper').show();
                        progressBar.css('width', `${progress}%`);
                        progressText.text(`${Math.round(progress)}%`);
                        
                        console.log('Progress:', Math.round(progress) + '%');
                    };

                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        console.log('Processing:', file.name);

                        // Validate file type
                        if (!file.type.match(acceptedTypes)) {
                            console.warn('Invalid file type:', file.type);
                            feedbackMessage.text(`Invalid file type: ${file.type}`).show();
                            continue;
                        }

                        try {
                            if (file.type.startsWith('image/')) {
                                const resizedBlob = await resizeImage(file, maxWidth, maxHeight, maxFileSize);
                                const resizedFile = new File([resizedBlob], file.name, { type: resizedBlob.type });
                                const preview = await fileToDataURL(resizedFile);
                                filesArray.push({ file: resizedFile, preview });
                            } else {
                                const preview = await fileToDataURL(file);
                                filesArray.push({ file, preview });
                            }
                            
                            updateProgress(i + 1, files.length);
                        } catch (error) {
                            console.error('Error processing file:', error);
                            feedbackMessage.text(`Error processing ${file.name}`).show();
                        }
                    }

                    // Update container data and UI
                    container.data('filesArray', filesArray);
                    updatePreview(filesArray);
                    updateHiddenInput(filesArray);

                    // Hide progress after delay
                    setTimeout(() => {
                        container.find('.cf7-mf-progress-bar-wrapper').hide();
                    }, 1000);

                } catch (error) {
                    console.error('Processing error:', error);
                    feedbackMessage.text(error.message).show();
                }
                console.groupEnd();
            };

            // Event handlers
            dragDropZone
                .on("dragover", (e) => {
                    e.preventDefault();
                    dragDropZone.addClass("drag-over");
                })
                .on("dragleave", () => {
                    dragDropZone.removeClass("drag-over");
                })
                .on("drop", async (e) => {
                    e.preventDefault();
                    dragDropZone.removeClass("drag-over");
                    await processFiles(e.originalEvent.dataTransfer.files);
                })
                .on("click", () => hiddenInput.click());

            hiddenInput.on("change", async (e) => {
                await processFiles(e.target.files);
            });

            // Delete handler
            previewContainer.on("click", ".cf7-mf-delete-icon", function() {
                const index = parseInt($(this).data("index"));
                console.log('Deleting file at index:', index);
                
                let filesArray = container.data('filesArray') || [];
                if (!isNaN(index) && index >= 0 && index < filesArray.length) {
                    filesArray.splice(index, 1);
                    container.data('filesArray', filesArray);
                    updatePreview(filesArray);
                    updateHiddenInput(filesArray);
                }
            });

            // Form submission cleanup
            document.addEventListener("wpcf7mailsent", function (event) {
                if (event.target.contains(container[0])) {
                    console.log('Form submitted, clearing files');
                    container.data('filesArray', []);
                    updatePreview([]);
                    updateHiddenInput([]);
                    feedbackMessage.hide();
                }
            });
        });
    });
})(jQuery);