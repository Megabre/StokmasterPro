-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 30, 2025 at 10:25 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stok`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` int NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('full','structure','data') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `size` bigint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `created_by` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backups`
--

INSERT INTO `backups` (`id`, `filename`, `type`, `size`, `created_at`, `created_by`) VALUES
(23, 'backup_2025-11-05_01-53-44.sql', 'full', 101594, '2025-11-05 01:53:44', 2),
(24, 'backup_2025-11-07_21-44-48.sql', 'full', 124400, '2025-11-07 21:44:48', 2),
(25, 'backup_2025-11-30_13-11-48.sql', 'full', 128876, '2025-11-30 13:11:48', 2);

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int NOT NULL,
  `backup_id` int NOT NULL,
  `action` enum('create','restore','delete','download') COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `user_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(10, 'Orman Ürünleri ', 'Tüm orman ürünleri Ağaç kalas kereste vb.', '2025-11-06 18:57:41', '2025-11-06 18:57:41'),
(11, 'Mobilya ürünleri ', 'Mobilyacılar için ürünler', '2025-11-06 18:58:01', '2025-11-06 18:58:01'),
(16, 'Hırdavat', 'Hırdavat ürünleri matkap şarjlı dekopaj vs gibi', '2025-11-07 21:42:02', '2025-11-30 13:20:23');

-- --------------------------------------------------------

--
-- Table structure for table `category_fields`
--

