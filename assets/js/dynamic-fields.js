/**
 * Megabre StokMaster Pro
 * Dynamic Fields JavaScript
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

$(document).ready(function() {
    'use strict';
    
    // Keep track of field count
    let fieldCount = $('.dynamic-field').length;
    const maxFieldCount = 20; // Maximum number of dynamic fields
    
    // Update add button state based on field count
    function updateAddButtonState() {
        if (fieldCount >= maxFieldCount) {
            $('#addFieldBtn').prop('disabled', true).addClass('disabled');
            $('#fieldCountWarning').show();
        } else {
            $('#addFieldBtn').prop('disabled', false).removeClass('disabled');
            $('#fieldCountWarning').hide();
        }
    }
    
    // Initialize state
    updateAddButtonState();
    
    // Add new field
    $('#addFieldBtn').on('click', function(e) {
        e.preventDefault();
        
        if (fieldCount >= maxFieldCount) {
            showAlert('warning', 'Maksimum alan sayısına ulaştınız (20).');
            return;
        }
        
        fieldCount++;
        
        // Generate unique ID for new field
        const fieldId = 'field_' + Date.now();
        
        // Create new field HTML
        const newField = `
            <div class="dynamic-field" id="${fieldId}">
                <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="${fieldId}" title="Kaldır">
                    <i class="ti ti-x"></i>
                </button>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="${fieldId}_name" class="form-label">Alan Adı</label>
                            <input type="text" class="form-control" id="${fieldId}_name" name="fields[${fieldId}][name]" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="${fieldId}_type" class="form-label">Alan Türü</label>
                            <select class="form-select field-type-select" id="${fieldId}_type" name="fields[${fieldId}][type]" data-field-id="${fieldId}" required>
                                <option value="">Seçiniz</option>
                                <option value="text">Metin</option>
                                <option value="number">Sayı</option>
                                <option value="select">Seçim</option>
                                <option value="textarea">Metin Alanı</option>
                                <option value="date">Tarih</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3" id="${fieldId}_options_container" style="display: none;">
                        <div class="mb-3">
                            <label for="${fieldId}_options" class="form-label">Seçenekler</label>
                            <input type="text" class="form-control" id="${fieldId}_options" name="fields[${fieldId}][options]" placeholder="Virgülle ayırın">
                        </div>
                    </div>
                    <div class="col-md-5" id="${fieldId}_value_container" style="display: none;">
                        <div class="mb-3">
                            <label for="${fieldId}_value" class="form-label">Değer</label>
                            <input type="text" class="form-control field-value-input" id="${fieldId}_value" name="fields[${fieldId}][value]" data-field-id="${fieldId}" style="display: none;">
                            <div id="${fieldId}_value_wrapper"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append to container
        $('#dynamicFieldsContainer').append(newField);
        
        // Update add button state
        updateAddButtonState();
        
        // Focus on name input of new field
        $(`#${fieldId}_name`).focus();
    });
    
    // Remove field
    $(document).on('click', '.dynamic-field-remove', function() {
        const fieldId = $(this).data('field-id');
        $(`#${fieldId}`).remove();
        
        fieldCount--;
        
        // Update add button state
        updateAddButtonState();
    });
    
    // Show/hide options field and value field based on field type
    $(document).on('change', '.field-type-select', function() {
        const fieldId = $(this).data('field-id');
        const fieldType = $(this).val();
        const valueContainer = $(`#${fieldId}_value_container`);
        const valueWrapper = $(`#${fieldId}_value_wrapper`);
        
        // Handle options container
        if (fieldType === 'select') {
            $(`#${fieldId}_options_container`).show();
            $(`#${fieldId}_options`).prop('required', true);
        } else {
            $(`#${fieldId}_options_container`).hide();
            $(`#${fieldId}_options`).prop('required', false);
        }
        
        // Handle value container and input type
        if (fieldType) {
            valueContainer.show();
            
            // Change input type based on field type
            let inputHtml = '';
            switch(fieldType) {
                case 'text':
                    inputHtml = '<input type="text" class="form-control field-value-input" id="' + fieldId + '_value" name="fields[' + fieldId + '][value]" data-field-id="' + fieldId + '">';
                    break;
                case 'number':
                    inputHtml = '<input type="number" class="form-control field-value-input" id="' + fieldId + '_value" name="fields[' + fieldId + '][value]" data-field-id="' + fieldId + '" step="any">';
                    break;
                case 'textarea':
                    inputHtml = '<textarea class="form-control field-value-input" id="' + fieldId + '_value" name="fields[' + fieldId + '][value]" data-field-id="' + fieldId + '" rows="3"></textarea>';
                    break;
                case 'date':
                    inputHtml = '<input type="date" class="form-control field-value-input" id="' + fieldId + '_value" name="fields[' + fieldId + '][value]" data-field-id="' + fieldId + '">';
                    break;
                case 'select':
                    // Get options from options field if available
                    const optionsField = $(`#${fieldId}_options`);
                    let optionsHtml = '<option value="">Seçiniz</option>';
                    if (optionsField.length && optionsField.val()) {
                        const options = optionsField.val().split(',').map(opt => opt.trim()).filter(opt => opt);
                        options.forEach(function(option) {
                            optionsHtml += '<option value="' + option + '">' + option + '</option>';
                        });
                    }
                    inputHtml = '<select class="form-select field-value-input" id="' + fieldId + '_value" name="fields[' + fieldId + '][value]" data-field-id="' + fieldId + '">' + optionsHtml + '</select>';
                    break;
                default:
                    inputHtml = '<input type="text" class="form-control field-value-input" id="' + fieldId + '_value" name="fields[' + fieldId + '][value]" data-field-id="' + fieldId + '">';
            }
            
            valueWrapper.html(inputHtml);
        } else {
            valueContainer.hide();
        }
    });
    
    // Initialize existing select fields and show value containers
    $('.field-type-select').each(function() {
        const fieldId = $(this).data('field-id');
        const fieldType = $(this).val();
        
        if (fieldType === 'select') {
            $(`#${fieldId}_options_container`).show();
            $(`#${fieldId}_options`).prop('required', true);
        }
        
        // Show value container if field type is selected and trigger change to create input
        if (fieldType) {
            $(this).trigger('change');
        }
    });
    
    // Update select options when options field changes
    $(document).on('input', '[id$="_options"]', function() {
        const fieldId = $(this).attr('id').replace('_options', '');
        const fieldType = $(`#${fieldId}_type`).val();
        const valueWrapper = $(`#${fieldId}_value_wrapper`);
        const valueInput = valueWrapper.find('select');
        
        if (fieldType === 'select' && valueInput.length) {
            const options = $(this).val().split(',').map(opt => opt.trim()).filter(opt => opt);
            valueInput.empty().append('<option value="">Seçiniz</option>');
            options.forEach(function(option) {
                valueInput.append('<option value="' + option + '">' + option + '</option>');
            });
        }
    });
    
    // Handle form submission
    $('#dynamicFieldsForm').on('submit', function(e) {
        // Check if any field is added
        if (fieldCount === 0) {
            e.preventDefault();
            showAlert('warning', 'En az bir dinamik alan eklemelisiniz.');
            return;
        }
        
        // Validate field names (unique)
        const fieldNames = {};
        let hasError = false;
        
        $('.dynamic-field').each(function() {
            const fieldId = $(this).attr('id');
            const fieldName = $(`#${fieldId}_name`).val();
            
            if (fieldName in fieldNames) {
                e.preventDefault();
                showAlert('danger', `"${fieldName}" alanı zaten eklenmiş. Alan adları benzersiz olmalıdır.`);
                $(`#${fieldId}_name`).addClass('is-invalid');
                hasError = true;
                return false; // Break each loop
            }
            
            fieldNames[fieldName] = true;
            $(`#${fieldId}_name`).removeClass('is-invalid');
        });
        
        if (hasError) {
            return;
        }
        
        // Validate select options
        $('.field-type-select').each(function() {
            const fieldId = $(this).data('field-id');
            const fieldType = $(this).val();
            
            if (fieldType === 'select') {
                const options = $(`#${fieldId}_options`).val();
                
                if (!options || options.trim() === '') {
                    e.preventDefault();
                    showAlert('danger', 'Seçim alanları için en az bir seçenek girmelisiniz.');
                    $(`#${fieldId}_options`).addClass('is-invalid');
                    hasError = true;
                    return false; // Break each loop
                }
                
                $(`#${fieldId}_options`).removeClass('is-invalid');
            }
        });
    });
    
    // Category dynamic fields related functions
    if ($('#categoryFieldsForm').length) {
        // Handle category selection change
        $('#category_id').on('change', function() {
            const categoryId = $(this).val();
            
            if (!categoryId) {
                $('#dynamicFieldsContainer').html('');
                return;
            }
            
            // Load fields for selected category
            $.ajax({
                url: 'api/categories.php?action=get_fields',
                type: 'GET',
                data: { category_id: categoryId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Clear container
                        $('#dynamicFieldsContainer').html('');
                        
                        // Add fields
                        if (response.fields && response.fields.length > 0) {
                            fieldCount = response.fields.length;
                            
                            response.fields.forEach(function(field) {
                                addCategoryField(field);
                            });
                        } else {
                            fieldCount = 0;
                            $('#dynamicFieldsContainer').html('<div class="alert alert-info">Bu kategori için henüz dinamik alan tanımlanmamış.</div>');
                        }
                        
                        // Update add button state
                        updateAddButtonState();
                    } else {
                        showAlert('danger', response.message || 'Kategori alanları yüklenirken bir hata oluştu.');
                    }
                },
                error: function() {
                    showAlert('danger', 'Kategori alanları yüklenirken bir hata oluştu.');
                }
            });
        });
        
        // Add category field with data
        function addCategoryField(field) {
            const fieldId = 'field_' + (field.id || Date.now());
            
            // Create field HTML
            const fieldHtml = `
                <div class="dynamic-field" id="${fieldId}">
                    <button type="button" class="btn btn-danger dynamic-field-remove" data-field-id="${fieldId}" title="Kaldır">
                        <i class="ti ti-x"></i>
                    </button>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="${fieldId}_name" class="form-label">Alan Adı</label>
                                <input type="text" class="form-control" id="${fieldId}_name" name="fields[${fieldId}][name]" value="${field.field_name || ''}" required>
                                ${field.id ? `<input type="hidden" name="fields[${fieldId}][id]" value="${field.id}">` : ''}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="${fieldId}_type" class="form-label">Alan Türü</label>
                                <select class="form-select field-type-select" id="${fieldId}_type" name="fields[${fieldId}][type]" data-field-id="${fieldId}" required>
                                    <option value="">Seçiniz</option>
                                    <option value="text" ${field.field_type === 'text' ? 'selected' : ''}>Metin</option>
                                    <option value="number" ${field.field_type === 'number' ? 'selected' : ''}>Sayı</option>
                                    <option value="select" ${field.field_type === 'select' ? 'selected' : ''}>Seçim</option>
                                    <option value="textarea" ${field.field_type === 'textarea' ? 'selected' : ''}>Metin Alanı</option>
                                    <option value="date" ${field.field_type === 'date' ? 'selected' : ''}>Tarih</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3" id="${fieldId}_options_container" style="${field.field_type === 'select' ? '' : 'display: none;'}">
                            <div class="mb-3">
                                <label for="${fieldId}_options" class="form-label">Seçenekler</label>
                                <input type="text" class="form-control" id="${fieldId}_options" name="fields[${fieldId}][options]" placeholder="Virgülle ayırın" value="${field.field_options ? formatOptions(field.field_options) : ''}">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Append to container
            $('#dynamicFieldsContainer').append(fieldHtml);
        }
        
        // Format options from JSON to comma-separated string
        function formatOptions(optionsJson) {
            try {
                const options = JSON.parse(optionsJson);
                return Array.isArray(options) ? options.join(', ') : '';
            } catch (e) {
                return optionsJson;
            }
        }
    }
    
    // Product dynamic fields related functions
    if ($('#productForm').length) {
        // Handle category selection change
        $('#category_id').on('change', function() {
            const categoryId = $(this).val();
            
            if (!categoryId) {
                $('#categoryFieldsContainer').html('');
                return;
            }
            
            // Load fields for selected category
            $.ajax({
                url: 'api/categories.php?action=get_fields',
                type: 'GET',
                data: { category_id: categoryId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Clear container
                        $('#categoryFieldsContainer').html('');
                        
                        // Add fields
                        if (response.fields && response.fields.length > 0) {
                            response.fields.forEach(function(field, index) {
                                addProductCategoryField(field, index);
                            });
                        } else {
                            $('#categoryFieldsContainer').html('<div class="alert alert-info">Bu kategori için henüz dinamik alan tanımlanmamış.</div>');
                        }
                    } else {
                        showAlert('danger', response.message || 'Kategori alanları yüklenirken bir hata oluştu.');
                    }
                },
                error: function() {
                    showAlert('danger', 'Kategori alanları yüklenirken bir hata oluştu.');
                }
            });
        });
        
        // Add product category field
        function addProductCategoryField(field, index) {
            const fieldId = `cat_field_${index}`;
            
            let fieldHtml = `
                <div class="mb-3" id="${fieldId}">
                    <label for="${fieldId}_value" class="form-label">${field.field_name}</label>
                    <input type="hidden" name="category_fields[${index}][field_id]" value="${field.id}">
                    <input type="hidden" name="category_fields[${index}][field_name]" value="${field.field_name}">
                    <input type="hidden" name="category_fields[${index}][field_type]" value="${field.field_type}">
            `;
            
            // Create field based on type
            switch (field.field_type) {
                case 'text':
                    fieldHtml += `<input type="text" class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]">`;
                    break;
                    
                case 'number':
                    fieldHtml += `<input type="number" class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]" step="any">`;
                    break;
                    
                case 'select':
                    fieldHtml += `<select class="form-select" id="${fieldId}_value" name="category_fields[${index}][field_value]">
                        <option value="">Seçiniz</option>`;
                    
                    // Add options
                    try {
                        const options = JSON.parse(field.field_options);
                        if (Array.isArray(options)) {
                            options.forEach(function(option) {
                                fieldHtml += `<option value="${option}">${option}</option>`;
                            });
                        }
                    } catch (e) {
                        console.error('Options parsing error:', e);
                    }
                    
                    fieldHtml += `</select>`;
                    break;
                    
                case 'textarea':
                    fieldHtml += `<textarea class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]" rows="3"></textarea>`;
                    break;
                    
                case 'date':
                    fieldHtml += `<input type="date" class="form-control" id="${fieldId}_value" name="category_fields[${index}][field_value]">`;
                    break;
            }
            
            fieldHtml += `</div>`;
            
            // Append to container
            $('#categoryFieldsContainer').append(fieldHtml);
        }
        
        // Initialize existing category if product is being edited
        if ($('#category_id').val()) {
            $('#category_id').trigger('change');
        }
    }
    
    // Function to show alert message
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Remove existing alerts
        $('.dynamic-fields-alert').remove();
        
        // Create alert container if not exists
        if ($('.dynamic-fields-alert').length === 0) {
            $('#dynamicFieldsForm').prepend('<div class="dynamic-fields-alert"></div>');
        }
        
        // Append alert to container
        $('.dynamic-fields-alert').html(alertHtml);
    }
});