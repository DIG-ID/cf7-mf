(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

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
			const resizeImage = (file, maxWidth, maxHeight, maxSizeKB) =>
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
			const initializeFileHandlers = (form) => {
					const dragDropZone = form.find(".cf7-mf-drag-drop-zone");
					const previewContainer = form.find(".cf7-mf-preview");
					const hiddenInput = form.find(".wpcf7-form-control.wpcf7-multifile");
					const feedbackMessage = form.find(".cf7-mf-feedback-message");

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
									// Validate file type (only images allowed)
									if (!file.type.startsWith("image/")) {
											feedbackMessage.text("Only image files are allowed!").show();
											continue;
									}

									// Resize image and process it
									try {
											const resizedBlob = await resizeImage(
													file,
													dragDropZone.data("width") || 720,
													dragDropZone.data("height") || 480,
													dragDropZone.data("file-limit") || 1024 // Max size in KB
											);

											const resizedFile = new File([resizedBlob], file.name, {
													type: resizedBlob.type,
											});

											// Create a preview for the resized image
											const preview = await fileToDataURL(resizedFile);
											filesArray.push({ file: resizedFile, preview });
									} catch (error) {
											feedbackMessage.text("Error resizing image. Please try again.").show();
											return;
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

					// Handle drag over event (to provide visual feedback)
					const handleDragOver = (event) => {
							event.preventDefault();
							dragDropZone.addClass("drag-over");
					};

					const handleDragLeave = () => {
							dragDropZone.removeClass("drag-over");
					};

					// Handle file drop event
					const handleDrop = (event) => {
							event.preventDefault();
							dragDropZone.removeClass("drag-over");
							const files = event.originalEvent.dataTransfer.files;
							processFiles(files);
					};

					// Handle file input selection (when the user clicks to select files)
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

					// Event listeners for drag-and-drop and file input
					dragDropZone.on("dragover", handleDragOver);
					dragDropZone.on("dragleave", handleDragLeave);
					dragDropZone.on("drop", handleDrop);
					dragDropZone.on("click", () => hiddenInput.click());
					hiddenInput.on("change", handleFileSelection);
					previewContainer.on("click", handleDelete);

					// Clear feedback messages after some time
					setTimeout(() => {
							feedbackMessage.fadeOut();
					}, 5000);
			};

			// Initialize handlers for each form
			$(".wpcf7").each(function () {
					const form = $(this);
					if (form.find(".cf7-mf-drag-drop-zone").length > 0) {
							initializeFileHandlers(form);
					}
			});

			// Handle form submission (optional - validate files before submitting)
			$(".wpcf7").on("submit", function (event) {
					const form = $(this);
					const hiddenInput = form.find(".wpcf7-form-control.wpcf7-multifile");

					// If there are no files selected, prevent submission and show error
					if (hiddenInput[0].files.length === 0) {
							form.find(".cf7-mf-feedback-message").text("Please select at least one file.").show();
							event.preventDefault(); // Prevent form submission
					}
			});

			// Handle file input after form submission (for clearing or resetting)
			document.addEventListener('wpcf7mailsent', function(event) {
					$(".cf7-mf-preview p").remove();
			});

			// To avoid "Bad Request" error in Safari when file input is empty
			$('.wpcf7-form').submit(function () {
					var inputs = $('.wpcf7-form input[type="file"]:not([disabled])');
					inputs.each(function (_, input) {
							if (input.files.length > 0) return;
							$(input).prop('disabled', true);
					});
			});

			// Re-enable file inputs after form submission
			document.addEventListener('wpcf7submit', function (event) {
					var inputs = $('.wpcf7-form input[type="file"][disabled]');
					inputs.each(function (_, input) {
							$(input).prop('disabled', false);
					});
			}, false);
	});


})( jQuery );
