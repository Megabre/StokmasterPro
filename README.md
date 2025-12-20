# StokMaster Pro v1.1.3

**Professional Stock and Inventory Management System**

StokMaster Pro is a comprehensive web-based management system that enables businesses to easily track stock, products, customers, and orders. With its modern interface, powerful features, and flexible structure, it is an ideal solution for small and medium-sized businesses.

For detailed information and features, please visit: **[https://www.megabre.com/stokmaster-pro.php](https://www.megabre.com/stokmaster-pro.php)**

---

## Features

### General Features
- Modern and responsive Tabler.io based interface
- Multi-language support (Turkish/English)
- Role-based authorization system
- Detailed activity logging (audit trail)
- Dynamic field system (products, categories, customers, stock)
- Advanced reporting and analytics
- Excel/CSV import/export
- Automatic backup system
- Database optimization tools
- Multi-currency support
- Customer tagging system
- Measurement unit management

### Technical Features
- PHP 7.4+ compatible
- MySQL/MariaDB database
- Secure database access with PDO
- CSRF protection
- XSS protection
- SQL injection protection
- Session management
- Cache system

---

## System Requirements

### Server Requirements
- **PHP:** 7.4 or higher
- **MySQL/MariaDB:** 5.7 or higher
- **Web Server:** Apache 2.4+ or Nginx
- **PHP Extensions:**
  - PDO
  - PDO_MySQL
  - GD or Imagick (for image processing)
  - mbstring
  - json
  - session
  - fileinfo

---

## Installation

### 1. Upload Files

```bash
# Clone the project or extract the ZIP file
cd /path/to/your/web/directory
```

### 2. Database Setup

1. Create a new database in MySQL/MariaDB:
```sql
CREATE DATABASE stok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the `stok.sql` file:
```bash
mysql -u username -p stok < stok.sql
```

Or import the `stok.sql` file via phpMyAdmin.

### 3. Configuration

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'stok');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');
```

Edit BASE_URL in `config/config.php`:

```php
define('APP_URL', 'http://yourdomain.com/stok');
```

### 4. Folder Permissions

The following folders must be writable:

```bash
chmod 755 uploads/
chmod 755 uploads/company/
chmod 755 uploads/products/
chmod 755 uploads/profile/
chmod 755 uploads/import/
chmod 755 backup/
chmod 755 cache/
```

### 5. First Login

Default admin credentials:
- **Username:** admin
- **Password:** admin123

**Security Warning:** Please change your password after first login!

---

## Modules

### 1. Dashboard
- General statistics (total products, customers, orders, stock)
- Quick actions menu
- Monthly payments and debts chart
- Product categories distribution
- Recent transactions list
- Customizable widget visibility

### 2. Products
- Product CRUD operations
- Category-based product management
- SKU and barcode management
- Product pricing
- Minimum stock level tracking
- Product image upload
- Dynamic field support
- Bulk operations

### 3. Categories
- Category CRUD operations
- Category-based dynamic field definition
- Category statistics

### 4. Customers
- Customer CRUD operations
- Customer detail page
- Customer order history
- Customer financial history (payment/debt)
- Customer balance tracking
- Customer tagging system
- Dynamic field support

### 5. Stock
- Stock entry/exit operations
- Stock adjustment
- Stock movement history
- Product-based stock tracking
- Unit-based stock management

### 6. Orders
- Order creation
- Order editing and deletion
- Order status management (Pending, Processing, Completed, Cancelled)
- Order detail page
- Order printing
- VAT calculation

### 7. Financial Transactions
- Payment/debt/expense management
- Multi-currency support
- Payment methods (Cash, Check, Promissory Note, Credit Card, Wire Transfer/EFT)
- Customer-based financial history
- Cash summary

### 8. Tools
- **Reports:** Sales, stock, and customer reports
- **Calculators:** Unit conversions, price calculations, VAT calculations
- **Database Optimization:** Table optimization, fragmentation analysis, cache clearing
- **Backup:** Automatic and manual database backup, restore
- **Import/Export:** Excel/CSV import/export for all modules
- **Cache Management:** Cache clearing and statistics

### 9. Settings
- System settings (company information, logo, timezone, date format)
- User management (roles: Admin, Manager, Accountant, Staff, Viewer)
- Inventory settings (low stock level, default unit, SKU settings)
- Currency management
- Customer tags
- Measurement units

### 10. Activity Log
- Detailed logging of all system activities
- User-based filtering
- Operation type filtering
- Date-based filtering
- Change details (old/new values)

### 11. Profile
- Profile information update
- Profile picture upload
- Password change
- Language preference
- Activity summary

---

## Configuration

### Database Settings
Edit database connection information in `config/database.php`.

### Application Settings
In `config/config.php`:
- `APP_NAME`: Application name
- `APP_VERSION`: Version number
- `APP_URL`: Application URL
- `APP_TIMEZONE`: Timezone

### Language Settings
- `lang/tr.php`: Turkish translations
- `lang/en.php`: English translations

To add a new language, add a new PHP file to the `lang/` folder.

---

## Security

### Security Features
- ✅ CSRF token protection
- ✅ XSS protection (htmlspecialchars)
- ✅ SQL injection protection (PDO prepared statements)
- ✅ Session security
- ✅ Password hashing (password_hash)
- ✅ Role-based access control
- ✅ File upload validation
- ✅ Input sanitization

### Security Recommendations
1. Change admin password after first installation
2. Use strong passwords
3. Perform regular backups
4. Keep system updated
5. Use HTTPS (in production environment)
6. Check access permissions for `config/` folder

---

## Backup and Restore

### Automatic Backup
- Automatic backup can be performed from Settings > Tools > Backup menu
- Backups are saved to `backup/` folder

### Manual Backup
```bash
mysqldump -u username -p stok > backup_manual.sql
```

### Restore
1. Go to Settings > Tools > Backup
2. Select the backup to restore
3. Click "Restore" button

---

## Support

### Official Support
- **Website:** https://megabre.com
- **Email:** hello@megabre.com
- **Documentation:** [https://www.megabre.com/stokmaster-pro.php](https://www.megabre.com/stokmaster-pro.php)

---

## Version History

### v1.1.3 (Current)
- Tabler.io integration
- ApexCharts integration
- Detailed activity logging system
- Customer detail page
- Report printing improvements
- Database optimization module
- Version management system

### v1.0.0
- Initial release
- Basic modules
- User management
- Stock management

---

## License

This project is proprietary licensed. All rights reserved.

**© 2024 Megabre. All rights reserved.**

---

## Developer

**Ali Çömez / Slaweally**
- **Web:** https://megabre.com
- **Email:** hello@megabre.com

---

## Acknowledgments

- Tabler.io - Modern UI framework
- ApexCharts - Chart library
- Bootstrap - CSS framework
- jQuery - JavaScript library
- Select2 - Advanced select component
- DataTables - Table plugin

---

**Last Update:** 2025  
**Version:** 1.1.3
