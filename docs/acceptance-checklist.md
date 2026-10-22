# Checklist nghiệm thu — Đề 29 Recipe

Đánh dấu từng mục sau khi tự chạy hệ thống trên Docker Desktop. Không đánh dấu “Đạt” chỉ vì file cấu hình đã tồn tại; cần kiểm tra đầu ra thực tế.

## A. Chức năng website

- [ ] Trang chủ trả HTTP 200 qua Nginx tại `http://localhost:8080`.
- [ ] Danh sách công thức hiển thị dữ liệu lấy từ MySQL.
- [ ] Tìm kiếm và lọc danh mục trả đúng kết quả.
- [ ] Trang chi tiết hiển thị nguyên liệu và bước nấu từ quan hệ DB.
- [ ] Admin đăng nhập đúng; đăng nhập sai không tiết lộ email có tồn tại hay không.
- [ ] Thêm công thức mới và chọn trạng thái bản nháp: không xuất hiện công khai.
- [ ] Đổi sang “Đã xuất bản”: công thức xuất hiện trên website.
- [ ] Sửa và xóa công thức hoạt động; quan hệ nguyên liệu/bước nấu được cập nhật.
- [ ] Tạo danh mục thành công; không thể xóa danh mục đang được công thức tham chiếu.

## B. Cơ sở dữ liệu

- [ ] `docker compose ps` cho thấy `recipe_v2_mysql` healthy.
- [ ] phpMyAdmin đăng nhập được bằng `MYSQL_USER` và `MYSQL_PASSWORD`.
- [ ] Có các bảng `categories`, `recipes`, `ingredients`, `recipe_ingredients`, `recipe_steps`, `users`.
- [ ] Thử lệnh SQL `SELECT COUNT(*) FROM recipes;` và xác nhận số dòng tương ứng với admin.
- [ ] MySQL không có cổng 3306 publish trực tiếp ra máy host.

## C. Reverse proxy và hardening

- [ ] Website được truy cập qua Nginx, PHP-FPM chỉ expose nội bộ trong Docker network.
- [ ] `curl.exe -I http://localhost:8080` trả các header `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- [ ] `/health` trả `ok`.
- [ ] `/metrics` chỉ cho phép Prometheus (`172.30.240.10`); truy cập từ trình duyệt host nhận HTTP 403.
- [ ] `/nginx_status` chỉ cho phép Nginx Exporter (`172.30.240.11`); truy cập từ máy host bị từ chối.
- [ ] File `.env` không xuất hiện trong `git status`.
- [ ] Login dùng `password_hash/password_verify`, các biểu mẫu POST dùng CSRF token, SQL sử dụng prepared statements.

## D. Prometheus + Grafana

- [ ] `http://localhost:9090/targets` hiển thị các scrape job `prometheus`, `recipe-app`, `nginx`, `mysql`.
- [ ] Các target cần thiết ở trạng thái `UP`.
- [ ] Truy vấn `recipe_recipes_total` trả số lượng công thức trong DB.
- [ ] Truy vấn `mysql_up` trả `1`.
- [ ] Truy vấn `nginx_connections_active` trả một chuỗi metric.
- [ ] Grafana hiển thị datasource Prometheus và Loki; dashboard `Recipe DE29 - System Overview` tải được.

## E. Loki + LogQL

- [ ] Mở trang công khai và xem ít nhất một chi tiết công thức để sinh log.
- [ ] Alloy và Loki đang chạy ổn định.
- [ ] `{job="nginx", log_type="access"}` trả log truy cập.
- [ ] `{job="recipe-php", level="INFO"}` trả log sự kiện PHP nếu đã tạo log sự kiện.
- [ ] `{job="nginx", status="404"}` hoạt động sau khi phát sinh request không tồn tại (nếu log có 404).

## F. Nộp bài

- [ ] Đã push source lên repository GitHub.
- [ ] README chứa cách cài đặt, danh sách service, truy cập và lệnh kiểm tra.
- [ ] Đã chụp ảnh minh chứng từ website, phpMyAdmin, `docker compose ps`, Prometheus Targets, dashboard Grafana và LogQL.
- [ ] Báo cáo ghi đúng các kiểm thử đã chạy thực tế và nêu rõ mọi giới hạn còn lại.
