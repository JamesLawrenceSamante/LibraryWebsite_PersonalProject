-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping data for table neu_library.audit_log: ~0 rows (approximately)
INSERT INTO `audit_log` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
	(20, 7, 'UPDATE', 'users', 7, 'username:Admin001, email:badger@neu.edu.ph, active:1', 'username:Admin001, email:glenn@neu.edu.ph, active:1', NULL, NULL, '2026-03-16 17:05:04');

-- Dumping data for table neu_library.blocked_users: ~0 rows (approximately)

-- Dumping data for table neu_library.library_stats: ~0 rows (approximately)

-- Dumping data for table neu_library.notifications: ~0 rows (approximately)

-- Dumping data for table neu_library.users: ~5 rows (approximately)
INSERT INTO `users` (`user_id`, `rfid`, `username`, `password`, `email`, `full_name`, `user_type`, `student_id`, `department`, `phone`, `address`, `profile_picture`, `is_active`, `created_at`, `updated_at`, `last_login`) VALUES
	(4, NULL, 'student1', '123', 'student1@neu.edu', 'Glenn Ross M. Ramones', 'student', 'S2024001', 'Computer Science', '555-0101', '123 College Ave', NULL, 1, '2026-03-16 01:19:27', '2026-03-16 16:42:13', NULL),
	(7, NULL, 'Admin001', '123', 'glenn@neu.edu.ph', 'Glenn Ross M. Ramones', 'admin', NULL, NULL, NULL, NULL, NULL, 1, '2026-03-16 09:30:37', '2026-03-16 17:05:04', '2026-03-16 12:56:19'),
	(8, NULL, 'faculty1', 'password123', 'faculty1@neu.edu', 'Prof. Jerry Esperanza', 'staff', NULL, 'Computer Science', NULL, NULL, NULL, 1, '2026-03-16 12:08:29', '2026-03-16 13:55:57', NULL),
	(9, NULL, 'faculty2', 'password123', 'faculty2@neu.edu', 'Staff 2', 'staff', NULL, 'Engineering', NULL, NULL, NULL, 1, '2026-03-16 12:08:29', '2026-03-16 13:35:38', NULL),
	(10, NULL, 'employee1', 'password123', 'employee1@neu.edu', 'Staff 3', 'staff', NULL, 'Library Staff', NULL, NULL, NULL, 1, '2026-03-16 12:08:29', '2026-03-16 13:35:41', NULL);

-- Dumping data for table neu_library.visitor_logs: ~0 rows (approximately)

-- Dumping data for table neu_library.visitor_stats: ~0 rows (approximately)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
