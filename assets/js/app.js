/**
 * Megabre StokMaster Pro
 * Main JavaScript
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

$(document).ready(function() {
    'use strict';
    
    // Toggle sidebar
    $('#sidebarToggle').on('click', function() {
        const windowWidth = $(window).width();
        
        if (windowWidth > 992) {
            // Desktop behavior - collapse/expand
            $('body').toggleClass('sidebar-collapsed');
            
            // Save preference in localStorage
            if ($('body').hasClass('sidebar-collapsed')) {
                localStorage.setItem('sidebar-collapsed', 'true');
            } else {
                localStorage.setItem('sidebar-collapsed', 'false');
            }
        } else {
            // Mobile behavior - show/hide with backdrop
            $('body').toggleClass('sidebar-open');
            
            if ($('body').hasClass('sidebar-open')) {
                // Add backdrop if not exists
                if ($('.sidebar-backdrop').length === 0) {
                    $('body').append('<div class="sidebar-backdrop"></div>');
                }
                
                // Close sidebar when backdrop is clicked
                $('.sidebar-backdrop').on('click', function() {
                    $('body').removeClass('sidebar-open');
                });
            }
        }
    });
    
    // Check localStorage for sidebar state
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        $('body').addClass('sidebar-collapsed');
    }
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Initialize Select2 for all select elements with .select2 class
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
    
    // Initialize DataTables for all tables with .datatable class
    if ($.fn.DataTable) {
        $('.datatable').each(function() {
            const tableId = $(this).attr('id');
            const pageLength = $(this).data('page-length') || 50;
            const lengthMenu = $(this).data('length-menu') || [50, 100, 250, -1];
            const lengthMenuText = $(this).data('length-menu-text') || [50, 100, 250, 'Tümü'];
            
            const dataTableOptions = {
                responsive: true,
                pageLength: pageLength,
                lengthMenu: [lengthMenu, lengthMenuText],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_' + tableId, JSON.stringify(data));
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_' + tableId));
                }
            };
            
            // Check if table has custom order
            const orderColumn = $(this).data('order-column');
            const orderDir = $(this).data('order-dir') || 'asc';
            
            if (orderColumn !== undefined) {
                dataTableOptions.order = [[orderColumn, orderDir]];
            }
            
            // Initialize DataTable
            $(this).DataTable(dataTableOptions);
        });
    }
    
    // AJAX form submission
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitButton = form.find('[type="submit"]');
        const originalButtonText = submitButton.html();
        const successMessage = form.data('success-message') || 'İşlem başarıyla tamamlandı.';
        const errorMessage = form.data('error-message') || 'İşlem sırasında bir hata oluştu.';
        const redirectUrl = form.data('redirect') || '';
        
        // Disable submit button and show loading
        submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> İşleniyor...');
        
        // Perform AJAX request
        $.ajax({
            url: form.attr('action'),
            type: form.attr('method'),
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', successMessage);
                    
                    // Redirect if specified
                    if (redirectUrl) {
                        setTimeout(function() {
                            window.location.href = redirectUrl;
                        }, 1000);
                    } else {
                        // Reset form if no redirect
                        form[0].reset();
                        
                        // Re-initialize Select2 if exists
                        if ($.fn.select2) {
                            form.find('.select2').val(null).trigger('change');
                        }
                    }
                } else {
                    showAlert('danger', response.message || errorMessage);
                }
            },
            error: function(xhr) {
                let errorMsg = errorMessage;
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                showAlert('danger', errorMsg);
            },
            complete: function() {
                // Re-enable submit button
                submitButton.prop('disabled', false).html(originalButtonText);
            }
        });
    });
    
    // Confirmation modal for delete actions
    $('body').on('click', '.delete-confirm', function(e) {
        e.preventDefault();
        
        const deleteUrl = $(this).attr('href') || $(this).data('url');
        const itemName = $(this).data('item-name') || 'Bu öğeyi';
        const redirectUrl = $(this).data('redirect') || '';
        
        if (!deleteUrl) {
            console.error('Delete URL is not specified');
            return;
        }
        
        // Create and show modal
        const modalHtml = `
            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Silme Onayı</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>${itemName}</strong> silmek istediğinize emin misiniz?</p>
                            <p>Bu işlem geri alınamaz!</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="button" class="btn btn-danger" id="confirmDelete">Evet, Sil</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        $('#deleteConfirmModal').remove();
        
        // Add modal to DOM
        $('body').append(modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        modal.show();
        
        // Handle delete confirmation
        $('#confirmDelete').on('click', function() {
            // Show loading
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> İşleniyor...');
            
            // Perform AJAX delete
            $.ajax({
                url: deleteUrl,
                type: 'POST',
                data: { _method: 'DELETE' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('success', response.message || 'Öğe başarıyla silindi.');
                        
                        // Hide modal
                        modal.hide();
                        
                        // Redirect if specified
                        if (redirectUrl) {
                            window.location.href = redirectUrl;
                        } else {
                            // Reload current page
                            window.location.reload();
                        }
                    } else {
                        showAlert('danger', response.message || 'Silme işlemi sırasında bir hata oluştu.');
                        modal.hide();
                    }
                },
                error: function() {
                    showAlert('danger', 'Silme işlemi sırasında bir hata oluştu.');
                    modal.hide();
                }
            });
        });
    });
    
    // AJAX data loader
    $('.ajax-load').each(function() {
        const container = $(this);
        const url = container.data('url');
        const loadingText = container.data('loading-text') || '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>';
        
        if (!url) {
            console.error('Load URL is not specified');
            return;
        }
        
        // Show loading
        container.html(loadingText);
        
        // Load data
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            success: function(response) {
                container.html(response);
                
                // Initialize components in loaded content
                initializeComponents(container);
            },
            error: function() {
                container.html('<div class="alert alert-danger">Veri yüklenirken bir hata oluştu.</div>');
            }
        });
    });
    
    // Initialize components in a container
    function initializeComponents(container) {
        // Initialize Select2
        if ($.fn.select2) {
            container.find('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }
        
        // Initialize DataTables
        if ($.fn.DataTable) {
            container.find('.datatable:not(.dataTable)').each(function() {
                $(this).DataTable({
                    responsive: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                    }
                });
            });
        }
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(container.find('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Function to show alert message
    window.showAlert = function(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Remove existing alerts
        $('.alert-container').remove();
        
        // Create alert container if not exists
        if ($('.alert-container').length === 0) {
            $('.content').prepend('<div class="alert-container"></div>');
        }
        
        // Append alert to container
        $('.alert-container').append(alertHtml);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $('.alert-container .alert').alert('close');
        }, 5000);
    };
    
    // Print functionality
    $('.print-btn').on('click', function(e) {
        e.preventDefault();
        window.print();
    });
    
    // Export to PDF
    $('.export-pdf').on('click', function(e) {
        e.preventDefault();
        
        const element = $($(this).data('target'));
        
        if (!element.length) {
            console.error('Export target not found');
            return;
        }
        
        // Show loading
        showLoading();
        
        // Use html2pdf.js or similar library
        // This is just a placeholder, you need to include html2pdf.js library
        if (typeof html2pdf === 'function') {
            html2pdf()
                .from(element[0])
                .save()
                .then(() => {
                    hideLoading();
                })
                .catch(() => {
                    hideLoading();
                    showAlert('danger', 'PDF oluşturulurken bir hata oluştu.');
                });
        } else {
            hideLoading();
            showAlert('warning', 'PDF oluşturma işlevi yüklenmedi.');
        }
    });
    
    // Export to Excel
    $('.export-excel').on('click', function(e) {
        e.preventDefault();
        
        const table = $($(this).data('target'));
        
        if (!table.length) {
            console.error('Export target not found');
            return;
        }
        
        // Show loading
        showLoading();
        
        // Use tableExport.jquery.plugin or similar library
        // This is just a placeholder, you need to include tableExport library
        if (typeof table.tableExport === 'function') {
            table.tableExport({
                type: 'xlsx',
                fileName: 'export_' + new Date().toISOString().slice(0, 10),
                mso: {
                    fileFormat: 'xlsx',
                    worksheetName: 'Data'
                }
            });
            hideLoading();
        } else {
            hideLoading();
            showAlert('warning', 'Excel oluşturma işlevi yüklenmedi.');
        }
    });
    
    // Number formatting
    $('.number-format').each(function() {
        const value = parseFloat($(this).text());
        
        if (!isNaN(value)) {
            $(this).text(value.toLocaleString('tr-TR'));
        }
    });
    
    // Currency formatting
    $('.currency-format').each(function() {
        const value = parseFloat($(this).text());
        
        if (!isNaN(value)) {
            $(this).text(value.toLocaleString('tr-TR', { 
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' ₺');
        }
    });
    
    // Date picker initialization
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'dd.mm.yyyy',
            language: 'tr',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Show loading overlay
    function showLoading() {
        // Remove existing overlay if any
        $('.loading-overlay').remove();
        
        // Create overlay
        const overlay = $('<div class="loading-overlay"><div class="loading-spinner"></div></div>');
        
        // Add to DOM
        $('body').append(overlay);
    }
    
    // Hide loading overlay
    function hideLoading() {
        $('.loading-overlay').remove();
    }
    
    // Handle server-side errors
    $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
        if (jqXHR.status === 401) {
            // Unauthorized - redirect to login
            window.location.href = 'login.php';
        } else if (jqXHR.status === 403) {
            // Forbidden
            showAlert('danger', 'Bu işlem için yetkiniz bulunmuyor.');
        } else if (jqXHR.status === 404) {
            // Not found
            showAlert('danger', 'İstenen kaynak bulunamadı.');
        } else if (jqXHR.status === 500) {
            // Server error
            showAlert('danger', 'Sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.');
        }
    });
    
    // File input custom text
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
    });
    
    // Initialize scrollbar for sidebar
    if (typeof PerfectScrollbar !== 'undefined') {
        const sidebarPS = new PerfectScrollbar('.sidebar', {
            wheelSpeed: 2,
            wheelPropagation: false,
            minScrollbarLength: 20
        });
    }
    
    // Prevent default on # links
    $('a[href="#"]').on('click', function(e) {
        e.preventDefault();
    });
    
    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        const passwordInput = $($(this).data('target'));
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});