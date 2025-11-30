<?php
/**
 * Megabre StokMaster Pro
 * Backup Tool
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect('login.php');
}

// Check if user has access to tools
if (!$auth->isAdmin()) {
    redirect('index.php');
}

// Initialize database connection
$db = Database::getInstance();

// Check users table structure
$db->query("SHOW COLUMNS FROM users");
$columns = $db->resultSet();
$columnNames = array_column($columns, 'Field');

// Add surname column if it doesn't exist
if (!in_array('surname', $columnNames)) {
    $db->query("ALTER TABLE users ADD COLUMN surname VARCHAR(50) NOT NULL AFTER name");
    $db->execute();
}

// Create necessary tables if they don't exist
$db->query("CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    type ENUM('full', 'structure', 'data') NOT NULL DEFAULT 'full',
    size BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->execute();

$db->query("CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_id INT NOT NULL,
    action ENUM('create', 'restore', 'delete', 'download') NOT NULL,
    details TEXT,
    created_at DATETIME NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (backup_id) REFERENCES backups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->execute();

// Process actions
$message = '';
$status = '';

// Create backup directory if it doesn't exist
if (!file_exists(BACKUP_PATH)) {
    mkdir(BACKUP_PATH, 0755, true);
}

/**
 * PHP-based database backup function
 * Creates SQL dump using PHP/PDO when mysqldump is not available
 */
