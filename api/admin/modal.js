// Modal functionality
let isEditMode = false;
let createAndAddNew = false; // Flag to determine if form should stay open after creation
let currentCategory = null; // Store current category context
let currentSubcategory = null; // Store current subcategory context

// Function to set the category context (called from category/articles pages)
function setCategoryContext(categoryName, subcategoryName = null) {
    currentCategory = categoryName;
    currentSubcategory = subcategoryName;
    sessionStorage.setItem('selectedCategory', categoryName);
    if (subcategoryName) {
        sessionStorage.setItem('selectedSubcategory', subcategoryName);
    }
}

// Function to get and restore category context
function getCategoryContext() {
    const stored = sessionStorage.getItem('selectedCategory');
    if (stored) {
        currentCategory = stored;
        currentSubcategory = sessionStorage.getItem('selectedSubcategory');
        return true;
    }
    return false;
}

// Function to clear category context
function clearCategoryContext() {
    currentCategory = null;
    currentSubcategory = null;
    sessionStorage.removeItem('selectedCategory');
    sessionStorage.removeItem('selectedSubcategory');
}

function toggleCreateForm() {
    const container = document.getElementById('articleFormContainer');
    const tableContainer = document.getElementById('tableContainer');
    const contentHeader = document.getElementById('contentHeader');
    const filtersSection = document.getElementById('filtersSection');
    if (!container) return;
    
    const isVisible = container.style.display === 'block' || container.style.display === '';
    
    if (isVisible) {
        // Hide form, show header, filters, and table
        container.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'block';
        if (contentHeader) contentHeader.style.display = 'block';
        if (filtersSection) filtersSection.style.display = 'flex';
        document.getElementById('articleModalForm').reset();
        hideAllTypeFields();
        document.getElementById('article_id').value = '';
        selectedTags = [];
        document.getElementById('selected_tags').value = '';
        document.getElementById('selectedTagsDisplay').innerHTML = '<span style="color: #9CA3AF; align-self: center;">No tags selected</span>';
        
        // Re-enable category field
        const categoryGroup = document.getElementById('modal_category')?.closest('.form-group');
        if (categoryGroup) {
            categoryGroup.style.opacity = '1';
            categoryGroup.style.pointerEvents = 'auto';
            const note = categoryGroup.querySelector('.category-note');
            if (note) note.remove();
        }
        
        // Clear category context when closing
        clearCategoryContext();
    } else {
        // Show form, hide header, filters, and table
        isEditMode = false;
        document.getElementById('modalTitle').textContent = 'New Article';
        // Update ALL buttons with id="modalSubmitBtn" (in both mainButtons and stepByStepButtons)
        document.querySelectorAll('#modalSubmitBtn').forEach(btn => {
            btn.textContent = 'Create Article';
        });
        document.getElementById('createAddNewBtn').textContent = 'Create & Add New';
        document.getElementById('articleModalForm').reset();
        document.getElementById('article_id').value = '';
        document.getElementById('modal_type').value = '';
        document.getElementById('modal_status').value = 'Publish';  // Default to Publish (draft)
        hideAllTypeFields();
        container.style.display = 'block';
        if (tableContainer) tableContainer.style.display = 'none';
        if (contentHeader) contentHeader.style.display = 'none';
        if (filtersSection) filtersSection.style.display = 'none';
        
        // Scroll to form
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Load available tags
        loadAvailableTags();
        selectedTags = [];
        document.getElementById('selected_tags').value = '';
        
        // Reinitialize category listener when form is opened
        console.log('Checking if initializeCategoryListener exists:', typeof initializeCategoryListener);
        if (typeof initializeCategoryListener === 'function') {
            console.log('Calling initializeCategoryListener from modal.js');
            setTimeout(() => {
                console.log('Executing initializeCategoryListener after 100ms');
                initializeCategoryListener();
            }, 100);
        } else {
            console.error('ERROR: initializeCategoryListener is NOT a function! Type:', typeof initializeCategoryListener);
        }
        
        // Check if category context is set
        if (getCategoryContext() && currentCategory) {
            // Hide the category dropdown since it's pre-selected
            const categoryDropdownGroup = document.getElementById('categoryDropdownGroup');
            if (categoryDropdownGroup) {
                categoryDropdownGroup.style.display = 'none';
            }
            
            // Pre-select the subcategory (currentCategory is the subcategory name from filter_category)
            const subcategorySelect = document.getElementById('modal_category');
            if (subcategorySelect) {
                subcategorySelect.value = currentCategory;
                
                // Show a visual note that subcategory is pre-selected
                const categoryGroup = subcategorySelect.closest('.form-group');
                if (categoryGroup && !categoryGroup.querySelector('.selected-note')) {
                    const note = document.createElement('small');
                    note.className = 'selected-note';
                    note.style.display = 'block';
                    note.style.marginTop = '0.5rem';
                    note.style.color = '#059669';
                    note.style.fontStyle = 'italic';
                    note.style.fontWeight = '500';
                    categoryGroup.appendChild(note);
                }
            }
        } else {
            // Show the category dropdown if no context and don't clear subcategories
            const categoryDropdownGroup = document.getElementById('categoryDropdownGroup');
            if (categoryDropdownGroup) {
                categoryDropdownGroup.style.display = 'block';
            }
            // Only reset category dropdown, NOT the subcategory - keep PHP-loaded list
            const categorySelect = document.getElementById('modal_category_id');
            if (categorySelect) {
                categorySelect.value = '';
                categorySelect.disabled = false;
                categorySelect.style.opacity = '1';
                categorySelect.style.pointerEvents = 'auto';
            }
            // Remove any existing notes
            const subcategorySelect = document.getElementById('modal_category');
            if (subcategorySelect) {
                const categoryGroup = subcategorySelect.closest('.form-group');
                const note = categoryGroup?.querySelector('.selected-note');
                if (note) note.remove();
            }
        }
        
        // Attach listener to article type select
        const typeSelect = document.getElementById('modal_type');
        if (typeSelect) {
            typeSelect.removeEventListener('change', updateFormFieldsForType);
            typeSelect.addEventListener('change', function() {
                updateFormFieldsForType();
            });
        }
    }
}

function loadAvailableTags() {
    // Load all available tags for new article creation
    fetch('get_tags.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('availableTagsContainer');
            if (data.tags && data.tags.length > 0) {
                container.innerHTML = data.tags.map(tag => `
                    <label class="tag-checkbox-wrapper" title="${tag.tag_name}">
                        <input type="checkbox" value="${tag.tag_id}" data-tag-name="${tag.tag_name}" onchange="updateSelectedTags()">
                        <span>${tag.tag_name}</span>
                    </label>
                `).join('');
            } else {
                container.innerHTML = '<span style="color: #9CA3AF; padding: 0.5rem;">No tags available. Create tags in the Category page first.</span>';
            }
        })
        .catch(error => {
            console.error('Error loading tags:', error);
        });
}


