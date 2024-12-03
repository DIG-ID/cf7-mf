(function ($) {
  "use strict";

  $(document).ready(function () {
    // Helper: Convert file to data URL (Base64)
    const fileToDataURL = (file) =>
      new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
      });

    // Helper: Resize image to maintain aspect ratio and max size
    const resizeImage = (file, width, height, maxSizeKB) =>
      new Promise(async (resolve, reject) => {
        const img = new Image();
        const dataUrl = await fileToDataURL(file);

        img.src = dataUrl;
        img.onload = () => {
          const canvas = document.createElement("canvas");
          let { width, height } = img;

          // Maintain aspect ratio while resizing
          if (width > height) {
            if (width > maxWidth) {
              height *= maxWidth / width;
              width = maxWidth;
            }
          } else {
            if (height > maxHeight) {
              width *= maxHeight / height;
              height = maxHeight;
            }
          }

          // Resize image on canvas
          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext("2d");
          ctx.drawImage(img, 0, 0, width, height);

          // Convert to blob and check file size
          canvas.toBlob(
            (blob) => {
              if (blob.size / 1024 > maxSizeKB) {
                // Compress further if it's too big
                canvas.toBlob(resolve, "image/jpeg", 0.8); // Adjust compression level
              } else {
                resolve(blob);
              }
            },
            file.type,
            1
          );
        };
        img.onerror = reject;
      });

    // Main function: Handle drag-and-drop or file input
    const initializeFileHandlers = () => {
      $(".cf7-mf-container").each( function () {
        const contentWrapper = $(this);
        const dragDropZone = contentWrapper.find(".cf7-mf-drag-drop-zone");
        const previewContainer = contentWrapper.find(".cf7-mf-preview");
        const hiddenInput = contentWrapper.find(".wpcf7-form-control.wpcf7-multifile");
        const feedbackMessage = contentWrapper.find(".cf7-mf-feedback-message");

        // Read custom data attributes from the wrapper
        const fileSize = dragDropZone.data("file-limit");
        const totalLimit = dragDropZone.data("total-limit");
        const width = dragDropZone.data("width");
        const height = dragDropZone.data("height");
        const acceptedFileTypes = dragDropZone.data("accept");

        let filesArray = [];

        // Update preview with selected files
        const updatePreview = () => {
          previewContainer.empty(); // Clear preview container
          filesArray.forEach((file, index) => {
            const figure = $("<figure>").html(`
              <img src="${file.preview}" alt="Preview">
              <span class="cf7-mf-delete-icon" data-index="${index}"></span>
            `);
            previewContainer.append(figure);
          });
        };

        // Validate and process selected files
        const processFiles = async (files) => {
          for (let file of files) {
            // Validate file type
            if (!file.type.match(acceptedFileTypes)) {
              feedbackMessage.text("Invalid file type.").show();
              continue;
            }

            // Validate file resolution
            if (file.resoltoin > fileResolution) {
              // Resize image and process it
              try {
                const resizedBlob = await resizeImage(file, maxWidth, maxHeight, maxFileSize / 1024);
                const resizedFile = new File([resizedBlob], file.name, { type: resizedBlob.type });

                // Create a preview for the resized image
                const preview = await fileToDataURL(resizedFile);
                filesArray.push({ file: resizedFile, preview });
              } catch (error) {
                feedbackMessage.text("Error processing image.").show();
                return;
              }
            }
            
            // Validate file size
            if (file.size > fileSize) {
              // Resize image and process it
              try {
                const resizedBlob = await resizeImage(file, maxWidth, maxHeight, maxFileSize / 1024);
                const resizedFile = new File([resizedBlob], file.name, { type: resizedBlob.type });
  
                // Create a preview for the resized image
                const preview = await fileToDataURL(resizedFile);
                filesArray.push({ file: resizedFile, preview });
              } catch (error) {
                feedbackMessage.text("Error processing image.").show();
                return;
              }
            }
          }
          updatePreview();
          updateHiddenInput();
        };

        // Update the hidden input with selected files
        const updateHiddenInput = () => {
          const dataTransfer = new DataTransfer();
          filesArray.forEach(({ file }) => dataTransfer.items.add(file));
          hiddenInput[0].files = dataTransfer.files;
        };

        // Handle drag and drop
        const handleDragOver = (event) => {
          event.preventDefault();
          dragDropZone.addClass("drag-over");
        };

        const handleDragLeave = () => {
          dragDropZone.removeClass("drag-over");
        };

        const handleDrop = (event) => {
          event.preventDefault();
          dragDropZone.removeClass("drag-over");
          const files = event.originalEvent.dataTransfer.files;
          processFiles(files);
        };

        // Handle file input selection
        const handleFileSelection = (event) => {
          const files = event.target.files;
          processFiles(files);
        };

        // Handle file removal from the preview
        const handleDelete = (event) => {
          if ($(event.target).hasClass("cf7-mf-delete-icon")) {
            const index = parseInt($(event.target).data("index"), 10);
            filesArray.splice(index, 1);
            updatePreview();
            updateHiddenInput();
          }
        };

        // Event listeners
        dragDropZone.on("dragover", handleDragOver);
        dragDropZone.on("dragleave", handleDragLeave);
        dragDropZone.on("drop", handleDrop);
        dragDropZone.on("click", () => hiddenInput.click()); // Use [0] to access DOM element
        hiddenInput.on("change", handleFileSelection);
        previewContainer.on("click", handleDelete);
      });
    };

    // Initialize handlers for all [multifile] shortcodes
    initializeFileHandlers();
  });
})(jQuery);