function createPhpBackup($db, $config, $backupFilePath, $backupType = 'full') {
    $file = fopen($backupFilePath, 'w');
    
    if (!$file) {
        throw new Exception("Yedek dosyası oluşturulamadı: {$backupFilePath}");
    }
    
    // Write SQL header
    fwrite($file, "-- Megabre StokMaster Pro Database Backup\n");
    fwrite($file, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($file, "-- Database: {$config['database']}\n");
    fwrite($file, "-- Backup Type: {$backupType}\n");
    fwrite($file, "--\n\n");
    fwrite($file, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($file, "SET AUTOCOMMIT = 0;\n");
    fwrite($file, "START TRANSACTION;\n");
    fwrite($file, "SET time_zone = \"+00:00\";\n\n");
    fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
    fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
    fwrite($file, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
    fwrite($file, "/*!40101 SET NAMES utf8mb4 */;\n\n");
    
    try {
        // Get all tables
        $db->query("SHOW TABLES");
        $tables = $db->resultSet();
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            
            // Get table structure
            if ($backupType == 'full' || $backupType == 'structure') {
                fwrite($file, "--\n");
                fwrite($file, "-- Table structure for table `{$tableName}`\n");
                fwrite($file, "--\n\n");
                fwrite($file, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                
                $db->query("SHOW CREATE TABLE `{$tableName}`");
                $createTable = $db->single();
                $createTableSql = $createTable['Create Table'];
                
                fwrite($file, $createTableSql . ";\n\n");
            }
            
            // Get table data
            if ($backupType == 'full' || $backupType == 'data') {
                $db->query("SELECT * FROM `{$tableName}`");
                $rows = $db->resultSet();
                
                if (count($rows) > 0) {
                    fwrite($file, "--\n");
                    fwrite($file, "-- Dumping data for table `{$tableName}`\n");
                    fwrite($file, "--\n\n");
                    
                    // Get column names
                    $db->query("SHOW COLUMNS FROM `{$tableName}`");
                    $columns = $db->resultSet();
                    $columnNames = array_column($columns, 'Field');
                    
                    // Get PDO instance for proper quoting
                    $pdo = $db->getPdo();
                    
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columnNames as $col) {
                            $value = isset($row[$col]) ? $row[$col] : null;
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                // Use PDO quote for proper escaping
                                $values[] = $pdo->quote($value);
                            }
                        }
                        
                        fwrite($file, "INSERT INTO `{$tableName}` (`" . implode('`, `', $columnNames) . "`) VALUES (" . implode(', ', $values) . ");\n");
                    }
                    
                    fwrite($file, "\n");
                }
            }
        }
        
        // Write SQL footer
        fwrite($file, "COMMIT;\n");
        fwrite($file, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
        fwrite($file, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
        fwrite($file, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
        
        fclose($file);
        
    } catch (Exception $e) {
        fclose($file);
        if (file_exists($backupFilePath)) {
            unlink($backupFilePath);
        }
        throw new Exception("PHP yedekleme hatası: " . $e->getMessage());
    }
}

// Process create backup action
if (isset($_POST['create_backup'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=backup');
    }
    
    $backupType = isset($_POST['backup_type']) ? $_POST['backup_type'] : 'full';
    $includeData = isset($_POST['include_data']) ? true : false;
    
    try {
        // Create backup file name
        $backupFileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backupFilePath = BACKUP_PATH . $backupFileName;
        
        // Get MySQL connection details
        $config = [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASS
        ];
        
        // Find mysqldump path
        $mysqldumpPath = '';
        
        // Laragon paths - dynamically find MySQL version
        $laragonBase = 'C:\\laragon\\bin\\mysql';
        $laragonPaths = [];
        if (is_dir($laragonBase)) {
            $mysqlDirs = glob($laragonBase . '\\mysql*', GLOB_ONLYDIR);
            foreach ($mysqlDirs as $mysqlDir) {
                $laragonPaths[] = $mysqlDir . '\\bin\\mysqldump.exe';
            }
        }
        
        // Check common paths
        $possiblePaths = array_merge(
            $laragonPaths, // Laragon paths first
            [
                // Windows paths
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
                'C:\\wamp\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
                // Linux paths
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/usr/local/mysql/bin/mysqldump',
                '/opt/mysql/bin/mysqldump'
            ]
        );
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $mysqldumpPath = $path;
                break;
            }
        }
        
        // If not found in common paths, try to find in PATH
        if (empty($mysqldumpPath)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                exec('where mysqldump 2>nul', $output, $returnVar);
                if ($returnVar === 0 && !empty($output[0])) {
                    $mysqldumpPath = trim($output[0]);
                }
            } else {
                exec('which mysqldump', $output, $returnVar);
                if ($returnVar === 0 && !empty($output[0])) {
                    $mysqldumpPath = trim($output[0]);
                }
            }
        }
        
        // If mysqldump still not found, use PHP-based backup
        if (empty($mysqldumpPath)) {
            // Use PHP-based backup method
            createPhpBackup($db, $config, $backupFilePath, $backupType);
        } else {
            // Use mysqldump command
            // Build mysqldump command
            $command = "\"{$mysqldumpPath}\" --host={$config['host']} --user={$config['username']} ";
            
            if (!empty($config['password'])) {
                $command .= "--password={$config['password']} ";
            }
            
            if ($backupType == 'structure') {
                $command .= "--no-data ";
            } elseif ($backupType == 'data') {
                $command .= "--no-create-info ";
            }
            
            $command .= "--single-transaction --routines --triggers {$config['database']} > \"{$backupFilePath}\"";
            
            // Execute command
            $output = [];
            $returnVar = 0;
            exec($command . " 2>&1", $output, $returnVar);
            
            if ($returnVar !== 0) {
                // If mysqldump fails, fallback to PHP method
                createPhpBackup($db, $config, $backupFilePath, $backupType);
            } else {
                if (!file_exists($backupFilePath) || filesize($backupFilePath) == 0) {
                    // If file doesn't exist or is empty, use PHP method
                    createPhpBackup($db, $config, $backupFilePath, $backupType);
                }
            }
        }
        
        if (!file_exists($backupFilePath)) {
            throw new Exception(t('backup_file_not_found', 'Yedek dosyası oluşturulamadı:') . ' ' . $backupFilePath);
        }
        
        // Compress backup if it's larger than 1MB
        $fileSize = filesize($backupFilePath);
        if ($fileSize > 1024 * 1024) {
            $zipFileName = $backupFilePath . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($backupFilePath, $backupFileName);
                $zip->close();
                
                // Remove original SQL file if zip was created successfully
                if (file_exists($zipFileName)) {
                    unlink($backupFilePath);
                    $backupFileName .= '.zip';
                }
            }
        }
        
        // Log backup
        $db->query("INSERT INTO backups (filename, type, size, created_at, created_by) 
                   VALUES (:filename, :type, :size, NOW(), :created_by)");
        $db->bind(':filename', $backupFileName);
        $db->bind(':type', $backupType);
        $db->bind(':size', $fileSize);
        $db->bind(':created_by', Session::get('user')['id']);
        $db->execute();
        
        $message = t('backup_create_success', 'Yedekleme başarıyla oluşturuldu.');
        $status = 'success';
        
    } catch (Exception $e) {
        $message = t('backup_create_error', 'Yedekleme oluşturulurken hata oluştu:') . ' ' . $e->getMessage();
        $status = 'error';
    }
}

// Process restore backup action
if (isset($_POST['restore_backup'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=backup');
    }
    
    $backupId = isset($_POST['backup_id']) ? (int)$_POST['backup_id'] : 0;
    
    if ($backupId <= 0) {
        $message = t('backup_invalid_selection', 'Geçersiz yedek seçimi.');
        $status = 'error';
    } else {
        try {
            // Get backup file
            $db->query("SELECT * FROM backups WHERE id = :id");
            $db->bind(':id', $backupId);
            $backup = $db->single();
            
            if (!$backup) {
                throw new Exception(t('backup_not_found', 'Seçilen yedek bulunamadı.'));
            }
            
            $backupFilePath = BACKUP_PATH . $backup['filename'];
            
            // Check if file exists
            if (!file_exists($backupFilePath)) {
                throw new Exception(t('backup_file_not_found', 'Yedek dosyası bulunamadı:') . ' ' . $backup['filename']);
            }
            
            // Extract if it's a zip file
            $isSql = true;
            if (pathinfo($backupFilePath, PATHINFO_EXTENSION) === 'zip') {
                $isSql = false;
                $zip = new ZipArchive();
                if ($zip->open($backupFilePath) === TRUE) {
                    // Extract to a temporary file
                    $tempFile = BACKUP_PATH . 'temp_' . time() . '.sql';
                    
                    // Extract first SQL file
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                            $fp = fopen($tempFile, 'w');
                            fwrite($fp, $zip->getFromIndex($i));
                            fclose($fp);
                            $isSql = true;
                            break;
                        }
                    }
                    
                    $zip->close();
                    $backupFilePath = $tempFile;
                } else {
                    throw new Exception(t('backup_zip_error', 'ZIP dosyası açılamadı.'));
                }
            }
            
            if (!$isSql) {
                throw new Exception(t('backup_invalid_sql', 'Geçerli bir SQL yedek dosyası bulunamadı.'));
            }
            
            // Get MySQL connection details from config
            $config = require CONFIG_PATH . 'database.php';
            $host = $config['host'];
            $database = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Build mysql command to restore
            $command = "mysql --host={$host} --user={$username} ";
            
            if (!empty($password)) {
                $command .= "--password={$password} ";
            }
            
            $command .= "{$database} < {$backupFilePath}";
            
            // Execute command
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            // Remove temporary file if exists
            if (strpos($backupFilePath, 'temp_') !== false && file_exists($backupFilePath)) {
                unlink($backupFilePath);
            }
            
            if ($returnVar !== 0) {
                throw new Exception("Geri yükleme işlemi başarısız oldu: " . implode("\n", $output));
            }
            
            // Log restoration
            $db->query("INSERT INTO backup_logs (backup_id, action, details, created_at, user_id) 
                       VALUES (:backup_id, 'restore', :details, NOW(), :user_id)");
            $db->bind(':backup_id', $backupId);
            $db->bind(':details', t('backup_restore_success', 'Yedek başarıyla geri yüklendi'));
            $db->bind(':user_id', Session::get('user')['id']);
            $db->execute();
            
            $message = t('backup_restore_success', 'Yedek başarıyla geri yüklendi.');
            $status = 'success';
            
        } catch (Exception $e) {
            $message = t('backup_restore_error', 'Yedek geri yüklenirken hata oluştu:') . ' ' . $e->getMessage();
            $status = 'error';
        }
    }
}

// Process delete backup action
if (isset($_POST['delete_backup'])) {
    // Validate CSRF token
    if (!validateCsrf()) {
        redirect('index.php?module=tools&action=backup');
    }
    
    $backupId = isset($_POST['backup_id']) ? (int)$_POST['backup_id'] : 0;
    
    if ($backupId <= 0) {
        $message = t('backup_invalid_selection', 'Geçersiz yedek seçimi.');
        $status = 'error';
    } else {
        try {
            // Get backup file
            $db->query("SELECT * FROM backups WHERE id = :id");
            $db->bind(':id', $backupId);
            $backup = $db->single();
            
            if (!$backup) {
                throw new Exception(t('backup_not_found', 'Seçilen yedek bulunamadı.'));
            }
            
            $backupFilePath = BACKUP_PATH . $backup['filename'];
            
            // Delete file if exists
            if (file_exists($backupFilePath)) {
                unlink($backupFilePath);
            }
            
            // Delete from database
            $db->query("DELETE FROM backups WHERE id = :id");
            $db->bind(':id', $backupId);
            $db->execute();
            
            $message = t('backup_delete_success', 'Yedek başarıyla silindi.');
            $status = 'success';
            
        } catch (Exception $e) {
            $message = t('backup_delete_error', 'Yedek silinirken hata oluştu:') . ' ' . $e->getMessage();
            $status = 'error';
        }
    }
}

// Process download backup action
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $backupId = (int)$_GET['download'];
    
    // Get backup file
    $db->query("SELECT * FROM backups WHERE id = :id");
    $db->bind(':id', $backupId);
    $backup = $db->single();
    
    if ($backup) {
        $backupFilePath = BACKUP_PATH . $backup['filename'];
        
        if (file_exists($backupFilePath)) {
            // Log download
            $db->query("INSERT INTO backup_logs (backup_id, action, details, created_at, user_id) 
                       VALUES (:backup_id, 'download', :details, NOW(), :user_id)");
            $db->bind(':backup_id', $backupId);
            $db->bind(':details', t('backup_download', 'Yedek indirildi'));
            $db->bind(':user_id', Session::get('user')['id']);
            $db->execute();
            
            // Set headers and start download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backupFilePath));
            readfile($backupFilePath);
            exit;
        }
    }
    
    // If download fails, redirect back with error
    Session::setFlash('error', t('backup_download_error', 'Yedek dosyası bulunamadı.'));
    redirect('index.php?module=tools&action=backup');
}

// Get all backups
$db->query("SELECT b.*, u.name as user_name, u.surname as user_surname 
           FROM backups b 
           LEFT JOIN users u ON b.created_by = u.id 
           ORDER BY b.created_at DESC");
$backups = $db->resultSet();

// Get database size
$db->query("SELECT 
           ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
           FROM information_schema.TABLES 
           WHERE table_schema = DATABASE()");
$dbSize = $db->single()['size'];

// Get table count
$db->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE table_schema = DATABASE()");
$tableCount = $db->single()['count'];

// Check if mysqldump is available
$mysqldumpAvailable = false;
$mysqldumpPath = '';
$output = [];
$returnVar = 0;

// Laragon paths - dynamically find MySQL version
$laragonBase = 'C:\\laragon\\bin\\mysql';
$laragonPaths = [];
if (is_dir($laragonBase)) {
    $mysqlDirs = glob($laragonBase . '\\mysql*', GLOB_ONLYDIR);
    foreach ($mysqlDirs as $mysqlDir) {
        $laragonPaths[] = $mysqlDir . '\\bin\\mysqldump.exe';
    }
}

// Check common paths
$possiblePaths = array_merge(
    $laragonPaths,
    [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
        'C:\\wamp\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        '/usr/local/mysql/bin/mysqldump',
        '/opt/mysql/bin/mysqldump'
    ]
);

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $mysqldumpPath = $path;
        $mysqldumpAvailable = true;
        break;
    }
}

// If not found in common paths, try to find in PATH
if (!$mysqldumpAvailable) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('where mysqldump 2>nul', $output, $returnVar);
        if ($returnVar === 0 && !empty($output[0])) {
            $mysqldumpPath = trim($output[0]);
            $mysqldumpAvailable = true;
        }
    } else {
        exec('which mysqldump', $output, $returnVar);
        if ($returnVar === 0 && !empty($output[0])) {
            $mysqldumpPath = trim($output[0]);
            $mysqldumpAvailable = true;
        }
    }
}