CREATE TABLE `category_fields` (
  `id` int NOT NULL,
  `field_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `field_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` enum('text','number','select','textarea','date') COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_options` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON formatında seçenek değerleri',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `field_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_fields`
--

INSERT INTO `category_fields` (`id`, `field_key`, `category_id`, `field_name`, `field_type`, `field_options`, `is_required`, `is_active`, `field_order`, `created_at`, `updated_at`) VALUES
(13, NULL, 11, 'Türü', 'select', '[\"Mdf\",\"Sunta\"]', 0, 1, 0, '2025-11-06 18:58:19', '2025-11-06 18:58:38'),
(14, NULL, 11, 'Rengi', 'text', '[]', 0, 1, 0, '2025-11-06 19:12:29', '2025-11-06 19:12:29'),
(20, NULL, 16, 'Türü', 'select', '[\"\\u015earjl\\u0131\",\"Elektrikli\"]', 0, 1, 0, '2025-11-07 21:42:02', '2025-11-07 21:42:02');

-- --------------------------------------------------------

--
-- Table structure for table `category_field_values`
--

CREATE TABLE `category_field_values` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int NOT NULL,
  `code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Para birimi kodu (TRY, USD, EUR vb.)',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Para birimi adı',
  `prefix` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Önek (₺, $, €)',
  `suffix` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sonek (TL, USD, EUR)',
  `format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '1234.56' COMMENT 'Biçim (1234.56 veya 1,234.56)',
  `base_rate` decimal(15,5) NOT NULL DEFAULT '1.00000' COMMENT 'Baz dönüşüm oranı (TRY baz alınarak)',
  `decimal_places` int NOT NULL DEFAULT '2' COMMENT 'Ondalık basamak sayısı',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Varsayılan para birimi',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Para birimleri';

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `name`, `prefix`, `suffix`, `format`, `base_rate`, `decimal_places`, `is_active`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'TRY', 'Türk Lirası', '₺', 'TL', '1234.56', 1.00000, 2, 1, 1, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(2, 'USD', 'Amerikan Doları', '$', 'USD', '1,234.56', 42.08650, 2, 1, 0, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(3, 'EUR', 'Euro', '€', 'EUR', '1,234.56', 45.25000, 2, 1, 0, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(4, 'GBP', 'İngiliz Sterlini', '£', 'GBP', '1,234.56', 52.35000, 2, 1, 0, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(13, 'M3', 'Metreküp', 'm³', 'M3', '1234.56', 9500.00000, 2, 1, 0, '2025-11-05 23:32:41', '2025-11-05 23:32:55');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tag_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Etiket ID''leri (virgülle ayrılmış, cache için)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `first_name`, `last_name`, `phone`, `email`, `company`, `address`, `notes`, `created_at`, `created_by`, `updated_at`, `tag_ids`) VALUES
(14, 'Ali', 'Hoca', '5325323232', 'ali@mail.com', 'Muhtar hoca ', 'Türkiye Trabzon', 'Bu adam iyi adam sorunsuz ticaret yapılır', '2025-11-07 19:20:05', NULL, '2025-11-07 19:20:05', '4'),
(15, 'Şenol', 'Küçük', '525323232', 'senol@mail.com', 'Senol Ticareti', 'Trabzon ortasar', 'Bu adam VIP muşteri', '2025-11-07 19:21:08', NULL, '2025-11-07 19:21:08', '2');

-- --------------------------------------------------------

--
-- Table structure for table `customer_fields`
--

CREATE TABLE `customer_fields` (
  `id` int NOT NULL,
  `field_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `field_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` enum('text','number','select','textarea','date') COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_options` text COLLATE utf8mb4_unicode_ci,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `field_order` int NOT NULL DEFAULT '0',
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_field_values`
--

CREATE TABLE `customer_field_values` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_tags`
--

CREATE TABLE `customer_tags` (
  `id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Etiket adı (örn: Kırmızı, VIP, Özel Müşteri)',
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#dc3545' COMMENT 'Etiket rengi (hex)',
  `discount_percentage` decimal(5,2) DEFAULT '0.00' COMMENT 'Varsayılan indirim yüzdesi',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Etiket açıklaması',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Müşteri etiketleri';

--
-- Dumping data for table `customer_tags`
--

INSERT INTO `customer_tags` (`id`, `name`, `color`, `discount_percentage`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Kırmızı', '#dc3545', 5.00, 'Kırmızı etiketli müşterilere %5 indirim uygulanır', 1, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(2, 'VIP', '#6f42c1', 10.00, 'VIP müşterilere %10 indirim uygulanır', 1, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(3, 'Özel Müşteri', '#fd7e14', 15.00, 'Özel müşterilere %15 indirim uygulanır', 1, '2025-11-05 22:36:29', '2025-11-05 22:36:29'),
(4, 'Standart', '#6c757d', 0.00, 'Standart müşteriler, indirim yok', 1, '2025-11-05 22:36:29', '2025-11-05 22:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `customer_tag_relations`
--

CREATE TABLE `customer_tag_relations` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `tag_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Müşteri-etiket ilişkileri';

--
-- Dumping data for table `customer_tag_relations`
--

INSERT INTO `customer_tag_relations` (`id`, `customer_id`, `tag_id`, `created_at`) VALUES
(1, 14, 4, '2025-11-07 19:20:05'),
(2, 15, 2, '2025-11-07 19:21:08');

-- --------------------------------------------------------

--
-- Table structure for table `dynamic_fields`
--

CREATE TABLE `dynamic_fields` (
  `id` int NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `field_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `field_label` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'text',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `options` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Gider kategorisi (Elektrik, Su, Kira, vb.)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Gider açıklaması',
  `amount` decimal(10,2) NOT NULL COMMENT 'Gider tutarı',
  `date` date NOT NULL COMMENT 'Gider tarihi',
  `payment_method` enum('cash','check','promissory_note','credit_card','bank_transfer') COLLATE utf8mb4_unicode_ci DEFAULT 'cash' COMMENT 'Ödeme yöntemi',
  `reference_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Referans/Belge no',
  `supplier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tedarikçi/Firma adı',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Ek notlar',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'Oluşturan kullanıcı ID',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dış giderler tablosu';

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `date`, `payment_method`, `reference_no`, `supplier`, `notes`, `created_at`, `created_by`, `updated_at`) VALUES
(1, 'Elektrik', 'Elektrik faturası ödedim', 560.00, '2025-11-01', 'cash', '146161', 'Tedaş', 'Not alanını test ettim', '2025-11-01 23:59:09', 2, '2025-11-01 23:59:09');

-- --------------------------------------------------------

--
-- Table structure for table `import_export_logs`
--

CREATE TABLE `import_export_logs` (
  `id` int NOT NULL,
  `type` enum('import','export') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `user_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

CREATE TABLE `installments` (
  `id` int NOT NULL,
  `transaction_id` int NOT NULL,
  `installment_no` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `paid_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` int NOT NULL,
  `code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `code`, `name`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'tr', 'Türkçe', 1, 1, '2025-05-24 18:51:34', '2025-05-24 18:51:34'),
(2, 'en', 'English', 0, 1, '2025-05-24 18:51:34', '2025-05-24 18:51:34');

-- --------------------------------------------------------

--
-- Table structure for table `language_translations`
--

CREATE TABLE `language_translations` (
  `id` int NOT NULL,
  `language_id` int NOT NULL,
  `translation_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translation_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(1, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-24 22:12:52'),
(2, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-24 22:12:58'),
(3, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-24 22:24:15'),
(4, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 00:45:39'),
(5, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 01:11:59'),
(6, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 01:14:32'),
(7, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 01:14:37'),
(8, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 01:22:06'),
(9, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 01:23:07'),
(10, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:12:29'),
(11, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:12:35'),
(12, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:12:41'),
(13, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:12:56'),
(14, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:14:14'),
(15, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:14:18'),
(16, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:14:39'),
(17, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:14:40'),
(18, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:14:43'),
(19, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:14:47'),
(20, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:15:21'),
(21, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:15:22'),
(22, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:15:29'),
(23, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 11:16:18'),
(24, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 11:17:47'),
(25, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 11:17:58'),
(26, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 11:18:23'),
(27, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 17:35:24'),
(28, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 17:35:28'),
(29, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-25 17:35:51'),
(30, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 17:35:56'),
(31, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 18:37:50'),
(32, 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-25 21:15:30'),
(33, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-26 02:07:44'),
(34, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-26 12:01:19'),
(35, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'success', '2025-05-26 12:51:02'),
(36, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'success', '2025-05-26 12:55:13'),
(37, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-26 12:58:52'),
(38, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-26 12:58:57'),
(39, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-26 18:14:06'),
(40, 2, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-26 18:43:14'),
(41, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 22:45:17'),
(42, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 22:45:23'),
(43, 1, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'success', '2025-05-26 22:45:29'),
(44, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:04:21'),
(45, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:04:26'),
(46, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:04:31'),
(47, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:04:34'),
(48, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:04:37'),
(49, 1, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'success', '2025-05-26 23:04:43'),
(50, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:04:59'),
(51, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:02'),
(52, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:06'),
(53, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:09'),
(54, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:12'),
(55, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:15'),
(56, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:17'),
(57, 1, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'success', '2025-05-26 23:05:23'),
(58, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:05:47'),
(59, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:06:28'),
(60, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:06:30'),
(61, NULL, '194.146.159.147', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-26 23:06:39'),
(62, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-27 01:52:11'),
(63, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-27 01:52:20'),
(64, NULL, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'failed', '2025-05-27 01:52:31'),
(65, 1, '88.230.250.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'success', '2025-05-27 01:52:38'),
(66, NULL, '78.190.134.54', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'failed', '2025-05-30 20:48:19'),
(67, 1, '78.190.134.54', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', 'success', '2025-05-30 20:48:24'),
(68, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'failed', '2025-10-31 00:21:36'),
(69, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'failed', '2025-10-31 00:21:44'),
(70, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'failed', '2025-10-31 00:22:14'),
(71, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'failed', '2025-10-31 00:22:20'),
(72, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'failed', '2025-10-31 00:22:38'),
(73, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-10-31 00:26:15'),
(74, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-10-31 00:52:23'),
(75, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-10-31 00:59:23'),
(76, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-10-31 01:04:21'),
(77, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-10-31 01:10:02'),
(78, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 00:36:27'),
(79, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 00:40:17'),
(80, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 00:46:51'),
(81, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 00:52:29'),
(82, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 00:55:40'),
(83, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 01:02:06'),
(84, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-01 23:45:05'),
(85, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-02 00:49:41'),
(86, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-02 01:03:11'),
(87, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 00:06:16'),
(88, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 01:31:03'),
(89, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 19:09:27'),
(90, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 22:44:05'),
(91, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 23:14:56'),
(92, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 23:18:46'),
(93, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 23:25:20'),
(94, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-05 23:42:00'),
(95, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 00:06:18'),
(96, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 19:05:04'),
(97, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 19:27:04'),
(98, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 20:29:56'),
(99, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 20:37:42'),
(100, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 20:44:00'),
(101, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 23:14:38'),
(102, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-06 23:50:41'),
(103, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 00:09:44'),
(104, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 00:13:35'),
(105, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 19:16:42'),
(106, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 19:41:52'),
(107, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 19:44:27'),
(108, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 20:19:19'),
(109, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 21:39:05'),
(110, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 21:47:13'),
(111, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-07 21:53:31'),
(112, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-14 19:45:15'),
(113, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', 'success', '2025-11-14 19:45:24'),
(114, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-17 18:55:08'),
(115, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-18 00:26:24'),
(116, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-18 13:32:12'),
(117, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-18 13:35:15'),
(118, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-21 17:53:37'),
(119, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-29 23:31:36'),
(120, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-30 12:48:25'),
(121, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-30 12:56:24'),
(122, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-30 12:59:20'),
(123, 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:145.0) Gecko/20100101 Firefox/145.0', 'success', '2025-11-30 13:03:47');

-- --------------------------------------------------------

--
-- Table structure for table `measurement_units`
--

CREATE TABLE `measurement_units` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `measurement_units`
--

INSERT INTO `measurement_units` (`id`, `name`, `symbol`, `created_at`, `updated_at`) VALUES
(1, 'Adet', 'ad', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(2, 'Kilogram', 'kg', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(3, 'Gram', 'g', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(4, 'Litre', 'lt', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(5, 'Metre', 'm', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(6, 'Paket', 'pk', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(7, 'Kutu', 'kt', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(8, 'Çift', 'çf', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(9, 'Düzine', 'dz', '2025-05-25 03:38:12', '2025-05-25 03:38:12'),
(10, 'Koli', 'kl', '2025-05-25 03:38:12', '2025-05-25 03:38:12');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `order_date` date NOT NULL,
  `status` enum('pending','processing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat_rate` decimal(5,2) DEFAULT NULL,
  `vat_amount` decimal(10,2) DEFAULT NULL,
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `currency_id` int DEFAULT NULL COMMENT 'Sipariş para birimi',
  `discount_type` enum('none','percentage','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'none' COMMENT 'İndirim tipi',
  `discount_value` decimal(10,2) DEFAULT '0.00' COMMENT 'İndirim değeri',
  `discount_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'İndirim tutarı',
  `applied_tag_id` int DEFAULT NULL COMMENT 'Uygulanan müşteri etiketi ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_date`, `status`, `notes`, `total_amount`, `vat_rate`, `vat_amount`, `grand_total`, `created_at`, `created_by`, `updated_at`, `currency_id`, `discount_type`, `discount_value`, `discount_amount`, `applied_tag_id`) VALUES
(12, 15, '2025-11-07', 'completed', 'Bu sipairşler 2 gün içinde teslim edilmesi gerekiyor - Acil lazım olan sipariş', 113140.00, 0.00, 0.00, 101826.00, '2025-11-07 19:33:05', NULL, '2025-11-07 21:39:53', NULL, 'percentage', 10.00, 11314.00, 2),
(13, 14, '2025-11-18', 'pending', 'Kargo ile gönderildi - Gönderildi', 3000.00, 0.00, 0.00, 3000.00, '2025-11-18 00:28:37', NULL, '2025-11-18 00:29:07', NULL, 'none', 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_history`
--

CREATE TABLE `order_history` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `status` enum('pending','processing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `currency_id` int DEFAULT NULL COMMENT 'Kalem para birimi',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Satış birimi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `notes`, `created_at`, `updated_at`, `currency_id`, `unit`) VALUES
(20, 12, 27, 500.00, 145.00, 72500.00, NULL, '2025-11-07 19:33:05', '2025-11-07 19:33:05', NULL, NULL),
(21, 12, 28, 5.00, 4064.00, 20320.00, NULL, '2025-11-07 19:33:05', '2025-11-07 19:33:05', NULL, NULL),
(22, 12, 29, 5.00, 4064.00, 20320.00, NULL, '2025-11-07 19:33:05', '2025-11-07 19:33:05', NULL, NULL),
(23, 13, 30, 12.00, 250.00, 3000.00, NULL, '2025-11-18 00:28:37', '2025-11-18 00:28:37', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sku` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_stock_level` int DEFAULT '0',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `currency_id` int DEFAULT NULL COMMENT 'Para birimi ID (varsayılan para birimi kullanılacaksa NULL)',
  `price_unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'adet' COMMENT 'Fiyat birimi (adet, kg, gram, lt vb.)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `price`, `sku`, `barcode`, `description`, `min_stock_level`, `image`, `created_at`, `updated_at`, `currency_id`, `price_unit`) VALUES
(27, 10, '3Metre 5X10', 145.00, 'PRD-KME8JR', '', '', 50, '690cc8588ccbe.png', '2025-11-06 19:09:02', '2025-11-06 19:10:00', NULL, 'adet'),
(28, 11, 'Beyaz 1 Tabak MDF', 4064.00, 'PRD-FGAJRL', '', '1 Tabak ebatlanmamış Beyaz MDF ', 10, '690cc9c69da30.jpg', '2025-11-06 19:12:11', '2025-11-06 19:36:21', NULL, 'adet'),
(29, 11, 'Krem 1 Tabak MDF ', 5000.00, 'PRD-GYT7Y1', '', 'Krem rengi', 10, '690ccecf63a4b.jpg', '2025-11-06 19:37:35', '2025-11-17 22:35:31', NULL, 'adet'),
(30, 16, 'Kerpeten', 250.00, 'PRD-2F9Y1F', '', 'İzeltaş Kerpeten', 20, '691b9355871b4.jpg', '2025-11-18 00:27:49', '2025-11-18 00:27:49', NULL, 'adet');

-- --------------------------------------------------------

--
-- Table structure for table `product_fields`
--

CREATE TABLE `product_fields` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `placeholder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `options` text COLLATE utf8mb4_unicode_ci,
  `order` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_fields_backup`
--

CREATE TABLE `product_fields_backup` (
  `id` int NOT NULL DEFAULT '0',
  `product_id` int NOT NULL,
  `field_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` enum('text','number','select','textarea','date') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_fields_backup`
--

INSERT INTO `product_fields_backup` (`id`, `product_id`, `field_name`, `field_type`, `field_value`, `created_at`, `updated_at`) VALUES
(0, 26, 'En', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Boy', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Kalınlık', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Türü', 'select', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'En', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Boy', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Kalınlık', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Türü', 'select', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'En', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Boy', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Kalınlık', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Türü', 'select', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'En', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Boy', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Kalınlık', 'number', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 26, 'Türü', 'select', '', '2025-11-02 00:25:58', '2025-11-02 00:25:58'),
(0, 28, 'Türü', 'select', 'Mdf', '2025-11-06 19:36:21', '2025-11-06 19:36:21'),
(0, 28, 'Rengi', 'text', 'Beyaz', '2025-11-06 19:36:21', '2025-11-06 19:36:21'),
(0, 29, 'Türü', 'select', '', '2025-11-17 22:35:31', '2025-11-17 22:35:31'),
(0, 29, 'Rengi', 'text', '', '2025-11-17 22:35:31', '2025-11-17 22:35:31'),
(0, 29, 'Kalınlık', 'text', '1.8mm', '2025-11-17 22:35:31', '2025-11-17 22:35:31'),
(0, 30, 'Türü', 'select', '', '2025-11-18 00:27:49', '2025-11-18 00:27:49');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:44:55', '2025-05-25 03:44:55'),
(2, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:44:55', '2025-05-25 03:44:55'),
(3, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:44:55', '2025-05-25 03:44:55'),
(4, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:44:55', '2025-05-25 03:44:55'),
(5, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:44:55', '2025-05-25 03:44:55'),
(6, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:45:00', '2025-05-25 03:45:00'),
(7, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:45:00', '2025-05-25 03:45:00'),
(8, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:45:00', '2025-05-25 03:45:00'),
(9, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:45:00', '2025-05-25 03:45:00'),
(10, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:45:00', '2025-05-25 03:45:00'),
(11, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:45:52', '2025-05-25 03:45:52'),
(12, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:45:52', '2025-05-25 03:45:52'),
(13, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:45:52', '2025-05-25 03:45:52'),
(14, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:45:52', '2025-05-25 03:45:52'),
(15, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:45:52', '2025-05-25 03:45:52'),
(16, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:46:19', '2025-05-25 03:46:19'),
(17, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:46:19', '2025-05-25 03:46:19'),
(18, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:46:19', '2025-05-25 03:46:19'),
(19, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:46:19', '2025-05-25 03:46:19'),
(20, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:46:19', '2025-05-25 03:46:19'),
(21, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(22, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(23, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(24, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(25, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(26, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(27, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(28, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(29, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(30, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:46:51', '2025-05-25 03:46:51'),
(31, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:47:46', '2025-05-25 03:47:46'),
(32, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:47:46', '2025-05-25 03:47:46'),
(33, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:47:46', '2025-05-25 03:47:46'),
(34, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:47:46', '2025-05-25 03:47:46'),
(35, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:47:46', '2025-05-25 03:47:46'),
(36, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:48:00', '2025-05-25 03:48:00'),
(37, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:48:00', '2025-05-25 03:48:00'),
(38, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:48:00', '2025-05-25 03:48:00'),
(39, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:48:00', '2025-05-25 03:48:00'),
(40, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:48:00', '2025-05-25 03:48:00'),
(41, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:48:20', '2025-05-25 03:48:20'),
(42, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:48:20', '2025-05-25 03:48:20'),
(43, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:48:20', '2025-05-25 03:48:20'),
(44, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:48:20', '2025-05-25 03:48:20'),
(45, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:48:20', '2025-05-25 03:48:20'),
(46, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:49:30', '2025-05-25 03:49:30'),
(47, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:49:30', '2025-05-25 03:49:30'),
(48, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:49:30', '2025-05-25 03:49:30'),
(49, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:49:30', '2025-05-25 03:49:30'),
(50, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:49:30', '2025-05-25 03:49:30'),
(51, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(52, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(53, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(54, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(55, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(56, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(57, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(58, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(59, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(60, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:49:43', '2025-05-25 03:49:43'),
(61, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:49:49', '2025-05-25 03:49:49'),
(62, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:49:49', '2025-05-25 03:49:49'),
(63, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:49:49', '2025-05-25 03:49:49'),
(64, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:49:49', '2025-05-25 03:49:49'),
(65, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:49:49', '2025-05-25 03:49:49'),
(66, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 03:49:50', '2025-05-25 03:49:50'),
(67, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 03:49:50', '2025-05-25 03:49:50'),
(68, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 03:49:50', '2025-05-25 03:49:50'),
(69, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 03:49:50', '2025-05-25 03:49:50'),
(70, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 03:49:50', '2025-05-25 03:49:50'),
(71, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 04:28:10', '2025-05-25 04:28:10'),
(72, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 04:28:10', '2025-05-25 04:28:10'),
(73, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 04:28:10', '2025-05-25 04:28:10'),
(74, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 04:28:10', '2025-05-25 04:28:10'),
(75, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 04:28:10', '2025-05-25 04:28:10'),
(76, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 18:37:55', '2025-05-25 18:37:55'),
(77, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 18:37:55', '2025-05-25 18:37:55'),
(78, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 18:37:55', '2025-05-25 18:37:55'),
(79, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 18:37:55', '2025-05-25 18:37:55'),
(80, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 18:37:55', '2025-05-25 18:37:55'),
(81, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 18:37:59', '2025-05-25 18:37:59'),
(82, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 18:37:59', '2025-05-25 18:37:59'),
(83, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 18:37:59', '2025-05-25 18:37:59'),
(84, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 18:37:59', '2025-05-25 18:37:59'),
(85, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 18:37:59', '2025-05-25 18:37:59'),
(86, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 18:38:05', '2025-05-25 18:38:05'),
(87, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 18:38:05', '2025-05-25 18:38:05'),
(88, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 18:38:05', '2025-05-25 18:38:05'),
(89, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 18:38:05', '2025-05-25 18:38:05'),
(90, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 18:38:05', '2025-05-25 18:38:05'),
(91, 'admin', 'Yönetici (Tam Yetki)', '2025-05-25 18:38:10', '2025-05-25 18:38:10'),
(92, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-25 18:38:10', '2025-05-25 18:38:10'),
(93, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-25 18:38:10', '2025-05-25 18:38:10'),
(94, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-25 18:38:10', '2025-05-25 18:38:10'),
(95, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-25 18:38:10', '2025-05-25 18:38:10'),
(96, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 01:41:29', '2025-05-26 01:41:29'),
(97, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 01:41:29', '2025-05-26 01:41:29'),
(98, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 01:41:29', '2025-05-26 01:41:29'),
(99, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 01:41:29', '2025-05-26 01:41:29'),
(100, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 01:41:29', '2025-05-26 01:41:29'),
(101, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 01:43:43', '2025-05-26 01:43:43'),
(102, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 01:43:43', '2025-05-26 01:43:43'),
(103, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 01:43:43', '2025-05-26 01:43:43'),
(104, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 01:43:43', '2025-05-26 01:43:43'),
(105, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 01:43:43', '2025-05-26 01:43:43'),
(106, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 01:43:47', '2025-05-26 01:43:47'),
(107, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 01:43:47', '2025-05-26 01:43:47'),
(108, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 01:43:47', '2025-05-26 01:43:47'),
(109, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 01:43:47', '2025-05-26 01:43:47'),
(110, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 01:43:47', '2025-05-26 01:43:47'),
(111, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 01:43:49', '2025-05-26 01:43:49'),
(112, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 01:43:49', '2025-05-26 01:43:49'),
(113, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 01:43:49', '2025-05-26 01:43:49'),
(114, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 01:43:49', '2025-05-26 01:43:49'),
(115, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 01:43:49', '2025-05-26 01:43:49'),
(116, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 12:31:21', '2025-05-26 12:31:21'),
(117, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 12:31:21', '2025-05-26 12:31:21'),
(118, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 12:31:21', '2025-05-26 12:31:21'),
(119, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 12:31:21', '2025-05-26 12:31:21'),
(120, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 12:31:21', '2025-05-26 12:31:21'),
(121, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 12:31:25', '2025-05-26 12:31:25'),
(122, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 12:31:25', '2025-05-26 12:31:25'),
(123, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 12:31:25', '2025-05-26 12:31:25'),
(124, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 12:31:25', '2025-05-26 12:31:25'),
(125, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 12:31:25', '2025-05-26 12:31:25'),
(126, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(127, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(128, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(129, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(130, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(131, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(132, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(133, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(134, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(135, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 12:31:30', '2025-05-26 12:31:30'),
(136, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:14:16', '2025-05-26 18:14:16'),
(137, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:14:16', '2025-05-26 18:14:16'),
(138, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:14:16', '2025-05-26 18:14:16'),
(139, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:14:16', '2025-05-26 18:14:16'),
(140, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:14:16', '2025-05-26 18:14:16'),
(141, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:42:43', '2025-05-26 18:42:43'),
(142, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:42:43', '2025-05-26 18:42:43'),
(143, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:42:43', '2025-05-26 18:42:43'),
(144, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:42:43', '2025-05-26 18:42:43'),
(145, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:42:43', '2025-05-26 18:42:43'),
(146, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:42:47', '2025-05-26 18:42:47'),
(147, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:42:47', '2025-05-26 18:42:47'),
(148, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:42:47', '2025-05-26 18:42:47'),
(149, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:42:47', '2025-05-26 18:42:47'),
(150, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:42:47', '2025-05-26 18:42:47'),
(151, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(152, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(153, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(154, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(155, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(156, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(157, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(158, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(159, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(160, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:42:59', '2025-05-26 18:42:59'),
(161, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:43:26', '2025-05-26 18:43:26'),
(162, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:43:26', '2025-05-26 18:43:26'),
(163, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:43:26', '2025-05-26 18:43:26'),
(164, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:43:26', '2025-05-26 18:43:26'),
(165, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:43:26', '2025-05-26 18:43:26'),
(166, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:43:28', '2025-05-26 18:43:28'),
(167, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:43:28', '2025-05-26 18:43:28'),
(168, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:43:28', '2025-05-26 18:43:28'),
(169, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:43:28', '2025-05-26 18:43:28'),
(170, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:43:28', '2025-05-26 18:43:28'),
(171, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(172, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(173, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(174, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(175, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(176, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(177, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(178, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(179, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(180, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 18:43:38', '2025-05-26 18:43:38'),
(181, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:02:18', '2025-05-26 23:02:18'),
(182, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:02:18', '2025-05-26 23:02:18'),
(183, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:02:18', '2025-05-26 23:02:18'),
(184, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:02:18', '2025-05-26 23:02:18'),
(185, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:02:18', '2025-05-26 23:02:18'),
(186, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:02:22', '2025-05-26 23:02:22'),
(187, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:02:22', '2025-05-26 23:02:22'),
(188, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:02:22', '2025-05-26 23:02:22'),
(189, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:02:22', '2025-05-26 23:02:22'),
(190, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:02:22', '2025-05-26 23:02:22'),
(191, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:02:27', '2025-05-26 23:02:27'),
(192, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:02:27', '2025-05-26 23:02:27'),
(193, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:02:27', '2025-05-26 23:02:27'),
(194, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:02:27', '2025-05-26 23:02:27'),
(195, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:02:27', '2025-05-26 23:02:27'),
(196, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:27', '2025-05-26 23:03:27'),
(197, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:27', '2025-05-26 23:03:27'),
(198, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:27', '2025-05-26 23:03:27'),
(199, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:27', '2025-05-26 23:03:27'),
(200, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:27', '2025-05-26 23:03:27'),
(201, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:32', '2025-05-26 23:03:32'),
(202, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:32', '2025-05-26 23:03:32'),
(203, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:32', '2025-05-26 23:03:32'),
(204, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:32', '2025-05-26 23:03:32'),
(205, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:32', '2025-05-26 23:03:32'),
(206, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(207, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(208, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(209, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(210, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(211, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(212, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(213, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(214, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(215, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:35', '2025-05-26 23:03:35'),
(216, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:39', '2025-05-26 23:03:39'),
(217, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:39', '2025-05-26 23:03:39'),
(218, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:39', '2025-05-26 23:03:39'),
(219, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:39', '2025-05-26 23:03:39'),
(220, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:39', '2025-05-26 23:03:39'),
(221, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:50', '2025-05-26 23:03:50'),
(222, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:50', '2025-05-26 23:03:50'),
(223, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:50', '2025-05-26 23:03:50'),
(224, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:50', '2025-05-26 23:03:50'),
(225, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:50', '2025-05-26 23:03:50'),
(226, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:54', '2025-05-26 23:03:54'),
(227, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:54', '2025-05-26 23:03:54'),
(228, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:54', '2025-05-26 23:03:54'),
(229, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:54', '2025-05-26 23:03:54'),
(230, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:54', '2025-05-26 23:03:54'),
(231, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(232, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(233, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(234, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(235, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(236, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(237, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(238, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(239, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(240, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:03:59', '2025-05-26 23:03:59'),
(241, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:07', '2025-05-26 23:04:07'),
(242, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:07', '2025-05-26 23:04:07'),
(243, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:07', '2025-05-26 23:04:07'),
(244, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:07', '2025-05-26 23:04:07'),
(245, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:07', '2025-05-26 23:04:07'),
(246, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(247, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(248, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(249, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(250, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(251, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(252, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(253, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(254, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(255, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:13', '2025-05-26 23:04:13'),
(256, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:47', '2025-05-26 23:04:47'),
(257, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:47', '2025-05-26 23:04:47'),
(258, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:47', '2025-05-26 23:04:47'),
(259, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:47', '2025-05-26 23:04:47'),
(260, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:47', '2025-05-26 23:04:47'),
(261, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:50', '2025-05-26 23:04:50'),
(262, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:50', '2025-05-26 23:04:50'),
(263, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:50', '2025-05-26 23:04:50'),
(264, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:50', '2025-05-26 23:04:50'),
(265, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:50', '2025-05-26 23:04:50'),
(266, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(267, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(268, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(269, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(270, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(271, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(272, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(273, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(274, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(275, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:04:52', '2025-05-26 23:04:52'),
(276, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:05:25', '2025-05-26 23:05:25'),
(277, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:05:25', '2025-05-26 23:05:25'),
(278, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:05:25', '2025-05-26 23:05:25'),
(279, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:05:25', '2025-05-26 23:05:25'),
(280, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:05:25', '2025-05-26 23:05:25'),
(281, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:05:27', '2025-05-26 23:05:27'),
(282, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:05:27', '2025-05-26 23:05:27'),
(283, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:05:27', '2025-05-26 23:05:27'),
(284, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:05:27', '2025-05-26 23:05:27'),
(285, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:05:27', '2025-05-26 23:05:27'),
(286, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:05:35', '2025-05-26 23:05:35'),
(287, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:05:35', '2025-05-26 23:05:35'),
(288, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:05:35', '2025-05-26 23:05:35'),
(289, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:05:35', '2025-05-26 23:05:35'),
(290, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:05:35', '2025-05-26 23:05:35'),
(291, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:05:36', '2025-05-26 23:05:36'),
(292, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:05:36', '2025-05-26 23:05:36'),
(293, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:05:36', '2025-05-26 23:05:36'),
(294, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:05:36', '2025-05-26 23:05:36'),
(295, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:05:36', '2025-05-26 23:05:36'),
(296, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:06:22', '2025-05-26 23:06:22'),
(297, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:06:22', '2025-05-26 23:06:22'),
(298, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:06:22', '2025-05-26 23:06:22'),
(299, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:06:22', '2025-05-26 23:06:22'),
(300, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:06:22', '2025-05-26 23:06:22'),
(301, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(302, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(303, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(304, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(305, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(306, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(307, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(308, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(309, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(310, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:06:24', '2025-05-26 23:06:24'),
(311, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:06:35', '2025-05-26 23:06:35'),
(312, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:06:35', '2025-05-26 23:06:35'),
(313, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:06:35', '2025-05-26 23:06:35'),
(314, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:06:35', '2025-05-26 23:06:35'),
(315, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:06:35', '2025-05-26 23:06:35'),
(316, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:06:43', '2025-05-26 23:06:43'),
(317, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:06:43', '2025-05-26 23:06:43'),
(318, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:06:43', '2025-05-26 23:06:43'),
(319, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:06:43', '2025-05-26 23:06:43'),
(320, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:06:43', '2025-05-26 23:06:43'),
(321, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:07:57', '2025-05-26 23:07:57'),
(322, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:07:57', '2025-05-26 23:07:57'),
(323, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:07:57', '2025-05-26 23:07:57'),
(324, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:07:57', '2025-05-26 23:07:57'),
(325, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:07:57', '2025-05-26 23:07:57'),
(326, 'admin', 'Yönetici (Tam Yetki)', '2025-05-26 23:07:59', '2025-05-26 23:07:59'),
(327, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-26 23:07:59', '2025-05-26 23:07:59'),
(328, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-26 23:07:59', '2025-05-26 23:07:59'),
(329, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-26 23:07:59', '2025-05-26 23:07:59'),
(330, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-26 23:07:59', '2025-05-26 23:07:59'),
(331, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:22:41', '2025-05-27 00:22:41'),
(332, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:22:41', '2025-05-27 00:22:41'),
(333, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:22:41', '2025-05-27 00:22:41'),
(334, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:22:41', '2025-05-27 00:22:41'),
(335, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:22:41', '2025-05-27 00:22:41'),
(336, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:33', '2025-05-27 00:28:33'),
(337, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:33', '2025-05-27 00:28:33'),
(338, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:33', '2025-05-27 00:28:33'),
(339, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:33', '2025-05-27 00:28:33'),
(340, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:33', '2025-05-27 00:28:33'),
(341, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:35', '2025-05-27 00:28:35'),
(342, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:35', '2025-05-27 00:28:35'),
(343, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:35', '2025-05-27 00:28:35'),
(344, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:35', '2025-05-27 00:28:35'),
(345, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:35', '2025-05-27 00:28:35'),
(346, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(347, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(348, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(349, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(350, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(351, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(352, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(353, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(354, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(355, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:41', '2025-05-27 00:28:41'),
(356, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:44', '2025-05-27 00:28:44'),
(357, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:44', '2025-05-27 00:28:44'),
(358, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:44', '2025-05-27 00:28:44'),
(359, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:44', '2025-05-27 00:28:44'),
(360, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:44', '2025-05-27 00:28:44'),
(361, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(362, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(363, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(364, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(365, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(366, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(367, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(368, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(369, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(370, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:28:49', '2025-05-27 00:28:49'),
(371, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:36', '2025-05-27 00:30:36'),
(372, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:36', '2025-05-27 00:30:36'),
(373, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:36', '2025-05-27 00:30:36'),
(374, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:36', '2025-05-27 00:30:36'),
(375, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:36', '2025-05-27 00:30:36'),
(376, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:38', '2025-05-27 00:30:38'),
(377, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:38', '2025-05-27 00:30:38'),
(378, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:38', '2025-05-27 00:30:38'),
(379, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:38', '2025-05-27 00:30:38'),
(380, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:38', '2025-05-27 00:30:38'),
(381, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(382, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(383, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(384, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(385, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(386, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(387, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(388, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(389, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(390, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:41', '2025-05-27 00:30:41'),
(391, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:44', '2025-05-27 00:30:44'),
(392, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:44', '2025-05-27 00:30:44'),
(393, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:44', '2025-05-27 00:30:44'),
(394, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:44', '2025-05-27 00:30:44'),
(395, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:44', '2025-05-27 00:30:44'),
(396, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:47', '2025-05-27 00:30:47'),
(397, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:47', '2025-05-27 00:30:47'),
(398, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:47', '2025-05-27 00:30:47'),
(399, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:47', '2025-05-27 00:30:47'),
(400, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:47', '2025-05-27 00:30:47'),
(401, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:30:48', '2025-05-27 00:30:48'),
(402, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:30:48', '2025-05-27 00:30:48'),
(403, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:30:48', '2025-05-27 00:30:48'),
(404, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:30:48', '2025-05-27 00:30:48'),
(405, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:30:48', '2025-05-27 00:30:48'),
(406, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:31:15', '2025-05-27 00:31:15'),
(407, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:31:15', '2025-05-27 00:31:15'),
(408, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:31:15', '2025-05-27 00:31:15'),
(409, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:31:15', '2025-05-27 00:31:15'),
(410, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:31:15', '2025-05-27 00:31:15'),
(411, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:31:17', '2025-05-27 00:31:17'),
(412, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:31:17', '2025-05-27 00:31:17'),
(413, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:31:17', '2025-05-27 00:31:17'),
(414, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:31:17', '2025-05-27 00:31:17'),
(415, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:31:17', '2025-05-27 00:31:17'),
(416, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(417, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(418, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(419, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(420, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(421, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(422, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(423, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(424, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(425, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:31:52', '2025-05-27 00:31:52'),
(426, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:31:54', '2025-05-27 00:31:54'),
(427, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:31:54', '2025-05-27 00:31:54'),
(428, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:31:54', '2025-05-27 00:31:54'),
(429, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:31:54', '2025-05-27 00:31:54'),
(430, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:31:54', '2025-05-27 00:31:54'),
(431, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:32:39', '2025-05-27 00:32:39'),
(432, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:32:39', '2025-05-27 00:32:39'),
(433, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:32:39', '2025-05-27 00:32:39'),
(434, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:32:39', '2025-05-27 00:32:39'),
(435, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:32:39', '2025-05-27 00:32:39'),
(436, 'admin', 'Yönetici (Tam Yetki)', '2025-05-27 00:32:43', '2025-05-27 00:32:43'),
(437, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-05-27 00:32:43', '2025-05-27 00:32:43'),
(438, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-05-27 00:32:43', '2025-05-27 00:32:43'),
(439, 'staff', 'Personel (Sınırlı Yetki)', '2025-05-27 00:32:43', '2025-05-27 00:32:43'),
(440, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-05-27 00:32:43', '2025-05-27 00:32:43'),
(441, 'admin', 'Yönetici (Tam Yetki)', '2025-10-31 00:33:13', '2025-10-31 00:33:13'),
(442, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-10-31 00:33:13', '2025-10-31 00:33:13'),
(443, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-10-31 00:33:13', '2025-10-31 00:33:13'),
(444, 'staff', 'Personel (Sınırlı Yetki)', '2025-10-31 00:33:13', '2025-10-31 00:33:13'),
(445, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-10-31 00:33:13', '2025-10-31 00:33:13'),
(446, 'admin', 'Yönetici (Tam Yetki)', '2025-10-31 01:05:21', '2025-10-31 01:05:21'),
(447, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-10-31 01:05:21', '2025-10-31 01:05:21'),
(448, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-10-31 01:05:21', '2025-10-31 01:05:21'),
(449, 'staff', 'Personel (Sınırlı Yetki)', '2025-10-31 01:05:21', '2025-10-31 01:05:21'),
(450, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-10-31 01:05:21', '2025-10-31 01:05:21'),
(451, 'admin', 'Yönetici (Tam Yetki)', '2025-11-01 00:49:23', '2025-11-01 00:49:23'),
(452, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-01 00:49:23', '2025-11-01 00:49:23'),
(453, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-01 00:49:23', '2025-11-01 00:49:23'),
(454, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-01 00:49:23', '2025-11-01 00:49:23'),
(455, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-01 00:49:23', '2025-11-01 00:49:23'),
(456, 'admin', 'Yönetici (Tam Yetki)', '2025-11-01 00:57:25', '2025-11-01 00:57:25'),
(457, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-01 00:57:25', '2025-11-01 00:57:25'),
(458, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-01 00:57:25', '2025-11-01 00:57:25'),
(459, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-01 00:57:25', '2025-11-01 00:57:25'),
(460, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-01 00:57:25', '2025-11-01 00:57:25'),
(461, 'admin', 'Yönetici (Tam Yetki)', '2025-11-01 00:57:31', '2025-11-01 00:57:31'),
(462, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-01 00:57:31', '2025-11-01 00:57:31'),
(463, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-01 00:57:31', '2025-11-01 00:57:31'),
(464, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-01 00:57:31', '2025-11-01 00:57:31'),
(465, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-01 00:57:31', '2025-11-01 00:57:31'),
(466, 'admin', 'Yönetici (Tam Yetki)', '2025-11-01 00:57:37', '2025-11-01 00:57:37'),
(467, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-01 00:57:37', '2025-11-01 00:57:37'),
(468, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-01 00:57:37', '2025-11-01 00:57:37'),
(469, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-01 00:57:37', '2025-11-01 00:57:37'),
(470, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-01 00:57:37', '2025-11-01 00:57:37'),
(471, 'admin', 'Yönetici (Tam Yetki)', '2025-11-01 00:57:40', '2025-11-01 00:57:40'),
(472, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-01 00:57:40', '2025-11-01 00:57:40'),
(473, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-01 00:57:40', '2025-11-01 00:57:40'),
(474, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-01 00:57:40', '2025-11-01 00:57:40'),
(475, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-01 00:57:40', '2025-11-01 00:57:40'),
(476, 'admin', 'Yönetici (Tam Yetki)', '2025-11-01 00:57:43', '2025-11-01 00:57:43'),
(477, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-01 00:57:43', '2025-11-01 00:57:43'),
(478, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-01 00:57:43', '2025-11-01 00:57:43'),
(479, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-01 00:57:43', '2025-11-01 00:57:43'),
(480, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-01 00:57:43', '2025-11-01 00:57:43'),
(481, 'admin', 'Yönetici (Tam Yetki)', '2025-11-02 00:30:37', '2025-11-02 00:30:37'),
(482, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-02 00:30:37', '2025-11-02 00:30:37'),
(483, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-02 00:30:37', '2025-11-02 00:30:37'),
(484, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-02 00:30:37', '2025-11-02 00:30:37'),
(485, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-02 00:30:37', '2025-11-02 00:30:37'),
(486, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 01:24:48', '2025-11-05 01:24:48'),
(487, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 01:24:48', '2025-11-05 01:24:48'),
(488, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 01:24:48', '2025-11-05 01:24:48'),
(489, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 01:24:48', '2025-11-05 01:24:48'),
(490, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 01:24:48', '2025-11-05 01:24:48'),
(491, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 21:43:53', '2025-11-05 21:43:53'),
(492, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 21:43:53', '2025-11-05 21:43:53'),
(493, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 21:43:53', '2025-11-05 21:43:53'),
(494, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 21:43:53', '2025-11-05 21:43:53'),
(495, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 21:43:53', '2025-11-05 21:43:53'),
(496, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:44:16', '2025-11-05 22:44:16'),
(497, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:44:16', '2025-11-05 22:44:16'),
(498, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:44:16', '2025-11-05 22:44:16'),
(499, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:44:16', '2025-11-05 22:44:16'),
(500, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:44:16', '2025-11-05 22:44:16'),
(501, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:44:19', '2025-11-05 22:44:19'),
(502, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:44:19', '2025-11-05 22:44:19'),
(503, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:44:19', '2025-11-05 22:44:19'),
(504, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:44:19', '2025-11-05 22:44:19'),
(505, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:44:19', '2025-11-05 22:44:19'),
(506, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:44:25', '2025-11-05 22:44:25'),
(507, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:44:25', '2025-11-05 22:44:25'),
(508, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:44:25', '2025-11-05 22:44:25'),
(509, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:44:25', '2025-11-05 22:44:25'),
(510, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:44:25', '2025-11-05 22:44:25'),
(511, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:44:27', '2025-11-05 22:44:27'),
(512, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:44:27', '2025-11-05 22:44:27'),
(513, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:44:27', '2025-11-05 22:44:27'),
(514, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:44:27', '2025-11-05 22:44:27'),
(515, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:44:27', '2025-11-05 22:44:27'),
(516, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:45:41', '2025-11-05 22:45:41'),
(517, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:45:41', '2025-11-05 22:45:41'),
(518, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:45:41', '2025-11-05 22:45:41'),
(519, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:45:41', '2025-11-05 22:45:41'),
(520, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:45:41', '2025-11-05 22:45:41'),
(521, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:49:53', '2025-11-05 22:49:53'),
(522, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:49:53', '2025-11-05 22:49:53'),
(523, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:49:53', '2025-11-05 22:49:53'),
(524, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:49:53', '2025-11-05 22:49:53'),
(525, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:49:53', '2025-11-05 22:49:53'),
(526, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 22:49:55', '2025-11-05 22:49:55'),
(527, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 22:49:55', '2025-11-05 22:49:55'),
(528, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 22:49:55', '2025-11-05 22:49:55'),
(529, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 22:49:55', '2025-11-05 22:49:55'),
(530, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 22:49:55', '2025-11-05 22:49:55'),
(531, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 23:19:28', '2025-11-05 23:19:28'),
(532, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 23:19:28', '2025-11-05 23:19:28'),
(533, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 23:19:28', '2025-11-05 23:19:28'),
(534, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 23:19:28', '2025-11-05 23:19:28'),
(535, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 23:19:28', '2025-11-05 23:19:28'),
(536, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 23:19:29', '2025-11-05 23:19:29'),
(537, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 23:19:29', '2025-11-05 23:19:29'),
(538, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 23:19:29', '2025-11-05 23:19:29'),
(539, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 23:19:29', '2025-11-05 23:19:29'),
(540, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 23:19:29', '2025-11-05 23:19:29'),
(541, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 23:19:36', '2025-11-05 23:19:36'),
(542, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 23:19:36', '2025-11-05 23:19:36'),
(543, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 23:19:36', '2025-11-05 23:19:36'),
(544, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 23:19:36', '2025-11-05 23:19:36'),
(545, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 23:19:36', '2025-11-05 23:19:36'),
(546, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 23:19:38', '2025-11-05 23:19:38'),
(547, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 23:19:38', '2025-11-05 23:19:38'),
(548, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 23:19:38', '2025-11-05 23:19:38'),
(549, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 23:19:38', '2025-11-05 23:19:38'),
(550, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 23:19:38', '2025-11-05 23:19:38'),
(551, 'admin', 'Yönetici (Tam Yetki)', '2025-11-05 23:19:43', '2025-11-05 23:19:43'),
(552, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-05 23:19:43', '2025-11-05 23:19:43'),
(553, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-05 23:19:43', '2025-11-05 23:19:43'),
(554, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-05 23:19:43', '2025-11-05 23:19:43'),
(555, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-05 23:19:43', '2025-11-05 23:19:43'),
(556, 'admin', 'Administrator (Full Access)', '2025-11-05 23:43:02', '2025-11-05 23:43:02'),
(557, 'manager', 'Manager (Edit Permission)', '2025-11-05 23:43:02', '2025-11-05 23:43:02');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(558, 'accountant', 'Accountant (Financial Permission)', '2025-11-05 23:43:02', '2025-11-05 23:43:02'),
(559, 'staff', 'Staff (Limited Permission)', '2025-11-05 23:43:02', '2025-11-05 23:43:02'),
(560, 'viewer', 'Viewer (View Only)', '2025-11-05 23:43:02', '2025-11-05 23:43:02'),
(561, 'admin', 'Administrator (Full Access)', '2025-11-05 23:44:57', '2025-11-05 23:44:57'),
(562, 'manager', 'Manager (Edit Permission)', '2025-11-05 23:44:57', '2025-11-05 23:44:57'),
(563, 'accountant', 'Accountant (Financial Permission)', '2025-11-05 23:44:57', '2025-11-05 23:44:57'),
(564, 'staff', 'Staff (Limited Permission)', '2025-11-05 23:44:57', '2025-11-05 23:44:57'),
(565, 'viewer', 'Viewer (View Only)', '2025-11-05 23:44:57', '2025-11-05 23:44:57'),
(566, 'admin', 'Administrator (Full Access)', '2025-11-05 23:47:08', '2025-11-05 23:47:08'),
(567, 'manager', 'Manager (Edit Permission)', '2025-11-05 23:47:08', '2025-11-05 23:47:08'),
(568, 'accountant', 'Accountant (Financial Permission)', '2025-11-05 23:47:08', '2025-11-05 23:47:08'),
(569, 'staff', 'Staff (Limited Permission)', '2025-11-05 23:47:08', '2025-11-05 23:47:08'),
(570, 'viewer', 'Viewer (View Only)', '2025-11-05 23:47:08', '2025-11-05 23:47:08'),
(571, 'admin', 'Yönetici (Tam Yetki)', '2025-11-07 21:45:31', '2025-11-07 21:45:31'),
(572, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-07 21:45:31', '2025-11-07 21:45:31'),
(573, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-07 21:45:31', '2025-11-07 21:45:31'),
(574, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-07 21:45:31', '2025-11-07 21:45:31'),
(575, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-07 21:45:31', '2025-11-07 21:45:31'),
(576, 'admin', 'Yönetici (Tam Yetki)', '2025-11-18 13:16:25', '2025-11-18 13:16:25'),
(577, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-18 13:16:25', '2025-11-18 13:16:25'),
(578, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-18 13:16:25', '2025-11-18 13:16:25'),
(579, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-18 13:16:25', '2025-11-18 13:16:25'),
(580, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-18 13:16:25', '2025-11-18 13:16:25'),
(581, 'admin', 'Yönetici (Tam Yetki)', '2025-11-30 12:52:09', '2025-11-30 12:52:09'),
(582, 'manager', 'Müdür (Düzenleme Yetkisi)', '2025-11-30 12:52:09', '2025-11-30 12:52:09'),
(583, 'accountant', 'Muhasebeci (Mali Yetki)', '2025-11-30 12:52:09', '2025-11-30 12:52:09'),
(584, 'staff', 'Personel (Sınırlı Yetki)', '2025-11-30 12:52:09', '2025-11-30 12:52:09'),
(585, 'viewer', 'İzleyici (Sadece Görüntüleme)', '2025-11-30 12:52:09', '2025-11-30 12:52:09');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Megabre StokMaster', 'Site adı', '2025-05-24 18:51:34', '2025-05-25 04:28:26'),
(2, 'company_name', 'Şahinler Doğrama', 'Şirket adı', '2025-05-24 18:51:34', '2025-11-30 13:07:30'),
(3, 'cache_enabled', '0', 'Cache sistemi aktif mi?', '2025-05-24 18:51:34', '2025-11-05 23:20:21'),
(4, 'default_language', 'tr', 'Varsayılan dil', '2025-05-24 18:51:34', '2025-05-24 18:51:34'),
(5, 'backup_enabled', '1', 'Yedekleme sistemi aktif mi?', '2025-05-24 18:51:34', '2025-05-24 18:51:34'),
(6, 'currency_symbol', '₺', 'Para birimi sembolü', '2025-05-24 18:51:34', '2025-05-24 18:51:34'),
(7, 'items_per_page', '50', 'Sayfa başına gösterilecek öğe sayısı', '2025-05-24 18:51:34', '2025-05-24 18:51:34'),
(8, 'version', '1.0.0', 'Sistem versiyonu', '2025-05-24 18:51:34', '2025-05-24 18:51:34'),
(9, 'inventory_settings', '{\"low_stock_threshold\":\"10\",\"enable_stock_alerts\":true,\"enable_negative_stock\":false,\"default_stock_unit\":\"adet\",\"enable_barcode\":true,\"enable_serial_number\":false,\"enable_batch_tracking\":false,\"enable_expiry_date\":false,\"enable_location_tracking\":false,\"default_location\":\"Ana Depo\",\"default_unit\":\"1\",\"auto_sku\":1,\"sku_prefix\":\"PRD\",\"stock_movement_notes\":1,\"order_auto_status\":1,\"order_cancel_stock\":1,\"allow_negative_stock\":1,\"stock_history\":1,\"stock_history_days\":\"90\"}', 'Envanter ayarları', '2025-05-25 03:37:10', '2025-05-25 03:43:32'),
(11, 'company_address', 'Kocaeli/Trabzon Türkiye 61030', NULL, '2025-05-25 04:28:13', '2025-11-06 20:03:17'),
(12, 'company_phone', '5323903400', NULL, '2025-05-25 04:28:13', '2025-11-06 20:03:17'),
(13, 'company_email', 'sys@rootali.net', NULL, '2025-05-25 04:28:13', '2025-11-06 20:03:17'),
(14, 'company_tax_id', '46942838004', NULL, '2025-05-25 04:28:13', '2025-11-06 20:03:17'),
(15, 'default_currency', 'TRY', NULL, '2025-05-25 04:28:13', '2025-05-25 04:28:13'),
(16, 'date_format', 'Y-m-d H:i', NULL, '2025-05-25 04:28:13', '2025-11-07 19:49:47'),
(17, 'timezone', 'Europe/Istanbul', NULL, '2025-05-25 04:28:13', '2025-05-25 04:28:13'),
(18, 'max_upload_size', '5000', NULL, '2025-05-25 04:28:13', '2025-05-25 04:28:13'),
(19, 'last_update', '2025-11-30 13:07:30', NULL, '2025-05-25 04:28:13', '2025-11-30 13:07:30'),
(20, 'company_logo', 'logo_1763415103.png', NULL, '2025-05-25 04:28:18', '2025-11-18 00:31:43'),
(21, 'cache_ttl', '3600', 'Önbellek saklama süresi (saniye)', '2025-11-05 00:54:34', '2025-11-05 23:20:21'),
(22, 'cache_method', 'file', 'Önbellek saklama metodu', '2025-11-05 00:54:34', '2025-11-05 23:20:21'),
(23, 'default_currency_id', '1', 'Varsayılan para birimi ID (TRY için 1)', '2025-11-05 22:36:30', '2025-11-05 22:36:30');

-- --------------------------------------------------------

--
-- Table structure for table `stock_fields`
--

CREATE TABLE `stock_fields` (
  `id` int NOT NULL,
  `field_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_id` int DEFAULT NULL,
  `field_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` enum('text','number','select','textarea','date') COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_options` text COLLATE utf8mb4_unicode_ci,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `field_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `field_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `type` enum('in','out','adjustment') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'in=giriş, out=çıkış, adjustment=düzeltme',
  `quantity` decimal(10,2) NOT NULL,
  `unit` enum('piece','kg','lt','m','m2','m3','package','box','pallet') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piece',
  `date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `order_id` int DEFAULT NULL COMMENT 'Siparişten kaynaklı stok hareketi ise',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `type`, `quantity`, `unit`, `date`, `notes`, `order_id`, `created_at`, `created_by`, `updated_at`) VALUES
(30, 27, 'in', 650.00, 'piece', '2025-11-06', 'İlk stok girişi yapıldı', NULL, '2025-11-06 19:09:02', 'system', '2025-11-06 19:09:02'),
(31, 28, 'in', 55.00, 'piece', '2025-11-06', 'İlk stok girdi', NULL, '2025-11-06 19:12:11', 'system', '2025-11-06 19:12:11'),
(32, 29, 'in', 45.00, 'piece', '2025-11-06', 'İlk stoğa girdi', NULL, '2025-11-06 19:37:35', 'system', '2025-11-06 19:37:35'),
(33, 27, 'out', 500.00, 'piece', '2025-11-07', 'Sipariş #000012', NULL, '2025-11-07 19:33:05', NULL, '2025-11-07 19:33:05'),
(34, 28, 'out', 5.00, 'piece', '2025-11-07', 'Sipariş #000012', NULL, '2025-11-07 19:33:05', NULL, '2025-11-07 19:33:05'),
(35, 29, 'out', 5.00, 'piece', '2025-11-07', 'Sipariş #000012', NULL, '2025-11-07 19:33:05', NULL, '2025-11-07 19:33:05'),
(36, 30, 'in', 15.00, 'piece', '2025-11-18', 'Stok girişi', NULL, '2025-11-18 00:27:49', 'system', '2025-11-18 00:27:49'),
(37, 30, 'out', 12.00, 'piece', '2025-11-18', 'Sipariş #000013', NULL, '2025-11-18 00:28:37', NULL, '2025-11-18 00:28:37');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `type` enum('payment','debt') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'payment=ödeme/bakiye, debt=borç',
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `payment_method` enum('cash','check','promissory_note','credit_card','bank_transfer') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_installment` tinyint(1) NOT NULL DEFAULT '0',
  `installment_count` int DEFAULT NULL,
  `installment_number` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `order_id` int DEFAULT NULL COMMENT 'Siparişe bağlı işlem ise',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `currency_id` int DEFAULT NULL COMMENT 'İşlem para birimi',
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Birim (gram, adet, kg vb.)',
  `unit_price` decimal(15,5) DEFAULT NULL COMMENT 'Birim fiyatı (örneğin gram başına altın fiyatı)',
  `unit_quantity` decimal(15,5) DEFAULT NULL COMMENT 'Birim miktarı (örn: 10 gram)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `customer_id`, `type`, `amount`, `date`, `payment_method`, `reference_no`, `is_installment`, `installment_count`, `installment_number`, `notes`, `order_id`, `created_at`, `updated_at`, `currency_id`, `unit`, `unit_price`, `unit_quantity`) VALUES
(35, 15, 'debt', 101826.00, '2025-11-07', NULL, NULL, 0, NULL, NULL, 'Sipariş borcu: #000012', NULL, '2025-11-07 19:33:05', '2025-11-07 19:33:05', NULL, NULL, NULL, NULL),
(36, 15, 'payment', 100000.00, '2025-11-07', 'cash', 'Elden verdi', 0, 0, 0, 'Elden poşetle getirdi :)', NULL, '2025-11-07 19:33:57', '2025-11-07 19:33:57', NULL, NULL, NULL, NULL),
(37, 15, 'payment', 1826.00, '2025-11-07', 'cash', '', 0, 0, 0, '', NULL, '2025-11-07 19:34:55', '2025-11-07 19:34:55', NULL, NULL, NULL, NULL),
(38, 14, 'debt', 3000.00, '2025-11-18', NULL, NULL, 0, NULL, NULL, 'Sipariş borcu: #000013', NULL, '2025-11-18 00:28:37', '2025-11-18 00:28:37', NULL, NULL, NULL, NULL),
(39, 15, 'debt', 15000.00, '2025-11-18', 'cash', '', 0, 0, 0, '', NULL, '2025-11-18 00:29:42', '2025-11-18 00:29:42', 1, NULL, NULL, NULL),
(40, 15, 'payment', 16500.00, '2025-11-18', 'cash', '', 0, 0, 0, '', NULL, '2025-11-18 00:30:08', '2025-11-18 00:30:08', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'tr',
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `surname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user','accounting') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `last_login` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `language`, `profile_image`, `name`, `surname`, `role`, `last_login`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$P.dXLE.B22nc99RG/z.saO1jlVRM4rVOpGzd21EfYeWZne3mgV81a', 'admin@megabre.com', 'tr', 'profile_1748134011.png', 'Admin', 'Ali', 'admin', '2025-05-30 20:48:24', 1, '2025-05-24 18:51:34', '2025-05-30 20:48:24'),
(2, 'Slaweally', '$2y$10$WXbUM4D7jGMltk1GZ..qOOVJF0hI17dOMwumFbQCNhwJwnLQlXXCO', 'test@mail.com', 'tr', 'profile_1761947823.jpg', 'Ali', 'Hoca9', 'admin', '2025-11-30 13:03:47', 1, '2025-05-25 03:49:43', '2025-11-30 13:03:47');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `activity` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity`
--

INSERT INTO `user_activity` (`id`, `user_id`, `activity`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'change_password', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', '2025-05-25 03:34:58'),
(2, 1, 'change_password', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', '2025-05-25 11:18:13'),
(3, 1, 'change_password', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0', '2025-05-25 18:37:44'),
(4, 2, 'password_reset', 'Password reset via reset-password.php script', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-10-31 00:26:03'),
(5, 2, 'update_profile', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-11-01 00:56:53'),
(6, 2, 'update_profile', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-11-01 00:57:03'),
(7, 2, 'update_profile', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-11-05 19:09:37'),
(8, 2, 'update_profile', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-11-05 19:09:58'),
(9, 2, 'update_profile', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-11-05 19:12:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_logs_user_id` (`user_id`),
  ADD KEY `idx_activity_logs_created_at` (`created_at`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `backup_id` (`backup_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `category_fields`
--
ALTER TABLE `category_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `category_field_values`
--
ALTER TABLE `category_field_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_field_unique` (`category_id`,`field_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_fields`
--
ALTER TABLE `customer_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_field_values`
--
ALTER TABLE `customer_field_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_field_unique` (`customer_id`,`field_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `customer_tags`
--
ALTER TABLE `customer_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `customer_tag_relations`
--
ALTER TABLE `customer_tag_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_tag_unique` (`customer_id`,`tag_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `dynamic_fields`
--
ALTER TABLE `dynamic_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `table_name` (`table_name`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expenses_date` (`date`),
  ADD KEY `idx_expenses_category` (`category`),
  ADD KEY `idx_expenses_created_by` (`created_by`);

--
-- Indexes for table `import_export_logs`
--
ALTER TABLE `import_export_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `installments`
--
ALTER TABLE `installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `language_translations`
--
ALTER TABLE `language_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `language_id_key` (`language_id`,`translation_key`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_username` (`username`),
  ADD KEY `idx_login_attempts_attempt_time` (`attempt_time`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `measurement_units`
--
ALTER TABLE `measurement_units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `applied_tag_id` (`applied_tag_id`);

--
-- Indexes for table `order_history`
--
ALTER TABLE `order_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `product_fields`
--
ALTER TABLE `product_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `stock_fields`
--
ALTER TABLE `stock_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `category_fields`
--
ALTER TABLE `category_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `category_field_values`
--
ALTER TABLE `category_field_values`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customer_fields`
--
ALTER TABLE `customer_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customer_field_values`
--
ALTER TABLE `customer_field_values`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_tags`
--
ALTER TABLE `customer_tags`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customer_tag_relations`
--
ALTER TABLE `customer_tag_relations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dynamic_fields`
--
ALTER TABLE `dynamic_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `import_export_logs`
--
ALTER TABLE `import_export_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `installments`
--
ALTER TABLE `installments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `language_translations`
--
ALTER TABLE `language_translations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `measurement_units`
--
ALTER TABLE `measurement_units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `order_history`
--
ALTER TABLE `order_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `product_fields`
--
ALTER TABLE `product_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=586;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `stock_fields`
--
ALTER TABLE `stock_fields`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD CONSTRAINT `backup_logs_ibfk_1` FOREIGN KEY (`backup_id`) REFERENCES `backups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `backup_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_field_values`
--
ALTER TABLE `category_field_values`
  ADD CONSTRAINT `category_field_values_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_field_values_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `category_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_fields`
--
ALTER TABLE `customer_fields`
  ADD CONSTRAINT `customer_fields_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_field_values`
--
ALTER TABLE `customer_field_values`
  ADD CONSTRAINT `customer_field_values_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_field_values_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `customer_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_tag_relations`
--
ALTER TABLE `customer_tag_relations`
  ADD CONSTRAINT `customer_tag_relations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `customer_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `import_export_logs`
--
ALTER TABLE `import_export_logs`
  ADD CONSTRAINT `import_export_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installments`
--
ALTER TABLE `installments`
  ADD CONSTRAINT `installments_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `language_translations`
--
ALTER TABLE `language_translations`
  ADD CONSTRAINT `language_translations_ibfk_1` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_tag` FOREIGN KEY (`applied_tag_id`) REFERENCES `customer_tags` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_history`
--
ALTER TABLE `order_history`
  ADD CONSTRAINT `order_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_fields`
--
ALTER TABLE `stock_fields`
  ADD CONSTRAINT `stock_fields_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `stock_movements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