function openAddModal() {
    // Legacy function - redirect to toggle
    toggleCreateForm();
}

function submitAndCreateNew() {
    // Set flag to create and add new
    createAndAddNew = true;
    // Submit the form
    const form = document.getElementById('articleModalForm');
    if (form) {
        form.dispatchEvent(new Event('submit'));
    }
}

function openEditModal(articleId) {
    isEditMode = true;
    document.getElementById('modalTitle').textContent = 'Edit Article';
    // Update ALL buttons with id="modalSubmitBtn" (in both mainButtons and stepByStepButtons)
    document.querySelectorAll('#modalSubmitBtn').forEach(btn => {
        btn.textContent = 'Update Article';
    });
    // Update ALL buttons with id="createAddNewBtn" (in both mainButtons and stepByStepButtons)
    document.querySelectorAll('#createAddNewBtn').forEach(btn => {
        btn.textContent = 'Update & Add New';
    });
    document.getElementById('articleModalForm').reset();
    const container = document.getElementById('articleFormContainer');
    const tableContainer = document.getElementById('tableContainer');
    const contentHeader = document.getElementById('contentHeader');
    const filtersSection = document.getElementById('filtersSection');
    
    if (container) {
        container.style.display = 'block';
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Hide header, filters, and table when editing
    if (tableContainer) tableContainer.style.display = 'none';
    if (contentHeader) contentHeader.style.display = 'none';
    if (filtersSection) filtersSection.style.display = 'none';
    
    // Fetch article data
    console.log('=== Loading article ID:', articleId, '===');
    fetch(`edit_article_modal.php?id=${articleId}`)
        .then(response => {
            console.log('Edit fetch response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Edit fetch response text:', text.substring(0, 500));
            try {
                const data = JSON.parse(text);
                return data;
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                console.error('Response was:', text);
                throw new Error('Invalid response format: ' + parseError.message);
            }
        })
        .then(data => {
            if (data.success) {
                const article = data.article;
                document.getElementById('article_id').value = article.article_id;
                document.getElementById('modal_title').value = article.title;
                
                // Set category_id and fetch subcategories for editing
                if (article.category_id && article.category_id > 0) {
                    const categorySelect = document.getElementById('modal_category_id');
                    if (categorySelect) {
                        // Find and select the category
                        for (let i = 0; i < categorySelect.options.length; i++) {
                            if (categorySelect.options[i].getAttribute('data-cat-id') == article.category_id) {
                                categorySelect.value = categorySelect.options[i].value;
                                break;
                            }
                        }
                        
                        // Trigger change event to fetch subcategories
                        categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                        
                        // Wait for subcategories to load, then set subcategory
                        setTimeout(() => {
                            if (article.subcategory_id && article.subcategory_id > 0) {
                                const subcategorySelect = document.getElementById('modal_category');
                                // Set by subcategory_id value
                                subcategorySelect.value = article.subcategory_id;
                            }
                        }, 300);
                    }
                } else if (article.category) {
                    // Fallback: set by category name if no category_id
                    document.getElementById('modal_category').value = article.category;
                }
                document.getElementById('modal_status').value = article.status || 'Publish';  // Set status field
                
                // Set the article type
                const detectedType = article.type || 'standard';
                document.getElementById('modal_type').value = detectedType;
                
                // Populate fields based on type
                if (detectedType === 'simple_question') {
                    // Set textareas first (for initial data)
                    document.getElementById('modal_question').value = article.question || '';
                    document.getElementById('modal_answer').value = article.answer || '';
                } else if (detectedType === 'step_by_step') {
                    // Set introduction textarea
                    document.getElementById('modal_introduction').value = article.introduction || '';
                    
                    // Rebuild steps container
                    const stepsContainer = document.getElementById('stepsContainer');
                    stepsContainer.innerHTML = '';
                    if (article.steps && article.steps.length > 0) {
                        article.steps.forEach((step, index) => {
                            const stepNum = index + 1;
                            const imagePreview = step.image ? `<div data-original-preview="step-${stepNum}" style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;"><img src="uploads/articles/${step.image}" alt="Step ${stepNum} Image" style="max-width: 100%; max-height: 200px; border-radius: 4px; border: 1px solid #E5E7EB;"><button type="button" class="remove-original-image" data-step="${stepNum}" style="padding: 0.5rem 0.75rem; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 600; white-space: nowrap;">Remove</button></div>` : '';
                            const stepHtml = `
                                <div class="step-item" data-step="${stepNum}">
                                    <input type="text" placeholder="Step ${stepNum} Title" class="step-title" name="step_${stepNum}_title" value="${step.title || ''}" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border: 1px solid #E5E7EB; border-radius: 4px; font-size: 0.875rem;">
                                    <div class="step-editor" data-step="${stepNum}" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 100px; max-height: 200px; margin-bottom: 0.5rem;"></div>
                                    <textarea placeholder="Step ${stepNum} Description" class="step-description" name="step_${stepNum}_description" style="display: none;">${step.description || ''}</textarea>
                                    ${imagePreview}
                                    <input type="file" id="step_${stepNum}_file" class="step-file" name="step_${stepNum}_file" accept="image/*,.pdf,.doc,.docx" style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; background: white; cursor: pointer; font-size: 0.875rem;">
                                </div>
                            `;
                            stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
                        });
                        
                        // Add event listeners to remove buttons
                        document.querySelectorAll('.remove-original-image').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                const stepNum = this.getAttribute('data-step');
                                const previewDiv = document.querySelector(`[data-original-preview="step-${stepNum}"]`);
                                if (previewDiv) {
                                    previewDiv.remove();
                                }
                            });
                        });
                        
                        // Add the "Add Step" button after all steps
                        stepsContainer.insertAdjacentHTML('beforeend', '<div style="margin-top: 0.5rem;"><button type="button" class="btn-secondary" onclick="addStep()">Add Step</button></div>');
                    } else {
                        stepsContainer.innerHTML = '<div class="step-item" data-step="1"><input type="text" placeholder="Step 1 Title" class="step-title" name="step_1_title" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border: 1px solid #E5E7EB; border-radius: 4px; font-size: 0.875rem;"><div class="step-editor" data-step="1" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 100px; max-height: 200px; margin-bottom: 0.5rem;"></div><textarea placeholder="Step 1 Description" class="step-description" name="step_1_description" style="display: none;"></textarea><input type="file" id="step_1_file" class="step-file" name="step_1_file" accept="image/*,.pdf,.doc,.docx" style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; background: white; cursor: pointer; font-size: 0.875rem;"></div><div style="margin-top: 0.5rem;"><button type="button" class="btn-secondary" onclick="addStep()">Add Step</button></div>';
                    }
                } else if (detectedType === 'standard') {
                    document.getElementById('modal_description').value = article.content || '';
                }
                
                // Load and display tags for this article
                if (article.tags && article.tags.length > 0) {
                    loadTagsForEdit(article.tags);
                } else {
                    loadTagsForEdit([]);
                }
                
                // Update form fields visibility
                updateFormFieldsForType();
                
                // Initialize Quill editors with loaded content
                setTimeout(() => {
                    if (typeof initializeQuillEditor === 'function') {
                        // Completely clean up old Quill instances
                        console.log('Cleaning up old Quill instances...');
                        
                        // Remove all Quill toolbars and containers
                        document.querySelectorAll('.ql-toolbar').forEach(toolbar => toolbar.remove());
                        document.querySelectorAll('.ql-container').forEach(container => {
                            container.classList.remove('ql-container');
                        });
                        
                        // Reset Quill editor references
                        window.quillEditors = {
                            description: null,
                            question: null,
                            answer: null,
                            steps: {}
                        };
                        
                        // Clear editor divs
                        const descEditor = document.getElementById('descriptionEditor');
                        if (descEditor) descEditor.innerHTML = '';
                        const qEditor = document.getElementById('questionEditor');
                        if (qEditor) qEditor.innerHTML = '';
                        const aEditor = document.getElementById('answerEditor');
                        if (aEditor) aEditor.innerHTML = '';
                        document.querySelectorAll('.step-editor').forEach(el => {
                            el.innerHTML = '';
                        });
                        
                        // Initialize fresh Quill editors
                        console.log('Calling initializeQuillEditor...');
                        initializeQuillEditor();
                        console.log('Quill editors initialized');
                        
                        // Load content into Quill editors
                        setTimeout(() => {
                            if (detectedType === 'simple_question') {
                                console.log('Loading simple question content...');
                                console.log('Article question:', article.question);
                                console.log('Article answer:', article.answer);
                                
                                if (window.quillEditors && window.quillEditors.question) {
                                    console.log('Setting question content');
                                    window.quillEditors.question.root.innerHTML = article.question || '';
                                    document.getElementById('modal_question').value = article.question || '';
                                }
                                if (window.quillEditors && window.quillEditors.answer) {
                                    console.log('Setting answer content');
                                    window.quillEditors.answer.root.innerHTML = article.answer || '';
                                    document.getElementById('modal_answer').value = article.answer || '';
                                }
                                
                                // Display QA image if exists
                                if (article.qa_image) {
                                    const qaImageSection = document.getElementById('qaCurrentImageSection');
                                    if (qaImageSection) {
                                        qaImageSection.style.display = 'block';
                                        const qaImageDisplay = document.getElementById('qaImageDisplay');
                                        if (qaImageDisplay) {
                                            // Detect image type - try to determine from data or default to jpeg
                                            let mimeType = 'image/jpeg';
                                            
                                            // Try to detect PNG by magic bytes in base64
                                            if (article.qa_image.substring(0, 8) === 'iVBORw0K') {
                                                mimeType = 'image/png';
                                            } else if (article.qa_image.substring(0, 4) === 'R0lG') {
                                                mimeType = 'image/gif';
                                            } else if (article.qa_image.substring(0, 4) === '/9j/') {
                                                mimeType = 'image/jpeg';
                                            }
                                            
                                            qaImageDisplay.src = 'data:' + mimeType + ';base64,' + article.qa_image;
                                        }
                                    }
                                }
                            } else if (detectedType === 'step_by_step') {
                                console.log('Loading step-by-step content...');
                                
                                // Load introduction
                                if (window.quillEditors && window.quillEditors.introduction) {
                                    console.log('Setting introduction content');
                                    window.quillEditors.introduction.root.innerHTML = article.introduction || '';
                                    const introTextarea = document.getElementById('modal_introduction');
                                    if (introTextarea) {
                                        introTextarea.value = article.introduction || '';
                                    }
                                }
                                
                                // Load steps
                                if (article.steps && article.steps.length > 0) {
                                    article.steps.forEach((step, index) => {
                                        const stepNum = index + 1;
                                        if (window.quillEditors && window.quillEditors.steps && window.quillEditors.steps[stepNum]) {
                                            console.log(`Setting step ${stepNum} content`);
                                            window.quillEditors.steps[stepNum].root.innerHTML = step.description || '';
                                            const textarea = document.querySelector(`textarea[name="step_${stepNum}_description"]`);
                                            if (textarea) {
                                                textarea.value = step.description || '';
                                            }
                                        }
                                    });
                                }
                            } else if (detectedType === 'standard') {
                                console.log('Loading standard content...');
                                console.log('Article object:', article);
                                if (window.quillEditors && window.quillEditors.description) {
                                    console.log('Setting description content');
                                    window.quillEditors.description.root.innerHTML = article.content || '';
                                    document.getElementById('modal_description').value = article.content || '';
                                }
                                
                                // Display standard image if exists
                                console.log('Checking for standard_image:', article.standard_image);
                                if (article.standard_image) {
                                    console.log('Found standard_image, displaying...');
                                    const standardImageSection = document.getElementById('standardCurrentImageSection');
                                    console.log('standardImageSection element:', standardImageSection);
                                    if (standardImageSection) {
                                        standardImageSection.style.display = 'block';
                                        const standardImageDisplay = document.getElementById('standardImageDisplay');
                                        console.log('standardImageDisplay element:', standardImageDisplay);
                                        if (standardImageDisplay) {
                                            // Use the filename directly from the uploads folder
                                            standardImageDisplay.src = 'uploads/articles/' + article.standard_image;
                                            console.log('Image src set to:', standardImageDisplay.src);
                                        }
                                    }
                                } else {
                                    console.log('No standard_image found in article object');
                                }
                            }
                        }, 100);
                    }
                }, 200);
                
                // Open modal if it exists (dashboard.php uses modalOverlay)
                const modal = document.getElementById('modalOverlay');
                if (modal) {
                    modal.classList.add('active');
                }
                
                // If articleFormContainer exists (articles.php), show it
                if (container) {
                    container.style.display = 'block';
                }
            } else {
                alert('Error loading article: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading article data');
        });
}



