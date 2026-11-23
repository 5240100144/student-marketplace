// CampusMarket client-side interactions

document.addEventListener('DOMContentLoaded', () => {
    // Mobile navigation toggle
    const mobileToggle = document.getElementById('mobileNavToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileToggle && mobileMenu) {
        mobileToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });
    }

    // Image gallery preview switcher
    const mainImg = document.getElementById('mainProductImage');
    const thumbnails = document.querySelectorAll('.thumbnail-btn');
    if (mainImg && thumbnails.length > 0) {
        thumbnails.forEach(btn => {
            btn.addEventListener('click', () => {
                thumbnails.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const newSrc = btn.getAttribute('data-full-img');
                if (newSrc) {
                    mainImg.src = newSrc;
                }
            });
        });
    }

    // Buyer interest modal
    const openInterestBtn = document.getElementById('openInterestModalBtn');
    const interestModal = document.getElementById('interestModal');
    const closeInterestBtns = document.querySelectorAll('.close-interest-modal');
    if (openInterestBtn && interestModal) {
        openInterestBtn.addEventListener('click', () => {
            interestModal.classList.add('active');
        });
        closeInterestBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                interestModal.classList.remove('active');
            });
        });
        interestModal.addEventListener('click', (e) => {
            if (e.target === interestModal) {
                interestModal.classList.remove('active');
            }
        });
    }

    // Product photo upload and preview
    const fileInput = document.getElementById('productPhotosInput');
    const dropzone = document.getElementById('uploadDropzone');
    const previewContainer = document.getElementById('imagePreviewGrid');

    if (fileInput && previewContainer) {
        const dt = new DataTransfer();

        const updatePreviews = () => {
            previewContainer.innerHTML = '';
            Array.from(dt.files).forEach((file, index) => {
                if (!file.type.startsWith('image/')) return;
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button type="button" class="preview-remove" data-index="${index}" title="Remove image">&times;</button>
                    `;
                    previewContainer.appendChild(item);

                    item.querySelector('.preview-remove').addEventListener('click', (ev) => {
                        ev.stopPropagation();
                        removeFileAtIndex(index);
                    });
                };
                reader.readAsDataURL(file);
            });
        };

        const removeFileAtIndex = (indexToRemove) => {
            const newDt = new DataTransfer();
            Array.from(dt.files).forEach((file, idx) => {
                if (idx !== indexToRemove) {
                    newDt.items.add(file);
                }
            });
            dt.items.clear();
            Array.from(newDt.files).forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
            updatePreviews();
        };

        const handleFiles = (files) => {
            const maxFiles = 5;
            for (let i = 0; i < files.length; i++) {
                if (dt.files.length >= maxFiles) {
                    alert(`Maximum ${maxFiles} images allowed per product listing.`);
                    break;
                }
                const file = files[i];
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    alert(`File "${file.name}" is not a supported format (JPEG, PNG, WebP).`);
                    continue;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File "${file.name}" exceeds 5MB size limit.`);
                    continue;
                }
                dt.items.add(file);
            }
            fileInput.files = dt.files;
            updatePreviews();
        };

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        if (dropzone) {
            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
            dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                if (e.dataTransfer.files) {
                    handleFiles(e.dataTransfer.files);
                }
            });
        }
    }

    // Confirmation dialogs
    const deleteTriggers = document.querySelectorAll('.btn-confirm-delete');
    deleteTriggers.forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to delete this listing? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});

