<?php
/**
 * Megabre StokMaster Pro
 * Footer Template
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Get database stats
$db = Database::getInstance();
$db_size = $db->getDatabaseSize();
$query_count = $db->getQueryCount();
$query_time = $db->getQueryTime();

// Get memory usage
$memory_usage = getMemoryUsage();

// Get execution time
$execution_time = getExecutionTime();

// Get system versions
$versions = getSystemVersions();
?>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="footer footer-transparent d-print-none">
                <div class="container-xl">
                    <div class="row text-center align-items-center flex-row-reverse">
                        <div class="col-lg-auto ms-lg-auto">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    <span class="badge bg-primary">DB: <?php echo $db_size; ?> MB</span>
                                </li>
                                <li class="list-inline-item">
                                    <span class="badge bg-secondary"><?php echo $execution_time; ?>s</span>
                                </li>
                                <li class="list-inline-item">
                                    <span class="badge bg-info"><?php echo $query_count; ?> sorgu</span>
                                </li>
                                <li class="list-inline-item">
                                    <span class="badge bg-success"><?php echo $memory_usage; ?></span>
                                </li>
                                <li class="list-inline-item">
                                    <button class="btn btn-sm" id="updateSystem" title="Sistemi Güncelle">
                                        <i class="ti ti-refresh"></i>
                                    </button>
                                </li>
                                <li class="list-inline-item">
                                    <button class="btn btn-sm" id="helpButton" title="Yardım" data-bs-toggle="modal" data-bs-target="#helpModal">
                                        <i class="ti ti-help"></i>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                            <ul class="list-inline list-inline-dots mb-0">
                                <li class="list-inline-item">
                                    &copy; <?php echo date('Y'); ?> <a href="https://megabre.com" target="_blank" class="link-secondary">Megabre</a>. Tüm hakları saklıdır.
                                </li>
                                <li class="list-inline-item">
                                    <a href="#" class="link-secondary" rel="noopener">v<?php echo $versions['app']; ?> | PHP <?php echo $versions['php']; ?> / MySQL <?php echo $versions['mysql']; ?></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal modal-blur fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="helpModalLabel">Megabre StokMaster Pro Yardım</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mt-3">
                        <h5>Hızlı Yardım</h5>
                        <p>Megabre StokMaster Pro, işletmenizin stok, ürün, müşteri ve sipariş takibini kolaylıkla yapmanızı sağlayan profesyonel bir yazılımdır.</p>
                        
                        <h6>Temel Özellikler:</h6>
                        <ul>
                            <li>Kategori ve ürün yönetimi</li>
                            <li>Müşteri takibi ve mali işlemler</li>
                            <li>Stok giriş/çıkış yönetimi</li>
                            <li>Sipariş oluşturma ve takibi</li>
                            <li>Detaylı raporlama ve analiz</li>
                            <li>Hesaplama araçları</li>
                            <li>Yedekleme ve veri transferi</li>
                        </ul>
                        
                        <p>Daha detaylı bilgi ve destek için <a href="https://megabre.com/destek" target="_blank">Megabre Destek Sayfasını</a> ziyaret edebilirsiniz.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Utils JS (if on dashboard) -->
    <?php if (isset($_GET['module']) && $_GET['module'] == 'dashboard'): ?>
    <script src="<?php echo asset('js/chart-utils.js'); ?>"></script>
    <?php endif; ?>
    
    <!-- Dynamic Fields JS (if editing something with dynamic fields) -->
    <?php if (isset($_GET['action']) && in_array($_GET['action'], ['add', 'edit', 'fields'])): ?>
    <script src="<?php echo asset('js/dynamic-fields.js'); ?>"></script>
    <?php endif; ?>
    
    <!-- Module specific JS -->
    <?php
    $module = isset($_GET['module']) ? $_GET['module'] : 'dashboard';
    $js_file = asset('js/' . $module . '.js');
    $js_path = ROOT_PATH . 'assets/js/' . $module . '.js';
    
    if (file_exists($js_path) && $module != 'app'): // Prevent loading app.js if it exists
    ?>
    <script src="<?php echo $js_file; ?>"></script>
    <?php endif; ?>
    
    <!-- Update System AJAX -->
    <script>
        $(document).ready(function() {
            // System update button
            $('#updateSystem').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).html('<i class="ti ti-loader-2 spinner"></i>');
                
                $.ajax({
                    url: '<?php echo url('api/update-system.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('success', response.message);
                            // Reload page after 2 seconds
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function(xhr) {
                        showAlert('danger', 'Sistem güncellenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
                    },
                    complete: function() {
                        button.prop('disabled', false).html('<i class="ti ti-refresh"></i>');
                    }
                });
            });
            
            // Function to show alert
            function showAlert(type, message) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" role="alert" style="z-index: 9999;">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                // Remove any existing alerts
                $('.alert').remove();
                
                // Add new alert
                $('body').append(alertHtml);
                
                // Auto hide after 5 seconds
                setTimeout(function() {
                    $('.alert').alert('close');
                }, 5000);
            }
        });
    </script>
</body>
</html>