function closeModal() {
    // Only close if it's edit mode (modal overlay), otherwise use toggle for create form
    const container = document.getElementById('articleFormContainer');
    if (container && !isEditMode) {
        toggleCreateForm();
    } else if (container) {
        container.style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('articleModalForm').reset();
        hideAllTypeFields();
        selectedTags = [];
        document.getElementById('selected_tags').value = '';
        document.getElementById('selectedTagsDisplay').innerHTML = '<span style="color: #9CA3AF; align-self: center;">No tags selected</span>';
    }
}

// Article type field visibility
function hideAllTypeFields() {
    const simpleQuestionFields = document.getElementById('simpleQuestionFields');
    const stepByStepFields = document.getElementById('stepByStepFields');
    const standardFields = document.getElementById('standardFields');
    
    if (simpleQuestionFields) simpleQuestionFields.style.display = 'none';
    if (stepByStepFields) stepByStepFields.style.display = 'none';
    if (standardFields) standardFields.style.display = 'none';
}

function updateFormFieldsForType() {
    const typeSelect = document.getElementById('modal_type');
    if (!typeSelect) {
        console.error('Type select not found');
        return;
    }
    
    const selectedType = typeSelect.value;
    console.log('Selected type:', selectedType);
    console.log('Calling hideAllTypeFields...');
    
    hideAllTypeFields();
    
    // Hide/show button containers based on type
    const mainButtons = document.getElementById('mainButtons');
    const stepByStepButtons = document.getElementById('stepByStepButtons');
    
    // Show or hide the two-column layout based on type
    const twoColumnLayout = document.getElementById('twoColumnLayout');
    if (twoColumnLayout) {
        if (selectedType === 'standard' || selectedType === 'simple_question' || selectedType === 'step_by_step') {
            twoColumnLayout.style.display = 'grid';
        } else {
            twoColumnLayout.style.display = 'none';
        }
    }
    
    console.log('Checking selected type...');
    if (selectedType === 'simple_question') {
        console.log('Showing simple question fields');
        const field = document.getElementById('simpleQuestionFields');
        console.log('Simple question field element:', field);
        if (field) {
            field.style.display = 'block';
            console.log('Simple question fields displayed');
        }
        
        // Hide author field for simple question
        const authorGroup = document.getElementById('authorFieldGroup');
        if (authorGroup) {
            authorGroup.style.display = 'none';
        }
        
        // Show main buttons, hide step-by-step buttons
        if (mainButtons) {
            mainButtons.style.display = 'flex';
            mainButtons.style.setProperty('padding-top', '1.5rem', 'important'); // Reset padding for simple question type
        }
        if (stepByStepButtons) stepByStepButtons.style.display = 'none';
    } else if (selectedType === 'step_by_step') {
        console.log('Showing step by step fields');
        const field = document.getElementById('stepByStepFields');
        console.log('Step by step field element:', field);
        if (field) {
            field.style.display = 'block';
            console.log('Step by step fields displayed');
        }
        
        // Hide author field for step-by-step articles
        const authorGroup = document.getElementById('authorFieldGroup');
        if (authorGroup) {
            authorGroup.style.display = 'none';
        }
        
        // Hide main buttons, show step-by-step buttons and add step button
        if (mainButtons) mainButtons.style.display = 'none';
        if (stepByStepButtons) stepByStepButtons.style.display = 'flex';
    } else if (selectedType === 'standard') {
        console.log('Showing standard fields');
        const field = document.getElementById('standardFields');
        console.log('Standard field element:', field);
        if (field) {
            field.style.display = 'block';
            console.log('Standard fields displayed');
        }
        
        // Hide author field for standard
        const authorGroup = document.getElementById('authorFieldGroup');
        if (authorGroup) {
            authorGroup.style.display = 'none';
        }
        
        // Show main buttons, hide step-by-step buttons
        if (mainButtons) {
            mainButtons.style.display = 'flex';
            mainButtons.style.setProperty('padding-top', '4rem', 'important'); // Increase spacing for standard type
        }
        if (stepByStepButtons) stepByStepButtons.style.display = 'none';
    } else {
        console.log('No type selected or invalid type');
    }
}