// Include header
include_once INCLUDES_PATH . 'header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title"><?php echo t('backup_title', 'Sistem Yedekleme'); ?></h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>"><?php echo t('home', 'Ana Sayfa'); ?></a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('index.php?module=tools'); ?>"><?php echo t('tools_title', 'Araçlar'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo t('backup_title', 'Sistem Yedekleme'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Display Message -->
<?php if ($message): ?>
<div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Backup Management Card -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('backup_list', 'Yedekler'); ?></h5>
            </div>
            <div class="card-body">
                <?php if (count($backups) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><?php echo t('backup_id', 'ID'); ?></th>
                                <th><?php echo t('backup_filename', 'Dosya Adı'); ?></th>
                                <th><?php echo t('backup_type', 'Tür'); ?></th>
                                <th><?php echo t('backup_size', 'Boyut'); ?></th>
                                <th><?php echo t('backup_created_at', 'Oluşturma Tarihi'); ?></th>
                                <th><?php echo t('backup_created_by', 'Oluşturan'); ?></th>
                                <th><?php echo t('backup_actions', 'İşlemler'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><?php echo $backup['id']; ?></td>
                                <td><?php echo e($backup['filename']); ?></td>
                                <td>
                                    <?php
                                    switch ($backup['type']) {
                                        case 'full':
                                            echo '<span class="badge bg-primary">' . t('backup_type_full', 'Tam Yedek') . '</span>';
                                            break;
                                        case 'structure':
                                            echo '<span class="badge bg-info">' . t('backup_type_structure', 'Yapı') . '</span>';
                                            break;
                                        case 'data':
                                            echo '<span class="badge bg-success">' . t('backup_type_data', 'Veri') . '</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">' . t('backup_type_unknown', 'Bilinmiyor') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo formatSize($backup['size']); ?></td>
                                <td><?php echo formatDateTime($backup['created_at']); ?></td>
                                <td><?php echo e($backup['user_name'] . ' ' . $backup['user_surname']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo url('index.php?module=tools&action=backup&download=' . $backup['id']); ?>" class="btn btn-sm btn-primary" title="<?php echo t('backup_download', 'İndir'); ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-warning restore-backup" data-id="<?php echo $backup['id']; ?>" data-bs-toggle="modal" data-bs-target="#restoreModal" title="<?php echo t('backup_restore', 'Geri Yükle'); ?>">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-backup" data-id="<?php echo $backup['id']; ?>" data-bs-toggle="modal" data-bs-target="#deleteModal" title="<?php echo t('backup_delete', 'Sil'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> <?php echo t('backup_no_backups', 'Henüz hiç yedek oluşturulmamış.'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('backup_create_new', 'Yeni Yedek Oluştur'); ?></h5>
            </div>
            <div class="card-body">
                <?php if (!$mysqldumpAvailable): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong><?php echo t('backup_info', 'Bilgi:'); ?></strong> <?php echo t('backup_mysqldump_not_found', 'mysqldump bulunamadı, PHP tabanlı yedekleme kullanılacak. Bu yöntem de güvenilir ve tüm verilerinizi yedekleyecektir.'); ?>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong><?php echo t('backup_mysqldump_found', 'mysqldump bulundu:'); ?></strong> <?php echo e($mysqldumpPath); ?>
                </div>
                <?php endif; ?>
                
                <form action="<?php echo url('index.php?module=tools&action=backup'); ?>" method="post">
                    <?php echo csrfField(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label"><?php echo t('backup_type_label', 'Yedek Türü:'); ?></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" id="backup_full" value="full" checked>
                            <label class="form-check-label" for="backup_full">
                                <?php echo t('backup_type_full_desc', 'Tam Yedek (Yapı + Veri)'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" id="backup_structure" value="structure">
                            <label class="form-check-label" for="backup_structure">
                                <?php echo t('backup_type_structure_desc', 'Sadece Yapı (Tablolar)'); ?>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" id="backup_data" value="data">
                            <label class="form-check-label" for="backup_data">
                                <?php echo t('backup_type_data_desc', 'Sadece Veri'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" id="include_data" name="include_data" checked>
                        <label class="form-check-label" for="include_data">
                            <?php echo t('backup_compress', 'Yedek tamamlandığında sıkıştır'); ?>
                        </label>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong><?php echo t('backup_info', 'Bilgi:'); ?></strong> <?php echo t('backup_info_text', 'Yedekleme işlemi veritabanı boyutuna bağlı olarak zaman alabilir.'); ?>
                    </div>
                    
                    <button type="submit" name="create_backup" class="btn btn-success">
                        <i class="fas fa-database me-1"></i> <?php echo t('backup_create_button', 'Yedek Oluştur'); ?>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title"><?php echo t('backup_database_info', 'Veritabanı Bilgileri'); ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="border rounded p-3 text-center">
                            <h4 class="m-0"><?php echo $dbSize; ?> MB</h4>
                            <p class="text-muted mb-0"><?php echo t('backup_database_size', 'Veritabanı Boyutu'); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3 text-center">
                            <h4 class="m-0"><?php echo $tableCount; ?></h4>
                            <p class="text-muted mb-0"><?php echo t('backup_table_count', 'Tablo Sayısı'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?php echo t('backup_warning', 'Uyarı:'); ?></strong> <?php echo t('backup_warning_text', 'Veritabanı geri yükleme işlemi mevcut verilerin üzerine yazılmasına neden olur. Önemli verilerinizi düzenli olarak yedekleyin.'); ?>
                </div>
                
                <div class="alert alert-info">
                    <h6 class="alert-heading"><?php echo t('backup_recommendations', 'Yedekleme Önerileri'); ?></h6>
                    <ul class="mb-0">
                        <li><?php echo t('backup_recommendation1', 'Büyük sistem değişikliklerinden önce'); ?></li>
                        <li><?php echo t('backup_recommendation2', 'Düzenli olarak (günlük/haftalık)'); ?></li>
                        <li><?php echo t('backup_recommendation3', 'Önemli veri ekleme/silme işlemlerinden önce'); ?></li>
                        <li><?php echo t('backup_recommendation4', 'Sistem güncellemelerinden önce'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Backup Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="restoreModalLabel"><?php echo t('backup_restore_modal_title', 'Yedeği Geri Yükle'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?php echo t('backup_restore_warning', 'Dikkat!'); ?></strong> <?php echo t('backup_restore_warning_text', 'Bu işlem mevcut tüm verilerin üzerine yazılmasına neden olacaktır ve geri alınamaz!'); ?>
                </div>
                <p><?php echo t('backup_restore_confirm', 'Seçilen yedeği geri yüklemek istediğinizden emin misiniz?'); ?></p>
                <form id="restoreForm" action="<?php echo url('index.php?module=tools&action=backup'); ?>" method="post">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="backup_id" id="restore_backup_id" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                <button type="button" class="btn btn-warning" id="confirmRestore"><?php echo t('backup_restore_button', 'Geri Yükle'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Backup Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><?php echo t('backup_delete_modal_title', 'Yedeği Sil'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?php echo t('backup_delete_warning', 'Uyarı!'); ?></strong> <?php echo t('backup_delete_warning_text', 'Bu işlem yedek dosyasını tamamen silecektir ve geri alınamaz!'); ?>
                </div>
                <p><?php echo t('backup_delete_confirm', 'Seçilen yedeği silmek istediğinizden emin misiniz?'); ?></p>
                <form id="deleteForm" action="<?php echo url('index.php?module=tools&action=backup'); ?>" method="post">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="backup_id" id="delete_backup_id" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel', 'İptal'); ?></button>
                <button type="button" class="btn btn-danger" id="confirmDelete"><?php echo t('backup_delete_button', 'Sil'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Set backup ID for restore
        $('.restore-backup').on('click', function() {
            const backupId = $(this).data('id');
            $('#restore_backup_id').val(backupId);
        });
        
        // Confirm restore action
        $('#confirmRestore').on('click', function() {
            $('#restoreForm').append('<input type="hidden" name="restore_backup" value="1">').submit();
        });
        
        // Set backup ID for delete
        $('.delete-backup').on('click', function() {
            const backupId = $(this).data('id');
            $('#delete_backup_id').val(backupId);
        });
        
        // Confirm delete action
        $('#confirmDelete').on('click', function() {
            $('#deleteForm').append('<input type="hidden" name="delete_backup" value="1">').submit();
        });
    });
</script>

<?php
// Include footer
include_once INCLUDES_PATH . 'footer.php';
?>