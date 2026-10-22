# BÁO CÁO TRIỂN KHAI — ĐỀ 29: WEBSITE CÔNG THỨC NẤU ĂN

> Điền thông tin học phần/sinh viên/nhóm theo mẫu do giảng viên yêu cầu. Chỉ mô tả là “đã kiểm thử” đối với hạng mục đã chạy thực tế và có ảnh minh chứng.

## 1. Mục tiêu

Triển khai website chia sẻ công thức nấu ăn, lưu trữ món ăn/nguyên liệu/danh mục trên MySQL, truy cập DB bằng phpMyAdmin, đặt Nginx làm reverse proxy và giám sát hệ thống bằng Prometheus/Grafana cùng Loki/LogQL.

## 2. Kiến trúc hệ thống

- **Ứng dụng:** PHP 8.3-FPM, HTML, CSS, JavaScript.
- **Cơ sở dữ liệu:** MySQL 8.4.
- **Quản trị DB:** phpMyAdmin.
- **Reverse proxy:** Nginx.
- **Metrics:** Prometheus, Nginx Prometheus Exporter, MySQL Exporter, endpoint metrics riêng của ứng dụng.
- **Dashboard/log:** Grafana, Loki, Grafana Alloy.
- **Triển khai:** Docker Compose, các mạng frontend/backend/observability và named volumes.

Chèn sơ đồ kiến trúc hoặc ảnh Docker Compose nếu yêu cầu báo cáo.

## 3. Thiết kế cơ sở dữ liệu

| Bảng | Vai trò |
|---|---|
| `categories` | Lưu danh mục món ăn. |
| `recipes` | Lưu tên món, mô tả, ảnh, thời gian, độ khó, khẩu phần, trạng thái và lượt xem. |
| `ingredients` | Danh mục nguyên liệu duy nhất. |
| `recipe_ingredients` | Quan hệ nhiều-nhiều giữa công thức và nguyên liệu, kèm số lượng. |
| `recipe_steps` | Các bước nấu theo thứ tự. |
| `users` | Tài khoản quản trị và vai trò. |

Chèn ảnh phpMyAdmin thể hiện các bảng và dữ liệu thật của dự án.

## 4. Chức năng ứng dụng

Mô tả kết quả kiểm thử tìm kiếm, lọc danh mục, xem chi tiết công thức, đăng nhập admin, thêm/sửa/xóa công thức, thêm/xóa danh mục. Chèn ảnh trước/sau khi thay đổi dữ liệu để chứng minh website và MySQL đồng bộ.

## 5. Triển khai bằng Docker Compose

Ghi phiên bản Docker/Compose và kết quả `docker compose ps`. Chèn ảnh các container cần thiết đang chạy. Ghi chú nếu có service từng lỗi và cách khắc phục thực tế.

## 6. Nginx reverse proxy và Hardening

Mô tả luồng trình duyệt → Nginx → PHP-FPM → MySQL; các cổng chỉ bind localhost; MySQL không publish cổng; các network riêng; `.env` không commit; CSRF token, prepared statements, password hashing, login lock, HTTP security headers và giới hạn request. Chèn ảnh `curl.exe -I http://localhost:8080` và kiểm tra `.gitignore`/`git status`.

## 7. Giám sát bằng Prometheus/Grafana

Chèn ảnh trang Prometheus Targets và dashboard `Recipe DE29 - System Overview`. Nêu kết quả các truy vấn `up`, `recipe_recipes_total`, `mysql_up`, `nginx_connections_active`.

## 8. Thu thập log bằng Loki/LogQL

Giải thích Nginx access/error logs và JSON app logs được Grafana Alloy chuyển đến Loki. Chèn ảnh Explore của Grafana có kết quả từ ít nhất một truy vấn LogQL, ví dụ `{job="nginx", log_type="access"}` hoặc `{job="recipe-php", level="INFO"}`.

## 9. Kết quả kiểm thử và hạn chế

Ghi bảng test case, bước thực hiện, kết quả mong đợi, kết quả thực tế và trạng thái đạt/chưa đạt. Không tự điền kết quả “Đạt” trước khi chạy thử.

## 10. Kết luận

Tóm tắt các yêu cầu đã hoàn thành, các ảnh minh chứng và hướng mở rộng.