function addStep() {
    const container = document.getElementById('stepsContainer');
    if (!container) return;
    
    // Find the highest step number currently in the container
    let maxStepNum = 0;
    const stepItems = container.querySelectorAll('.step-item[data-step]');
    stepItems.forEach(item => {
        const stepNum = parseInt(item.getAttribute('data-step'));
        if (stepNum > maxStepNum) {
            maxStepNum = stepNum;
        }
    });
    
    const stepCount = maxStepNum + 1;
    
    const stepHTML = `
        <div class="step-item" data-step="${stepCount}">
            <input type="text" placeholder="Step ${stepCount} Title" class="step-title" name="step_${stepCount}_title" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem; border: 1px solid #E5E7EB; border-radius: 4px; font-size: 0.875rem;">
            <div class="step-editor" data-step="${stepCount}" style="background: white; border: 1px solid #E5E7EB; border-radius: 4px; min-height: 100px; max-height: 200px; margin-bottom: 0.5rem;"></div>
            <textarea placeholder="Step ${stepCount} Description" class="step-description" name="step_${stepCount}_description" style="display: none;"></textarea>
            <div style="margin-top: 1rem; padding: 1rem; background: #F3F4F6; border-radius: 6px;">
                <label for="step_${stepCount}_file" style="display: block; font-weight: 500; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">Upload File (Optional)</label>
                <input type="file" id="step_${stepCount}_file" class="step-file" name="step_${stepCount}_file" accept="image/*,.pdf,.doc,.docx" style="width: 100%; padding: 0.5rem; border: 1px solid #D1D5DB; border-radius: 4px; background: white; cursor: pointer; font-size: 0.875rem;">
                <p style="margin: 0.5rem 0 0 0; font-size: 0.75rem; color: #6B7280;">Supported: JPG, PNG, GIF, PDF, DOC, DOCX (Max 5MB)</p>
            </div>
            <button type="button" class="btn-remove-step" onclick="removeStep(${stepCount})" style="margin-top: 0.5rem; padding: 0.5rem 1rem; background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500;">Remove Step</button>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', stepHTML);
    
    // Move the new step before the "Add Step" button wrapper if it exists
    const addStepButtonWrapper = container.querySelector('div:has(> button.btn-secondary[onclick="addStep()"])');
    if (addStepButtonWrapper) {
        const newStepItem = container.querySelector(`.step-item[data-step="${stepCount}"]`);
        if (newStepItem) {
            container.insertBefore(newStepItem, addStepButtonWrapper);
        }
    }
    
    // Adjust container height to show new step
    adjustContainerHeight();
    
    // Initialize Quill editor for the new step if available
    if (typeof initializeQuillEditor === 'function' && typeof Quill !== 'undefined') {
        setTimeout(() => {
            const newStepEditor = document.querySelector(`.step-editor[data-step="${stepCount}"]`);
            if (newStepEditor && (!window.quillEditors || !window.quillEditors.steps[stepCount])) {
                const editorConfig = {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'font': [] }, { 'size': ['small', false, 'large', 'huge'] }],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'header': 1 }, { 'header': 2 }],
                            ['blockquote', 'code-block'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'script': 'sub'}, { 'script': 'super' }],
                            [{ 'align': [] }],
                            ['link', 'image', 'video'],
                            ['clean']
                        ]
                    },
                    placeholder: 'Enter step description...'
                };
                window.quillEditors.steps[stepCount] = new Quill(newStepEditor, editorConfig);
                window.quillEditors.steps[stepCount].on('text-change', function() {
                    document.querySelector(`textarea[name="step_${stepCount}_description"]`).value = window.quillEditors.steps[stepCount].root.innerHTML;
                });
            }
        }, 100);
    }
    
    // Scroll to the new step
    setTimeout(() => {
        const newStep = container.querySelector(`.step-item[data-step="${stepCount}"]`);
        if (newStep) {
            newStep.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }, 100);
}

function removeStep(stepNum) {
    const stepItem = document.querySelector(`.step-item[data-step="${stepNum}"]`);
    if (stepItem) {
        stepItem.remove();
    }
    
    // Clean up Quill editor instance if it exists
    if (typeof window.quillEditors !== 'undefined' && window.quillEditors.steps && window.quillEditors.steps[stepNum]) {
        delete window.quillEditors.steps[stepNum];
    }
    
    // Adjust container height after removing step
    adjustContainerHeight();
}

// Tags management for article form
let selectedTags = [];

function loadTagsForEdit(currentTags = []) {
    // Load available tags and mark selected ones
    fetch('get_tags.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('availableTagsContainer');
            if (data.tags && data.tags.length > 0) {
                // Use tag_name for matching instead of tags_id, since article tags have different ids
                const currentTagNames = currentTags.map(t => t.tag_name);
                container.innerHTML = data.tags.map(tag => {
                    const isSelected = currentTagNames.includes(tag.tag_name);
                    return `
                        <label class="tag-checkbox-wrapper" title="${tag.tag_name}">
                            <input type="checkbox" value="${tag.tag_id}" data-tag-name="${tag.tag_name}" ${isSelected ? 'checked' : ''} onchange="updateSelectedTags()">
                            <span>${tag.tag_name}</span>
                        </label>
                    `;
                }).join('');
                
                // Update selected tags display
                selectedTags = currentTags.map(t => ({
                    id: t.tag_id,
                    name: t.tag_name
                }));
                updateSelectedTagsDisplay();
                
                // Initialize search for edit mode
                initTagSearch();
            } else {
                container.innerHTML = '<span style="color: #9CA3AF; padding: 0.5rem;">No tags available</span>';
            }
        })
        .catch(error => {
            console.error('Error loading tags:', error);
        });
}

function updateSelectedTags() {
    const checkboxes = document.querySelectorAll('#availableTagsContainer input[type="checkbox"]:checked');
    selectedTags = Array.from(checkboxes).map(cb => ({
        id: cb.value,
        name: cb.dataset.tagName
    }));

    console.log('Selected tags count:', selectedTags.length);
    console.log('Selected tags:', selectedTags);

    // Limit to 3 tags
    if (selectedTags.length > 3) {
        checkboxes[checkboxes.length - 1].checked = false;
        selectedTags.pop();
        showNotification('Maximum 3 tags allowed per article', 'error');
    }

    updateSelectedTagsDisplay();
}

function updateSelectedTagsDisplay() {
    const display = document.getElementById('selectedTagsDisplay');
    if (selectedTags.length > 0) {
        display.innerHTML = selectedTags.map(tag => `
            <span class="tag-badge-item">
                ${tag.name}
                <button type="button" onclick="removeTag(${tag.id})">×</button>
            </span>
        `).join('');
    } else {
        display.innerHTML = '<span style="color: #9CA3AF; align-self: center;">No tags selected</span>';
    }

    // Update hidden input
    const tagsValue = selectedTags.map(t => t.id).join(',');
    console.log('Updating hidden input with tags:', tagsValue);
    document.getElementById('selected_tags').value = tagsValue;
}

function removeTag(tagId) {
    const checkbox = document.querySelector(`#availableTagsContainer input[value="${tagId}"]`);
    if (checkbox) {
        checkbox.checked = false;
        updateSelectedTags();
    }
}

