-- =====================================================================
--  HUIT Schedule Hub - Cấu trúc cơ sở dữ liệu (MySQL 5.7+/8.x, MariaDB)
-- =====================================================================
CREATE DATABASE IF NOT EXISTS huit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE huit_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS branches;
SET FOREIGN_KEY_CHECKS = 1;

-- Bảng Cơ sở
CREATE TABLE branches (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_name  VARCHAR(150) NOT NULL,
    address      VARCHAR(255) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Bảng Giảng viên (password_hash, access_token phục vụ Cổng thông tin Giảng viên)
CREATE TABLE teachers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    phone          VARCHAR(20)  NULL,
    expertise      VARCHAR(255) NULL COMMENT 'Chuyên môn chính, cách nhau dấu phẩy',
    password_hash  VARCHAR(255) NULL,
    access_token   CHAR(32)     NULL UNIQUE COMMENT 'Đường dẫn riêng: portal.php?token=...',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Bảng Lịch dạy
--   day_of_week      : 2 = Thứ 2 ... 7 = Thứ 7, 8 = Chủ nhật
--   shift_or_periods : "tiết_bắt_đầu-tiết_kết_thúc", VD "1-3" (Sáng 1-6, Chiều 7-12, Tối 13-15)
CREATE TABLE schedules (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id        INT UNSIGNED NOT NULL,
    branch_id         INT UNSIGNED NOT NULL,
    subject_name      VARCHAR(150) NOT NULL,
    room              VARCHAR(30)  NOT NULL,
    day_of_week       TINYINT UNSIGNED NOT NULL,
    shift_or_periods  VARCHAR(20)  NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sched_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sched_branch  FOREIGN KEY (branch_id)  REFERENCES branches (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_teacher_day (teacher_id, day_of_week),
    INDEX idx_branch_day  (branch_id, day_of_week)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- DỮ LIỆU MẪU (mật khẩu giảng viên mặc định: 123456)
-- ---------------------------------------------------------------------

INSERT INTO branches (id, branch_name, address) VALUES
(1, 'Cơ sở chính – Tân Phú', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM'),
(2, 'Cơ sở 2 – Khu phòng máy', 'Khu thực hành phòng máy (địa chỉ mẫu), Q. Tân Phú, TP.HCM'),
(3, 'Trung tâm liên kết – Bình Tân', 'Trung tâm đào tạo liên kết (địa chỉ mẫu), Q. Bình Tân, TP.HCM');

INSERT INTO teachers (id, full_name, email, phone, expertise, password_hash, access_token) VALUES
(1, 'Nguyễn Văn An', 'an.nv@huit.edu.vn', '0952082305', 'Lập trình web, PHP, Mã nguồn mở', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', '3468e193b200160b12025db02a042b96'),
(2, 'Trần Thị Bích Ngọc', 'ngoc.ttb@huit.edu.vn', '0962560774', 'Cơ sở dữ liệu, SQL, NoSQL', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'edb2e6f6d331da28cdc1527d74d5b5c0'),
(3, 'Lê Hoàng Minh', 'minh.lh@huit.edu.vn', '0973871898', 'Mạng máy tính, An ninh mạng', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', '58ddfc70fc146ba1672619ca580760ea'),
(4, 'Phạm Thu Hà', 'ha.pt@huit.edu.vn', '0988273336', 'Trí tuệ nhân tạo, Học máy', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', '4370d374f90bff692db0ea2d70b2a9ce'),
(5, 'Võ Quốc Bảo', 'bao.vq@huit.edu.vn', '0964617421', 'Lập trình di động, Flutter', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', '88cd6abd9132c338016e4053c21ed142'),
(6, 'Đặng Thị Mai', 'mai.dt@huit.edu.vn', '0995491917', 'Cấu trúc dữ liệu và giải thuật, Lập trình C++', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'c9a9a8966aa467b981d3895ef7c01499'),
(7, 'Huỳnh Tấn Phát', 'phat.ht@huit.edu.vn', '0931818397', 'Hệ điều hành, Linux', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'de44b66ef73e4f4d97ba07c08f22cafe'),
(8, 'Bùi Ngọc Lan', 'lan.bn@huit.edu.vn', '0976691060', 'Công nghệ phần mềm, Phân tích thiết kế hệ thống', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'aaf4676d1963d9a678db3a6450858a94'),
(9, 'Đỗ Minh Khoa', 'khoa.dm@huit.edu.vn', '0988534300', 'Dữ liệu lớn, Khoa học dữ liệu', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'f708d4ce254d7ac85419f4c176a18a1d'),
(10, 'Hồ Thị Thanh Tâm', 'tam.htt@huit.edu.vn', '0994665779', 'Internet vạn vật, Hệ thống nhúng', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', '5f017eba380853e228155f5db08dbb0c'),
(11, 'Ngô Đức Thắng', 'thang.nd@huit.edu.vn', '0970856133', 'Kiểm thử phần mềm, Công nghệ phần mềm', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'b6864ae545f28affa498edc21d3ffe17'),
(12, 'Dương Mỹ Linh', 'linh.dm@huit.edu.vn', '0951009484', 'Điện toán đám mây, DevOps, Linux', '$2y$10$TI9AtrdwcZ9B9ro1ZsbgaeUYvnopUOq18PWyPQ3l3qxyZvoHFOq0G', 'c48979b5b48567c07e654bbac23c72b5');

INSERT INTO schedules (id, teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods) VALUES
(1, 1, 1, 'Lập trình mã nguồn mở', 'A101', 7, '13-15'),
(2, 1, 1, 'Lập trình mã nguồn mở', 'C402', 7, '10-12'),
(3, 1, 3, 'Lập trình web', 'L202', 6, '7-10'),
(4, 2, 2, 'Cơ sở dữ liệu', 'PM01', 4, '2-5'),
(5, 2, 3, 'Hệ quản trị cơ sở dữ liệu', 'L305', 5, '9-11'),
(6, 2, 1, 'Hệ quản trị cơ sở dữ liệu', 'A204', 6, '2-4'),
(7, 2, 2, 'Cơ sở dữ liệu NoSQL', 'PM02', 4, '9-12'),
(8, 2, 2, 'Cơ sở dữ liệu NoSQL', 'PM01', 3, '1-4'),
(9, 2, 2, 'Cơ sở dữ liệu NoSQL', 'PM04', 3, '13-15'),
(10, 3, 2, 'Mạng máy tính', 'PM01', 6, '4-6'),
(11, 3, 1, 'Mạng máy tính', 'C402', 5, '8-11'),
(12, 3, 1, 'Mạng máy tính', 'C402', 5, '4-6'),
(13, 3, 3, 'An ninh mạng', 'L202', 7, '7-10'),
(14, 3, 1, 'An ninh mạng', 'B201', 5, '1-3'),
(15, 3, 3, 'An ninh mạng', 'L201', 7, '4-6'),
(16, 4, 1, 'Trí tuệ nhân tạo', 'B201', 7, '10-12'),
(17, 4, 2, 'Học máy', 'PM01', 5, '2-4'),
(18, 4, 1, 'Học máy', 'B201', 7, '7-9'),
(19, 5, 1, 'Lập trình di động', 'A101', 2, '13-15'),
(20, 5, 2, 'Lập trình di động', 'PM04', 6, '7-10'),
(21, 5, 1, 'Lập trình di động', 'A101', 2, '10-12'),
(22, 6, 3, 'Cấu trúc dữ liệu và giải thuật', 'L305', 7, '10-12'),
(23, 6, 2, 'Cấu trúc dữ liệu và giải thuật', 'PM04', 2, '4-6'),
(24, 6, 3, 'Cấu trúc dữ liệu và giải thuật', 'L305', 7, '1-4'),
(25, 7, 1, 'Hệ điều hành', 'A204', 5, '9-12'),
(26, 7, 1, 'Hệ điều hành', 'B201', 5, '13-15'),
(27, 7, 1, 'Hệ điều hành', 'B201', 6, '3-6'),
(28, 8, 3, 'Phân tích thiết kế hệ thống', 'L305', 7, '13-15'),
(29, 8, 1, 'Phân tích thiết kế hệ thống', 'A101', 6, '3-6'),
(30, 11, 2, 'Công nghệ phần mềm', 'PM03', 5, '2-4'),
(31, 9, 1, 'Nhập môn dữ liệu lớn', 'B201', 3, '3-5'),
(32, 10, 1, 'Internet vạn vật', 'A204', 7, '11-12'),
(33, 11, 3, 'Kiểm thử phần mềm', 'L202', 2, '10-12'),
(34, 11, 1, 'Kiểm thử phần mềm', 'A306', 4, '7-10'),
(35, 11, 2, 'Kiểm thử phần mềm', 'PM03', 5, '8-10'),
(36, 12, 1, 'Điện toán đám mây', 'C402', 6, '10-12'),
(37, 12, 3, 'Điện toán đám mây', 'L201', 7, '1-3'),
(38, 12, 2, 'Điện toán đám mây', 'PM04', 3, '7-9');
