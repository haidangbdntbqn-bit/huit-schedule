# HUIT Schedule Hub

> Web App quản lý và phân lịch giảng dạy **đa cơ sở** thông minh cho Phòng Đào tạo và Giảng viên HUIT.

![PHP Lint](https://github.com/phantananh/huit-schedule/actions/workflows/php-lint.yml/badge.svg)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)

## Tính năng

| Nhóm | Mô tả |
|---|---|
| **CRUD** | Quản lý Cơ sở, Giảng viên, Lịch dạy (thêm / sửa / xóa / tìm kiếm / lọc). |
| **Phân lịch theo tiết trống** | Lưới thời khóa biểu 15 tiết × 7 ngày theo giảng viên hoặc theo phòng; bấm vào ô trống để xếp lịch ngay vào tiết đó. |
| **Kiểm tra & tối ưu** | Chặn trùng lịch giảng viên, trùng phòng; chặn đổi cơ sở khi không đủ thời gian di chuyển (`TRAVEL_GAP_PERIODS`); cảnh báo **khoảng trống chết** (`DEAD_GAP_PERIODS`) và quá tải tiết/ngày. Kiểm tra trực tiếp (AJAX) khi nhập form. |
| **Gợi ý giảng viên** | Khi thêm lớp mới, xếp hạng giảng viên theo độ khớp chuyên môn, loại người bị trùng lịch/không kịp di chuyển, ưu tiên người đã dạy cùng cơ sở trong ngày, có thể dạy nối tiếp và đang ít tiết (cân bằng tải). |
| **Gợi ý khung giờ** | Tìm các tiết trống tối ưu cho một giảng viên tại một cơ sở: ưu tiên nối tiếp lớp sẵn có, tránh phải di chuyển, tránh tạo lỗ hổng giữa buổi. |
| **Cổng Giảng viên** | Giảng viên đăng nhập (email + mật khẩu) hoặc dùng **đường dẫn riêng** `portal.php?token=...` để xem lịch cá nhân theo **tuần / tháng**, danh sách cơ sở cần đến và lộ trình từng ngày. Giảng viên chỉ xem được lịch của chính mình. |
| **Tổng quan** | Lọc nhanh theo Cơ sở / Giảng viên; biểu đồ phân bổ tiết theo ngày–cơ sở, theo giảng viên, theo buổi; rà soát xung đột toàn hệ thống. |

## Công nghệ

- PHP ≥ 8.1 (PDO MySQL), MySQL / MariaDB
- Composer: [`vlucas/phpdotenv`](https://packagist.org/packages/vlucas/phpdotenv) (đọc `.env`), [`fakerphp/faker`](https://packagist.org/packages/fakerphp/faker) (sinh dữ liệu mẫu)
- Bootstrap 5, Chart.js (CDN)
- GitHub Actions: kiểm tra cú pháp `php -l` mỗi lần push

## Cấu trúc thư mục

```
huit-schedule/
├── .github/workflows/php-lint.yml   # CI: php -l
├── api/suggest.php                  # API JSON: kiểm tra / gợi ý GV / gợi ý tiết trống
├── assets/                          # CSS, JS
├── database/huit_db.sql             # Cấu trúc bảng + dữ liệu mẫu
├── includes/
│   ├── config.php                   # Nạp Composer autoload + phpdotenv
│   ├── db.php                       # Kết nối PDO
│   ├── auth.php                     # Đăng nhập & phân quyền (admin / teacher)
│   ├── scheduler.php                # Thuật toán xếp lịch, kiểm tra, gợi ý
│   └── seeder.php                   # Sinh dữ liệu mẫu với Faker
├── index.php                        # Tổng quan + biểu đồ
├── schedules.php / schedule_form.php# Danh sách & phân lịch
├── free_slots.php                   # Lưới tiết trống
├── teachers.php / branches.php      # Quản lý giảng viên, cơ sở
├── portal.php                       # Cổng thông tin giảng viên
├── seed.php                         # php seed.php -> sinh dữ liệu Faker
├── .env.example  composer.json  LICENSE  README.md
```

## Cài đặt & chạy

### 1. Lấy mã nguồn

```bash
# Laragon (Windows)
cd C:\laragon\www
# MAMP (macOS)
cd /Applications/MAMP/htdocs

git clone https://github.com/phantananh/huit-schedule.git
cd huit-schedule
```

### 2. Cài thư viện bằng Composer

```bash
composer install
```

> Chưa có Composer trên macOS: `brew install composer` hoặc tải tại <https://getcomposer.org>.

### 3. Cấu hình file `.env`

```bash
cp .env.example .env        # Windows: copy .env.example .env
```

Sửa thông số kết nối cho đúng môi trường:

| Môi trường | DB_HOST | DB_PORT | DB_USER | DB_PASS |
|---|---|---|---|---|
| Laragon / XAMPP | localhost | 3306 | root | *(để trống)* |
| MAMP (macOS) | localhost | **8889** | root | **root** |

Các biến khác: `ADMIN_EMAIL`, `ADMIN_PASSWORD` (tài khoản Phòng Đào tạo), `TEACHER_DEFAULT_PASSWORD`, và tham số thuật toán `TRAVEL_GAP_PERIODS`, `DEAD_GAP_PERIODS`, `MAX_PERIODS_PER_DAY`, `ALLOW_SUNDAY`.

### 4. Import cơ sở dữ liệu mẫu

File `database/huit_db.sql` tự tạo database `huit_db`, 3 bảng và dữ liệu mẫu (3 cơ sở, 12 giảng viên, 38 lịch dạy hợp lệ).

- **HeidiSQL (Laragon):** File → Load SQL file… → chọn `database/huit_db.sql` → Execute (F9).
- **phpMyAdmin (MAMP: <http://localhost:8888/phpMyAdmin>):** tab *Import* → chọn file → *Go*.
- **Dòng lệnh:**

  ```bash
  mysql -u root -p < database/huit_db.sql
  # MAMP:
  /Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -proot -P 8889 -h 127.0.0.1 < database/huit_db.sql
  ```

  *(Tên thư mục `mysql80`/`mysql57` tùy phiên bản MAMP.)*

Muốn sinh dữ liệu ngẫu nhiên mới bằng Faker (xóa dữ liệu cũ):

```bash
php seed.php 15      # 15 giảng viên
```

hoặc đăng nhập quản trị rồi mở `/seed.php`.

### 5. Chạy ứng dụng

- **Laragon:** đặt thư mục trong `laragon\www` → *Menu → Apache → Reload*; Laragon tự tạo virtual host **http://huit-schedule.test**.
- **MAMP:** Document root = `/Applications/MAMP/htdocs` → bấm *Start* → mở **http://localhost:8888/huit-schedule/**.
  (Muốn dùng tên miền `huit-schedule.test` với MAMP bản thường: thêm `127.0.0.1 huit-schedule.test` vào `/etc/hosts` và khai báo VirtualHost trong `/Applications/MAMP/conf/apache/extra/httpd-vhosts.conf`.)

### 6. Tài khoản đăng nhập

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Phòng Đào tạo (admin) | `admin@huit.edu.vn` | `admin123` *(đổi trong `.env`)* |
| Giảng viên (dữ liệu mẫu) | `an.nv@huit.edu.vn`, `ngoc.ttb@huit.edu.vn`, … | `123456` |

Đường dẫn riêng của từng giảng viên: trang **Giảng viên** → nút <kbd>🔗</kbd> để sao chép.

## Quy ước dữ liệu

- `schedules.day_of_week`: `2` = Thứ 2 … `7` = Thứ 7, `8` = Chủ nhật.
- `schedules.shift_or_periods`: `"tiết_đầu-tiết_cuối"`, ví dụ `"1-3"`. Buổi Sáng = tiết 1–6, Chiều = 7–12, Tối = 13–15; một lớp phải nằm gọn trong một buổi.

## Thuật toán (tóm tắt)

1. **Kiểm tra lịch** (`check_schedule`): trùng giảng viên / trùng phòng → *lỗi*; đổi cơ sở trong cùng buổi mà khoảng cách < `TRAVEL_GAP_PERIODS` tiết → *lỗi*; khoảng trống ≥ `DEAD_GAP_PERIODS` tiết trong cùng buổi, đổi cơ sở trong ngày, vượt `MAX_PERIODS_PER_DAY` → *cảnh báo*.
2. **Gợi ý giảng viên** (`suggest_teachers`): điểm = 60 × độ khớp chuyên môn + 15 (đã dạy cùng cơ sở trong ngày) + 10 (nối tiếp liền mạch) − 15 (phải sang cơ sở khác) − 8 × số cảnh báo − 0,8 × số tiết/tuần hiện có.
3. **Gợi ý tiết trống** (`suggest_slots`): duyệt mọi (thứ, tiết bắt đầu) hợp lệ, bỏ phương án có lỗi, chấm điểm ưu tiên cùng cơ sở + nối tiếp, phạt di chuyển, khoảng trống chết và buổi tối.

## CI/CD

Workflow `.github/workflows/php-lint.yml` chạy trên mỗi `git push` / pull request: cài PHP 8.1–8.3, `composer validate`, `composer install`, rồi `php -l` toàn bộ file `.php` (trừ `vendor/`).

## Đẩy mã lên GitHub

```bash
git init
git add .
git commit -m "Initial commit: HUIT Schedule Hub"
git branch -M main
git remote add origin https://github.com/phantananh/huit-schedule.git
git push -u origin main
```

`.gitignore` đã loại trừ `vendor/` và `.env`.

## Giấy phép

Phát hành theo giấy phép [MIT](LICENSE).
