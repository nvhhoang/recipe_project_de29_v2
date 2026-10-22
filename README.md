# Bếp Nhà — Đề 29: Website Công thức Nấu ăn / Recipe

Dự án PHP + MySQL được triển khai bằng Docker Compose, có phpMyAdmin, Nginx reverse proxy, Prometheus, Grafana, Loki và Grafana Alloy. Source được tổ chức để dùng cho bài thực hành **Triển khai và Quản trị Hệ thống Phần mềm**.

> **Lưu ý:** Tên đăng nhập/mật khẩu phải được cấu hình trong `.env` trước lần khởi chạy đầu tiên. Không commit file `.env` lên GitHub. Bộ mã đã kiểm tra cú pháp PHP, YAML và JSON tại môi trường tạo gói; cần chạy Docker trên máy của bạn để xác nhận các container và dashboard hoạt động thực tế.

## 1. Đối chiếu yêu cầu đề bài

| Yêu cầu | Thành phần triển khai |
|---|---|
| Website chia sẻ công thức | `app/public/index.php`: trang chủ, tìm kiếm, lọc danh mục, chi tiết món, nguyên liệu và các bước nấu; dữ liệu đọc trực tiếp từ MySQL. |
| MySQL | `mysql/init.sql`: bảng `categories`, `recipes`, `ingredients`, `recipe_ingredients`, `recipe_steps`, `users` và dữ liệu mẫu. |
| phpMyAdmin | Dịch vụ `phpmyadmin`, chỉ bind ở `127.0.0.1:8083`. |
| GitHub | `.gitignore` loại trừ bí mật, README và cấu trúc sẵn sàng đưa lên GitHub. Dự án được quản lý tại https://github.com/nvhhoang/recipe_project_de29_v2. |
| Nginx reverse proxy | `nginx/default.conf`: Nginx nhận HTTP, chuyển PHP đến PHP-FPM, giới hạn kích thước request và thêm security headers. |
| Prometheus + Grafana | Prometheus thu thập metrics từ app, Nginx exporter và MySQL exporter; Grafana có datasource và dashboard được provision sẵn. |
| Loki + LogQL | Loki nhận log qua Grafana Alloy; log gồm Nginx access/error và log sự kiện ứng dụng PHP dạng JSON. Truy vấn bằng LogQL trong Grafana Explore. |
| Hardening | Các cổng bind localhost; MySQL không publish ra host; backend/observability network tách riêng; `.env` không commit; tài khoản exporter có quyền đọc; CSRF, prepared statements, HTML escaping, mật khẩu hash, giới hạn đăng nhập và security headers. |

## 2. Yêu cầu trước khi chạy

- Docker Desktop khởi động và Docker Engine ở trạng thái Running.
- Git và PowerShell trên Windows.
- VS Code (khuyến nghị).
- Máy có thể tải image Docker lần đầu và trình duyệt có internet nếu muốn tải ảnh/font từ nguồn ngoài.

## 3. Cấu hình bảo mật lần đầu