// Function to adjust container height dynamically
function adjustContainerHeight() {
    const container = document.getElementById('articleFormContainer');
    if (!container || container.style.display === 'none') return;
    
    // Set a reasonable max-height based on viewport
    const maxHeight = Math.min(window.innerHeight - 200, 1200);
    container.style.maxHeight = maxHeight + 'px';
}

// Handle form submission
document.addEventListener('DOMContentLoaded', function() {
    // Article type change listener
    const typeSelect = document.getElementById('modal_type');
    if (typeSelect) {
        typeSelect.addEventListener('change', updateFormFieldsForType);
        console.log('Type select listener attached');
    } else {
        console.error('modal_type select not found during DOMContentLoaded');
    }
    
    const form = document.getElementById('articleModalForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Sync all Quill editors to their hidden textareas before form submission
            if (typeof window.quillEditors !== 'undefined') {
                console.log('Syncing Quill editors before submission...');
                
                // Sync description editor
                if (window.quillEditors.description) {
                    const descContent = window.quillEditors.description.root.innerHTML;
                    console.log('Description content:', descContent);
                    document.getElementById('modal_description').value = descContent;
                }
                
                // Sync question editor
                if (window.quillEditors.question) {
                    const qContent = window.quillEditors.question.root.innerHTML;
                    console.log('Question content:', qContent);
                    const qTextarea = document.getElementById('modal_question');
                    if (qTextarea) {
                        qTextarea.value = qContent;
                        console.log('Question textarea updated:', qTextarea.value);
                    }
                }
                
                // Sync answer editor
                if (window.quillEditors.answer) {
                    const aContent = window.quillEditors.answer.root.innerHTML;
                    console.log('Answer content:', aContent);
                    const aTextarea = document.getElementById('modal_answer');
                    if (aTextarea) {
                        aTextarea.value = aContent;
                        console.log('Answer textarea updated:', aTextarea.value);
                    }
                }
                
                // Sync all step editors
                Object.keys(window.quillEditors.steps).forEach(stepNum => {
                    if (window.quillEditors.steps[stepNum]) {
                        const textarea = document.querySelector(`textarea[name="step_${stepNum}_description"]`);
                        if (textarea) {
                            const stepContent = window.quillEditors.steps[stepNum].root.innerHTML;
                            console.log(`Step ${stepNum} content:`, stepContent);
                            textarea.value = stepContent;
                        }
                    }
                });
            }
            
            const formData = new FormData(form);
            
            // When editing, remove empty file inputs and send a preservation flag
            if (isEditMode) {
                console.log('=== EDIT MODE - PRESERVING EXISTING IMAGES ===');
                
                // Add flag to tell server we're in edit mode and want to preserve existing images
                formData.append('preserve_existing_images', '1');
                
                // For standard articles
                const standardImageInput = document.getElementById('modal_standard_image');
                if (standardImageInput) {
                    const hasFiles = standardImageInput.files && standardImageInput.files.length > 0;
                    console.log('Standard image input:', {
                        hasFiles: hasFiles,
                        filesLength: standardImageInput.files ? standardImageInput.files.length : 'N/A'
                    });
                    if (!hasFiles) {
                        formData.delete('standard_image');
                        console.log('✓ Removed empty standard_image - will preserve existing from DB');
                    }
                }
                
                // For step-by-step articles - remove empty step file inputs
                let stepNum = 1;
                while (true) {
                    const stepFileInput = document.getElementById(`step_${stepNum}_file`);
                    if (!stepFileInput) break; // No more steps
                    
                    const hasFiles = stepFileInput.files && stepFileInput.files.length > 0;
                    if (!hasFiles) {
                        formData.delete(`step_${stepNum}_file`);
                        console.log(`✓ Removed empty step_${stepNum}_file - will preserve existing from DB`);
                    }
                    stepNum++;
                }
                
                // For simple question articles - qa_image (if it exists and is empty)
                const qaImageInput = document.getElementById('modal_qa_image');
                if (qaImageInput) {
                    const hasFiles = qaImageInput.files && qaImageInput.files.length > 0;
                    if (!hasFiles) {
                        formData.delete('qa_image');
                        console.log('✓ Removed empty qa_image - will preserve existing from DB');
                    }
                }
            }
            
            // Add subcategory_id to formData
            const categorySelect = document.getElementById('modal_category');
            if (categorySelect && categorySelect.selectedOptions[0]) {
                const selectedOption = categorySelect.selectedOptions[0];
                const subcategoryId = selectedOption.value;
                if (subcategoryId) {
                    formData.set('subcategory_id', subcategoryId);
                    console.log('Added subcategory_id:', subcategoryId);
                }
            }
            
            // Ensure tags are included in the form data
            const tagsInput = document.getElementById('selected_tags');
            if (tagsInput) {
                formData.set('tags', tagsInput.value);
            }
            
            const url = isEditMode ? 'edit_article_modal.php' : 'add_article_modal.php';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Show success message
                        showNotification(data.message, 'success');
                        
                        // If createAndAddNew flag is set, reset form and keep it open
                        if (createAndAddNew) {
                            createAndAddNew = false; // Reset flag
                            isEditMode = false; // Exit edit mode
                            
                            // Get current URL parameters to preserve category/subcategory if filtering
                            const urlParams = new URLSearchParams(window.location.search);
                            const filterCategory = urlParams.get('filter_category') || '';
                            
                            // Reset the form fields
                            document.getElementById('articleModalForm').reset();
                            document.getElementById('article_id').value = '';
                            document.getElementById('modal_type').value = '';
                            document.getElementById('modal_status').value = 'Publish';  // Default to Publish (draft)
                            document.getElementById('modalTitle').textContent = 'New Article';
                            document.getElementById('modalSubmitBtn').textContent = 'Create Article';
                            document.getElementById('createAddNewBtn').textContent = 'Create & Add New';
                            hideAllTypeFields();
                            
                            // Clear all image file inputs
                            const fileInputs = [
                                'modal_standard_image',
                                'modal_step_image',
                                'modal_qa_image'
                            ];
                            fileInputs.forEach(inputId => {
                                const input = document.getElementById(inputId);
                                if (input) {
                                    input.value = '';
                                    input.type = 'file';
                                    input.type = 'file'; // Reset the file input
                                }
                            });
                            
                            // Clear all image preview displays
                            const imagePreviews = [
                                'articleImageDisplay',
                                'qaImageDisplay',
                                'standardImageDisplay'
                            ];
                            imagePreviews.forEach(previewId => {
                                const preview = document.getElementById(previewId);
                                if (preview) {
                                    preview.src = '';
                                    preview.alt = '';
                                }
                            });
                            
                            // Hide image preview sections
                            const imageSections = [
                                'currentImageSection',
                                'qaCurrentImageSection',
                                'standardCurrentImageSection',
                                'standardImageSection'
                            ];
                            imageSections.forEach(sectionId => {
                                const section = document.getElementById(sectionId);
                                if (section) {
                                    section.style.display = 'none';
                                }
                            });
                            
                            // Clear Quill editors
                            if (typeof clearQuillEditors === 'function') {
                                clearQuillEditors();
                            }
                            
                            // Reset tags
                            selectedTags = [];
                            document.getElementById('selected_tags').value = '';
                            document.getElementById('selectedTagsDisplay').innerHTML = '<span style="color: #9CA3AF; align-self: center;">No tags selected</span>';
                            
                            // Restore category and subcategory from filter if they exist
                            if (filterCategory) {
                                const categorySelect = document.getElementById('modal_category');
                                if (categorySelect) {
                                    // Find and select the filtered subcategory
                                    for (let i = 0; i < categorySelect.options.length; i++) {
                                        if (categorySelect.options[i].value === filterCategory) {
                                            categorySelect.value = filterCategory;
                                            // Trigger change event to ensure dependencies are updated
                                            categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            // Scroll back to top of form
                            document.getElementById('articleFormContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        } else {
                            // Normal behavior - close form and reload with pagination
                            if (!isEditMode) {
                                toggleCreateForm();
                            } else {
                                closeModal();
                            }
                            setTimeout(() => {
                                // Check if we came from a category or subcategory page
                                const referrer = document.referrer || '';
                                let redirectUrl = 'articles.php';
                                
                                if (referrer.includes('category_page.php')) {
                                    // Extract cat_id from referrer
                                    const url = new URL(referrer);
                                    const catId = url.searchParams.get('cat_id');
                                    if (catId) {
                                        redirectUrl = `../client/category_page.php?cat_id=${catId}`;
                                    }
                                } else if (referrer.includes('subcategory_page.php')) {
                                    // Extract subcat_id from referrer
                                    const url = new URL(referrer);
                                    const subcatId = url.searchParams.get('subcat_id');
                                    if (subcatId) {
                                        redirectUrl = `../client/subcategory_page.php?subcat_id=${subcatId}`;
                                    }
                                } else {
                                    // Standard redirect to articles.php with pagination
                                    const urlParams = new URLSearchParams(window.location.search);
                                    const perPage = urlParams.get('per_page') || '5';
                                    const filterMainCategory = urlParams.get('filter_main_category') || '';
                                    const filterCategory = urlParams.get('filter_category') || '';
                                    const filterType = urlParams.get('filter_type') || '';
                                    const search = urlParams.get('search') || '';
                                    const sortBy = urlParams.get('sort_by') || 'date_desc';
                                    
                                    // For edit mode, reset to page 1 to show newly edited article
                                    // For create mode, keep current page
                                    const currentPage = isEditMode ? '1' : (urlParams.get('page') || '1');
                                    
                                    // Build redirect URL with pagination parameters
                                    redirectUrl = `articles.php?page=${currentPage}&per_page=${perPage}&sort_by=${sortBy}`;
                                    if (filterMainCategory) redirectUrl += `&filter_main_category=${encodeURIComponent(filterMainCategory)}`;
                                    if (filterCategory) redirectUrl += `&filter_category=${encodeURIComponent(filterCategory)}`;
                                    if (filterType) redirectUrl += `&filter_type=${encodeURIComponent(filterType)}`;
                                    if (search) redirectUrl += `&search=${encodeURIComponent(search)}`;
                                }
                                
                                window.location.href = redirectUrl;
                            }, 1000);
                        }
                    } else {
                        showNotification(data.message || 'An error occurred', 'error');
                        createAndAddNew = false; // Reset flag on error
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    showNotification('Server error: ' + text.substring(0, 100), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        });
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
});

function showNotification(message, type) {
    // Remove existing notifications
    const existing = document.querySelector('.notification-toast');
    if (existing) {
        existing.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification-toast notification-${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Hide and remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Show article details modal
function showArticleDetails(articleId, event) {
    event.stopPropagation();
    
    fetch(`edit_article_modal.php?id=${articleId}`, {
        credentials: 'same-origin',
        method: 'GET'
    })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response text:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                return data;
            } catch (e) {
                console.error('JSON parse error:', e, 'Text was:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        })
        .then(data => {
            if (data.success) {
                const article = data.article;
                const detectedType = article.type || 'standard';
                
                // Populate details modal
                document.getElementById('detailsTitle').textContent = article.title;
                document.getElementById('detailsType-text').textContent = detectedType === 'simple_question' ? 'Simple Question' : detectedType === 'step_by_step' ? 'Step-by-Step' : 'Standard';
                document.getElementById('detailsCategory-text').textContent = article.category;
                
                // Format the date properly
                let formattedDate = '';
                if (article.article_date) {
                    const dateObj = new Date(article.article_date);
                    formattedDate = dateObj.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                } else if (article.created_at) {
                    const dateObj = new Date(article.created_at);
                    formattedDate = dateObj.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                }
                document.getElementById('detailsDate-text').textContent = formattedDate;
                
                // Populate tags in header
                const tagsContainer = document.getElementById('detailsTags-container');
                console.log('Article type:', detectedType);
                console.log('Article tags:', article.tags);
                if (article.tags && article.tags.length > 0) {
                    tagsContainer.innerHTML = article.tags.map(tag => `
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #F59E0B; color: white; padding: 0.5rem 1rem 0.5rem 0.5rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink: 0;">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            </svg>
                            ${tag.tag_name || tag}
                        </div>
                    `).join('');
                } else {
                    tagsContainer.innerHTML = '';
                }
                
                // Display content based on article type
                const detailsContent = document.getElementById('detailsContent-text');
                const standardContentDiv = document.getElementById('standardContent');
                const simpleQuestionDiv = document.getElementById('simpleQuestionContent');
                
                if (detectedType === 'simple_question') {
                    // Parse question and answer from content
                    const qMatch = article.content.match(/^(.*?)\n\nA:/is);
                    const aMatch = article.content.match(/A: (.*?)$/is);
                    
                    let question = qMatch ? qMatch[1].replace(/^Q: /, '') : '';
                    let answer = aMatch ? aMatch[1] : '';
                    
                    // Strip HTML tags and entities for readable text
                    const stripHtml = (html) => {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        let text = tempDiv.textContent || tempDiv.innerText || '';
                        // Remove any remaining HTML-like characters
                        text = text.replace(/<[^>]*>/g, '').trim();
                        return text;
                    };
                    
                    question = stripHtml(question);
                    answer = stripHtml(answer);
                    
                    // Populate Simple Question specific elements with plain text
                    document.getElementById('simpleQuestionText').textContent = question;
                    document.getElementById('simpleAnswerText').textContent = answer;
                    
                    // Show Simple Question layout, hide Standard
                    simpleQuestionDiv.style.display = 'block';
                    standardContentDiv.style.display = 'none';
                } else if (detectedType === 'step_by_step') {
                    // Parse steps from content
                    const steps = article.steps || [];
                    console.log('Step by Step Article - Steps data:', steps);
                    console.log('Number of steps:', steps.length);
                    console.log('Full article object:', article);
                    let stepsHtml = `
                        <div style="display: flex; flex-direction: column; gap: 2.5rem;">
                    `;
                    
                    // Add introduction section if available
                    if (article.introduction && article.introduction.trim() !== '') {
                        stepsHtml += `
                            <div style="margin-bottom: 2rem;">
                                <h3 style="font-size: 1rem; font-weight: 700; color: #374151; margin: 0 0 0.5rem 0;">Introduction</h3>
                                <div style="font-size: 0.95rem; line-height: 1.8; color: #4B5563;">
                                    ${article.introduction}
                                </div>
                            </div>
                        `;
                    }
                    
                    steps.forEach((step, index) => {
                        // Log step data for debugging
                        console.log(`Step ${index + 1}:`, step);
                        console.log(`Step image field:`, step.image);
                        
                        // Build image HTML if image exists
                        let imageHtml = '';
                        if (step.image) {
                            const imageSrc = `/FAQ/admin/uploads/articles/${step.image}`;
                            imageHtml = `
                                <div style="border: 2px solid #E5E7EB; border-radius: 8px; padding: 1rem; background: #F9FAFB;">
                                    <img src="${imageSrc}" alt="Step ${step.step_num || index + 1}" style="width: 100%; border-radius: 6px; object-fit: cover; max-height: 300px;" onerror="this.style.display='none'; this.parentElement.innerHTML='<p style=&quot;color: #9CA3AF; text-align: center; padding: 2rem;&quot;>Image not found</p>';">
                                </div>
                            `;
                            console.log(`Image URL for Step ${index + 1}: ${imageSrc}`);
                        } else {
                            imageHtml = `
                                <div style="border: 2px solid #E5E7EB; border-radius: 8px; padding: 1rem; background: #F3F4F6; display: flex; align-items: center; justify-content: center; min-height: 250px;">
                                    <span style="color: #9CA3AF;">No image available</span>
                                </div>
                            `;
                            console.log(`No image for Step ${index + 1}`);
                        }
                        
                        stepsHtml += `
                            <div>
                                <h3 style="font-size: 1.125rem; font-weight: 700; color: #1F2937; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.75rem;">
                                    <span style="display: flex; align-items: center; justify-content: center; min-width: 32px; width: 32px; height: 32px; background: #3B82F6; color: white; border-radius: 50%; font-weight: 700; font-size: 0.875rem; flex-shrink: 0;">
                                        ${step.step_num || index + 1}
                                    </span>
                                    ${step.title || 'Step ' + (step.step_num || index + 1)}
                                </h3>
                                
                                <!-- Two Column Layout: Description on left, Image on right -->
                                <div style="display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; align-items: flex-start;">
                                    <!-- Left: Step Description -->
                                    <div style="font-size: 0.95rem; line-height: 1.8; color: #374151; white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; min-width: 0;">
                                        ${step.description || ''}
                                    </div>
                                    
                                    <!-- Right: Step Image -->
                                    <div style="position: sticky; top: 1rem; width: 100%; flex-shrink: 0;">
                                        ${imageHtml}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    stepsHtml += `</div>`;
                    detailsContent.innerHTML = stepsHtml;
                    standardContentDiv.style.display = 'block';
                    simpleQuestionDiv.style.display = 'none';
                } else {
                    // Standard article - render HTML content with image
                    let standardHtml = '';
                    
                    // Add image if available
                    if (article.standard_image) {
                        const imageSrc = `/FAQ/admin/uploads/articles/${article.standard_image}`;
                        standardHtml += `
                            <div style="margin-bottom: 2rem; text-align: center;">
                                <img src="${imageSrc}" alt="Article Image" style="max-width: 100%; height: auto; border-radius: 8px; object-fit: contain; max-height: 400px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" onerror="this.style.display='none';">
                            </div>
                        `;
                    }
                    
                    // Strip HTML tags and display as readable text
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = article.content;
                    const plainText = tempDiv.textContent || tempDiv.innerText || '';
                    
                    standardHtml += `<p style="white-space: pre-wrap; line-height: 1.8;">${plainText}</p>`;
                    detailsContent.innerHTML = standardHtml;
                    standardContentDiv.style.display = 'block';
                    simpleQuestionDiv.style.display = 'none';
                }
                
                // Set delete button handler with custom confirmation modal
                const articleIdForDelete = articleId;
                document.getElementById('deleteBtn').onclick = function(e) {
                    e.preventDefault();
                    showDeleteConfirmation(articleIdForDelete);
                    return false;
                };
                
                // Store article ID for edit
                document.getElementById('detailsModalOverlay').setAttribute('data-article-id', articleId);
                
                // Show details modal
                document.getElementById('detailsModalOverlay').classList.add('active');
            } else {
                console.error('API returned error:', data.message);
                alert('Error loading article: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Full error details:', error);
            alert('Error loading article data: ' + error.message);
        });
}

function closeDetailsModal() {
    document.getElementById('detailsModalOverlay').classList.remove('active');
}

function editArticleFromDetails() {
    const articleId = document.getElementById('detailsModalOverlay').getAttribute('data-article-id');
    closeDetailsModal();
    openEditModal(articleId);
}

// Tag search functionality for both Create and Edit modes
function initTagSearch() {
    const searchInput = document.getElementById('tagSearchInput');
    if (searchInput) {
        searchInput.removeEventListener('input', filterTags);
        searchInput.addEventListener('input', filterTags);
    }
}

function filterTags() {
    const searchInput = document.getElementById('tagSearchInput');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const tagWrappers = document.querySelectorAll('#availableTagsContainer .tag-checkbox-wrapper');
    let visibleCount = 0;

    tagWrappers.forEach(wrapper => {
        const tagName = wrapper.querySelector('span').textContent.toLowerCase();
        if (tagName.includes(searchTerm)) {
            wrapper.classList.remove('hidden');
            visibleCount++;
        } else {
            wrapper.classList.add('hidden');
        }
    });

    // Show "no results" message if no tags match
    let noResultsMsg = document.getElementById('tagsNoResults');
    if (visibleCount === 0 && tagWrappers.length > 0) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'tagsNoResults';
            noResultsMsg.className = 'tag-search-no-results';
            noResultsMsg.style.cssText = 'grid-column: 1 / -1; color: #9CA3AF; text-align: center; padding: 1rem; font-size: 0.875rem;';
            noResultsMsg.textContent = 'No tags found matching your search';
            document.getElementById('availableTagsContainer').appendChild(noResultsMsg);
        }
    } else if (noResultsMsg) {
        noResultsMsg.remove();
    }
}

// Delete Confirmation Modal
function showDeleteConfirmation(articleId) {
    const modal = document.getElementById('deleteConfirmationOverlay');
    modal.style.display = 'flex';
    
    // Handle cancel button
    const cancelBtn = document.getElementById('deleteConfirmCancel');
    cancelBtn.onclick = function() {
        modal.style.display = 'none';
    };
    
    // Handle confirm delete button
    const confirmBtn = document.getElementById('deleteConfirmYes');
    confirmBtn.onclick = function() {
        modal.style.display = 'none';
        // Get current pagination parameters from URL
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = urlParams.get('page') || '1';
        const perPage = urlParams.get('per_page') || '5';
        const filterMainCategory = urlParams.get('filter_main_category') || '';
        const filterCategory = urlParams.get('filter_category') || '';
        const filterType = urlParams.get('filter_type') || '';
        const search = urlParams.get('search') || '';
        const sortBy = urlParams.get('sort_by') || 'date_desc';
        
        // Build redirect URL with pagination parameters
        let redirectUrl = `delete.php?id=${articleId}&page=${currentPage}&per_page=${perPage}&sort_by=${sortBy}`;
        if (filterMainCategory) redirectUrl += `&filter_main_category=${encodeURIComponent(filterMainCategory)}`;
        if (filterCategory) redirectUrl += `&filter_category=${encodeURIComponent(filterCategory)}`;
        if (filterType) redirectUrl += `&filter_type=${encodeURIComponent(filterType)}`;
        if (search) redirectUrl += `&search=${encodeURIComponent(search)}`;
        
        // Proceed with deletion
        window.location.href = redirectUrl;
    };
    
    // Close modal on clicking outside
    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    };
}

// Confirm modal.js has loaded
console.log('modal.js loaded successfully');
console.log('updateFormFieldsForType function available:', typeof updateFormFieldsForType);

// Add event listener for file inputs to show preview
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('step-file')) {
        const fileInput = e.target;
        const stepNum = fileInput.id.match(/step_(\d+)_file/)[1];
        const file = fileInput.files[0];
        
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(event) {
                // Remove original preview if it exists
                const originalPreview = fileInput.parentElement.querySelector('[data-original-preview="step-' + stepNum + '"]');
                if (originalPreview) {
                    originalPreview.remove();
                }
                
                // Remove existing new preview if any
                const existingPreview = fileInput.parentElement.querySelector('[data-preview="step-' + stepNum + '"]');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                // Create new preview
                const previewDiv = document.createElement('div');
                previewDiv.setAttribute('data-preview', 'step-' + stepNum);
                previewDiv.style.cssText = 'margin-bottom: 0.5rem; margin-top: 0.5rem;';
                previewDiv.innerHTML = `<img src="${event.target.result}" alt="Step ${stepNum} Preview" style="max-width: 100%; max-height: 250px; border-radius: 4px; border: 1px solid #E5E7EB;">`;
                
                // Insert preview before the file input
                fileInput.parentElement.insertBefore(previewDiv, fileInput);
            };
            reader.readAsDataURL(file);
        }
    }
}, true);