Mở PowerShell tại thư mục `recipe_project_de29_v2`:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
notepad .env
```

Lệnh trên giữ nguyên `.env` nếu bạn đã có; không ghi đè mật khẩu đang dùng. Thay tất cả giá trị `REPLACE_WITH_...` bằng mật khẩu riêng, dài tối thiểu 20 ký tự, ưu tiên chữ và số để tránh lỗi escape khi khởi tạo MySQL exporter. Mỗi dịch vụ nên có mật khẩu khác nhau. Riêng `MYSQLD_EXPORTER_PASSWORD` cũng phải là một mật khẩu mới do bạn chọn; dùng cùng giá trị trong `.env` cho MySQL và exporter (Compose đã truyền biến này cho cả hai). Lưu file `.env` và đóng Notepad. **Phải chọn mật khẩu trước lần chạy đầu tiên**: MySQL giữ mật khẩu trong volume và không tự đổi mật khẩu của tài khoản đã tạo khi bạn sửa `.env` sau đó.

Không gửi file `.env` lên GitHub và không chụp ảnh để lộ các mật khẩu.

## 4. Kiểm tra cấu hình và khởi chạy

Trong cùng cửa sổ PowerShell, chạy từng lệnh:

```powershell
docker --version
docker compose version
docker compose config
```

Nếu Docker báo không kết nối được `dockerDesktopLinuxEngine`, hãy mở Docker Desktop và chờ engine khởi động xong. Nếu `docker compose config` không báo lỗi, tiếp tục:

```powershell
docker compose up -d --build
docker compose ps
```

Xem log nếu service chưa chạy ổn định:

```powershell
docker compose logs --tail=100
```

Các container dùng tiền tố `recipe_v2_` để không đụng tên container của dự án Recipe cũ.

## 5. Các địa chỉ sau khi chạy

| Thành phần | URL | Đăng nhập |
|---|---|---|
| Website | http://localhost:8082 | Không bắt buộc |
| Quản trị Recipe | http://localhost:8082/admin.php | Email và mật khẩu lấy từ `ADMIN_EMAIL` / `ADMIN_PASSWORD` trong `.env` (được khởi tạo ở lần kết nối DB đầu tiên) |
| phpMyAdmin | http://localhost:8083 | Server `mysql`, user `MYSQL_USER`, mật khẩu `MYSQL_PASSWORD` |
| Prometheus | http://localhost:9091 | Chỉ mở trên máy local |
| Grafana | http://localhost:3001 | `GRAFANA_USER` / `GRAFANA_PASSWORD` trong `.env` |

Các cổng trên được bind vào `127.0.0.1` để chỉ máy local truy cập. Không publish trực tiếp MySQL, Loki, Alloy hay các exporter ra host.

## 6. Chức năng website và quản trị

### Website công khai

- Hiển thị các công thức ở trạng thái `published`.
- Tìm kiếm theo tên, mô tả hoặc danh mục.
- Lọc theo danh mục.
- Xem chi tiết: thời gian chuẩn bị/nấu, độ khó, khẩu phần, danh sách nguyên liệu và hướng dẫn từng bước.
- Số lượt xem chi tiết được lưu trong MySQL.

### Admin

- Đăng nhập bằng tài khoản admin được khởi tạo từ biến môi trường.
- Xem các chỉ số thật lấy từ MySQL.
- Thêm/sửa/xóa công thức; chọn danh mục; đặt trạng thái bản nháp/đã xuất bản.
- Quản lý danh mục và không cho xóa danh mục đang được công thức sử dụng.
- Mỗi dòng nguyên liệu nhập theo định dạng `Tên nguyên liệu | Số lượng`. Mỗi dòng ở phần cách nấu tương ứng với một bước.
- Các thao tác POST đều kiểm tra CSRF. Dữ liệu truy vấn sử dụng prepared statements và nội dung HTML được escape.

### Dữ liệu mẫu

`mysql/init.sql` tự tạo danh mục, công thức, nguyên liệu và các quan hệ khi MySQL khởi tạo **volume dữ liệu trống lần đầu**. Script init không tự chạy lại khi volume cũ còn dữ liệu.

## 7. Kiểm tra Prometheus

Mở `http://localhost:9091/targets`. Các target chính cần ở trạng thái **UP**:

- `prometheus`: chính Prometheus.
- `recipe-app`: endpoint `/metrics` của ứng dụng; Nginx chỉ cho phép IP nội bộ cố định của Prometheus (`172.30.240.10`). Truy cập từ trình duyệt host phải bị từ chối.
- `nginx`: Nginx Prometheus Exporter đọc `/nginx_status`.
- `mysql`: MySQL Exporter dùng tài khoản riêng `metrics_exporter` chỉ có quyền cần thiết để thu thập metrics.

Thử các biểu thức tại trang Graph:

```promql
up
recipe_recipes_total
recipe_published_recipes_total
recipe_categories_total
mysql_up
nginx_connections_active
mysql_global_status_threads_connected
```

Dashboard `Recipe DE29 - System Overview` được cấu hình sẵn trong Grafana, thư mục `Recipe Project`.

## 8. Kiểm tra Loki và LogQL

Trong Grafana, mở **Explore → Loki**. Truy vấn ví dụ:

```logql
{job="nginx", log_type="access"}
```

Lọc các request HTTP 404:

```logql
{job="nginx", status="404"}
```

Xem cảnh báo hoặc lỗi ứng dụng PHP:

```logql
{job="recipe-php", level="WARNING"}
{job="recipe-php", level="ERROR"}
```

Để tạo log minh chứng, mở website, tìm kiếm món ăn, xem chi tiết và thử đăng nhập admin sai một lần. Alloy đọc file log Nginx và file JSON của PHP rồi gửi đến Loki. Loki được cấu hình giữ log trong khoảng 7 ngày.

> Promtail đã hết vòng đời hỗ trợ ngày 2/3/2026; dự án dùng Grafana Alloy thay thế để thu thập log, còn Loki và LogQL giữ vai trò lưu trữ/truy vấn log như yêu cầu đề bài. Tham khảo tài liệu Grafana: https://grafana.com/docs/alloy/latest/set-up/migrate/from-promtail/ .

## 9. Kiểm thử nhanh trước khi chụp minh chứng

Chạy trên PowerShell:

```powershell
# Tất cả dịch vụ và trạng thái
docker compose ps

# Kiểm tra HTTP 200 và security headers
curl.exe -I http://localhost:8082

# Health endpoint
curl.exe -i http://localhost:8082/health

# Kiểm tra endpoint metrics từ máy host có thể bị chặn bởi Nginx (đây là chủ đích); Prometheus mới là client nội bộ
curl.exe -i http://localhost:8082/metrics

# Kiểm tra log liên quan đến app/Nginx
docker compose logs --tail=100 app nginx alloy loki prometheus grafana
```

Endpoint `/metrics` chỉ cho phép Prometheus tại IP Docker cố định `172.30.240.10`; `/nginx_status` chỉ cho phép Nginx Exporter tại `172.30.240.11`. Truy cập trực tiếp từ trình duyệt host phải bị từ chối (HTTP 403). Hãy kiểm tra metrics qua Prometheus.

## 10. Git và GitHub

Repository của dự án: https://github.com/nvhhoang/recipe_project_de29_v2

Kiểm tra các thay đổi trước khi commit:

```powershell
git status --short
git diff --check
git check-ignore .env
```

Không đưa `.env`, mật khẩu, log hoặc các file backup vào Git. Chỉ thêm những file dự án cần cập nhật sau khi đã kiểm tra danh sách:

```powershell
git add README.md
git diff --cached --check
git diff --cached
```

Nếu nội dung đã đúng, tạo commit và đẩy lên repository hiện có:

```powershell
git commit -m "Update Recipe project README"
git push origin main
```

Nếu Git báo không có gì để commit, kiểm tra `git status --short`; không cần tạo commit mới. Nếu nhánh hiện tại không phải `main`, kiểm tra bằng `git branch --show-current` trước khi push.

Lưu ý bảo mật:

- `.env` chứa cấu hình và mật khẩu riêng, không được commit.
- `.env.example` chỉ chứa giá trị mẫu, không chứa mật khẩu thật.
- Kiểm tra `git status --short` và `git diff --cached` trước khi commit.

## 11. Dừng và đặt lại dữ liệu

Dừng các container nhưng giữ dữ liệu MySQL/Grafana/Loki:

```powershell
docker compose down
```

**Cảnh báo:** lệnh dưới sẽ xóa các Docker volume, gồm toàn bộ dữ liệu MySQL và lịch sử Grafana/Loki. Chỉ dùng khi muốn khởi động sạch từ đầu:

```powershell
docker compose down -v
docker compose up -d --build
```

## 12. Bộ minh chứng nên đưa vào báo cáo

1. Trang chủ website và trang chi tiết công thức có nguyên liệu/các bước.
2. Admin dashboard và thao tác thêm/sửa/xóa một công thức.
3. phpMyAdmin hiển thị các bảng `recipes`, `categories`, `ingredients`, `recipe_ingredients`, `recipe_steps`, `users`.
4. `docker compose ps` cho thấy các service đang chạy.
5. Prometheus `/targets` với các job ở trạng thái UP và biểu thức `recipe_recipes_total`.
6. Dashboard Grafana `Recipe DE29 - System Overview`.
7. Grafana Explore/Loki với ít nhất một truy vấn LogQL có log trả về.
8. HTTP headers của Nginx và kết quả `git status`/repo GitHub.
