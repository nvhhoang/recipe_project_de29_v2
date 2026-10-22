<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';

$pdo = null;
$flash = '';
$flashType = 'success';
$action = (string)($_GET['action'] ?? '');

function redirect_admin(string $query = ''): never
{
    header('Location: /admin.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

function normalized_lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn($line) => $line !== ''));
}

function unique_recipe_slug(PDO $pdo, string $title, int $id = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $n = 2;
    while (true) {
        $sql = 'SELECT id FROM recipes WHERE slug = ?' . ($id > 0 ? ' AND id <> ?' : '') . ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $id > 0 ? $stmt->execute([$slug, $id]) : $stmt->execute([$slug]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $postAction = (string)($_POST['action'] ?? '');

        if ($postAction === 'login') {
            $now = time();
            if ((int)($_SESSION['login_locked_until'] ?? 0) > $now) {
                $remaining = (int)$_SESSION['login_locked_until'] - $now;
                $flash = 'Tài khoản tạm khóa do đăng nhập sai nhiều lần. Hãy thử lại sau khoảng ' . max(1, (int)ceil($remaining / 60)) . ' phút.';
                $flashType = 'error';
            } else {
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $password = (string)($_POST['password'] ?? '');
                $stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? AND role = 'admin' AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_user_id'] = (int)$user['id'];
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['login_locked_until']);
                    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
                    app_log('INFO', 'Administrator login succeeded', ['user_id' => (int)$user['id']]);
                    redirect_admin();
                }
                $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
                app_log('WARNING', 'Administrator login failed', ['email' => $email, 'attempt' => (int)$_SESSION['login_attempts']]);
                if ((int)$_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = $now + 900;
                    $_SESSION['login_attempts'] = 0;
                    $flash = 'Đăng nhập sai 5 lần. Phiên này bị khóa đăng nhập trong 15 phút.';
                } else {
                    $flash = 'Email hoặc mật khẩu không đúng.';
                }
                $flashType = 'error';
            }
        } elseif ($postAction === 'logout') {
            app_log('INFO', 'Administrator logged out', ['user_id' => (int)($_SESSION['admin_user_id'] ?? 0)]);
            unset($_SESSION['admin_user_id']);
            session_regenerate_id(true);
            redirect_admin();
        } else {
            $admin = require_admin();
            if ($postAction === 'save_recipe') {
                $id = max(0, (int)($_POST['id'] ?? 0));
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $categoryId = max(0, (int)($_POST['category_id'] ?? 0));
                $image = trim((string)($_POST['image_url'] ?? ''));
                $prep = filter_var($_POST['prep_minutes'] ?? 0, FILTER_VALIDATE_INT);
                $cook = filter_var($_POST['cook_minutes'] ?? 0, FILTER_VALIDATE_INT);
                $servings = filter_var($_POST['servings'] ?? 2, FILTER_VALIDATE_INT);
                $difficulty = (string)($_POST['difficulty'] ?? 'Dễ');
                $status = (string)($_POST['status'] ?? 'draft');
                $ingredientLines = normalized_lines((string)($_POST['ingredients'] ?? ''));
                $stepLines = normalized_lines((string)($_POST['steps'] ?? ''));
                if ($title === '' || mb_strlen($title) > 180 || $description === '' || mb_strlen($description) > 5000 || $categoryId < 1) {
                    throw new InvalidArgumentException('Tên món, mô tả và danh mục là bắt buộc. Tên món tối đa 180 ký tự, mô tả tối đa 5000 ký tự.');
                }
                if ($prep === false || $cook === false || $servings === false || $prep < 0 || $cook < 0 || $prep > 1440 || $cook > 1440 || $servings < 1 || $servings > 30) {
                    throw new InvalidArgumentException('Thời gian phải từ 0 đến 1440 phút và khẩu phần từ 1 đến 30 người.');
                }
                if (!in_array($difficulty, ['Dễ', 'Trung bình', 'Khó'], true) || !in_array($status, ['draft', 'published'], true)) {
                    throw new InvalidArgumentException('Độ khó hoặc trạng thái không hợp lệ.');
                }
                if ($image !== '' && (mb_strlen($image) > 500 || !filter_var($image, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                    throw new InvalidArgumentException('Đường dẫn ảnh phải là URL HTTP/HTTPS hợp lệ, tối đa 500 ký tự.');
                }
                if (count($ingredientLines) === 0 || count($stepLines) === 0) {
                    throw new InvalidArgumentException('Hãy nhập ít nhất một nguyên liệu và một bước nấu.');
                }
                if (count($ingredientLines) > 100 || count($stepLines) > 100) {
                    throw new InvalidArgumentException('Mỗi công thức tối đa 100 nguyên liệu và 100 bước nấu.');
                }
                $categoryCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
                $categoryCheck->execute([$categoryId]);
                if (!$categoryCheck->fetch()) throw new InvalidArgumentException('Danh mục đã chọn không tồn tại.');
                $slug = unique_recipe_slug($pdo, $title, $id);
                $pdo->beginTransaction();
                if ($id > 0) {
                    $check = $pdo->prepare('SELECT id FROM recipes WHERE id = ?');
                    $check->execute([$id]);
                    if (!$check->fetch()) throw new InvalidArgumentException('Không tìm thấy công thức cần sửa.');
                    $stmt = $pdo->prepare('UPDATE recipes SET category_id=?, title=?, slug=?, description=?, image_url=?, prep_minutes=?, cook_minutes=?, difficulty=?, servings=?, status=? WHERE id=?');
                    $stmt->execute([$categoryId, $title, $slug, $description, $image !== '' ? $image : null, $prep, $cook, $difficulty, $servings, $status, $id]);
                    $recipeId = $id;
                    $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
                    $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$recipeId]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO recipes (category_id,title,slug,description,image_url,prep_minutes,cook_minutes,difficulty,servings,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$categoryId, $title, $slug, $description, $image !== '' ? $image : null, $prep, $cook, $difficulty, $servings, $status]);
                    $recipeId = (int)$pdo->lastInsertId();
                }
                $ingredientStmt = $pdo->prepare('INSERT INTO ingredients (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
                $relationStmt = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity) VALUES (?, ?, ?)');
                foreach ($ingredientLines as $line) {
                    $parts = array_map('trim', explode('|', $line, 2));
                    $ingredientName = trim($parts[0] ?? '');
                    $quantity = trim($parts[1] ?? 'Vừa đủ');
                    if ($ingredientName === '') continue;
                    if (mb_strlen($ingredientName) > 150 || mb_strlen($quantity) > 100) throw new InvalidArgumentException('Tên nguyên liệu tối đa 150 ký tự và định lượng tối đa 100 ký tự.');
                    $ingredientStmt->execute([$ingredientName]);
                    $ingredientId = (int)$pdo->lastInsertId();
                    $relationStmt->execute([$recipeId, $ingredientId, $quantity !== '' ? $quantity : 'Vừa đủ']);
                }
                $stepStmt = $pdo->prepare('INSERT INTO recipe_steps (recipe_id, step_number, instruction) VALUES (?, ?, ?)');
                foreach ($stepLines as $index => $step) {
                    if (mb_strlen($step) > 5000) throw new InvalidArgumentException('Mỗi bước nấu không được quá 5000 ký tự.');
                    $stepStmt->execute([$recipeId, $index + 1, $step]);
                }
                $pdo->commit();
                app_log('INFO', 'Recipe saved', ['recipe_id' => $recipeId, 'title' => $title, 'status' => $status, 'admin_id' => (int)$admin['id']]);
                redirect_admin('notice=saved');
            }
            if ($postAction === 'delete_recipe') {
                $id = max(0, (int)($_POST['id'] ?? 0));
                $stmt = $pdo->prepare('DELETE FROM recipes WHERE id = ?');
                $stmt->execute([$id]);
                app_log('INFO', 'Recipe deleted', ['recipe_id' => $id, 'admin_id' => (int)$admin['id']]);
                redirect_admin('notice=deleted');
            }
            if ($postAction === 'add_category') {
                $name = trim((string)($_POST['name'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                if ($name === '' || mb_strlen($name) > 100) throw new InvalidArgumentException('Tên danh mục là bắt buộc và tối đa 100 ký tự.');
                $base = slugify($name);
                $slug = $base;
                $suffix = 2;
                $check = $pdo->prepare('SELECT id FROM categories WHERE slug = ? OR name = ? LIMIT 1');
                while (true) {
                    $check->execute([$slug, $name]);
                    if (!$check->fetch()) break;
                    $slug = $base . '-' . $suffix++;
                    if ($suffix > 100) throw new RuntimeException('Không tạo được slug cho danh mục.');
                }
                $pdo->prepare('INSERT INTO categories (name,slug,description) VALUES (?,?,?)')->execute([$name, $slug, $description !== '' ? $description : null]);
                app_log('INFO', 'Category created', ['name' => $name, 'admin_id' => (int)$admin['id']]);
                redirect_admin('notice=category_added');
            }
            if ($postAction === 'delete_category') {
                $id = max(0, (int)($_POST['id'] ?? 0));
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM recipes WHERE category_id = ?');
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) throw new InvalidArgumentException('Không thể xóa danh mục đang có công thức. Hãy chuyển công thức sang danh mục khác trước.');
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                app_log('INFO', 'Category deleted', ['category_id' => $id, 'admin_id' => (int)$admin['id']]);
                redirect_admin('notice=category_deleted');
            }
            throw new InvalidArgumentException('Thao tác không được hỗ trợ.');
        }
    }
} catch (Throwable $ex) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $flash = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Không thể hoàn thành thao tác. Kiểm tra dữ liệu và nhật ký ứng dụng.';
    $flashType = 'error';
    app_log('ERROR', 'Admin request failed', ['error' => $ex->getMessage(), 'action' => $action ?: (string)($_POST['action'] ?? '')]);
    if (in_array((string)($_POST['action'] ?? ''), ['save_recipe'], true)) $action = ((int)($_POST['id'] ?? 0)) > 0 ? 'edit' : 'new';
}

$isLoggedIn = !empty($_SESSION['admin_user_id']);
if ($isLoggedIn) {
    try { $admin = require_admin(); } catch (Throwable $ex) { $isLoggedIn = false; }
}
if (!$isLoggedIn) {
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Đăng nhập quản trị | Bếp Nhà</title><link rel="stylesheet" href="/css/admin-crud.css"></head><body class="login-page"><main class="login-card"><a class="login-brand" href="/"><span>✳</span><strong>Bếp Nhà<small>ADMIN WORKSPACE</small></strong></a><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Chào mừng trở lại</h1><p class="muted">Đăng nhập để quản lý công thức, nguyên liệu và danh mục.</p><?php if ($flash !== ''): ?><div class="flash <?=e($flashType)?>"><?=e($flash)?></div><?php endif; ?><form method="post" action="/admin.php" class="stack-form"><?=csrf_field()?><input type="hidden" name="action" value="login"><label>Email quản trị<input type="email" name="email" autocomplete="username" required maxlength="190" placeholder="admin@bepnha.local"></label><label>Mật khẩu<input type="password" name="password" autocomplete="current-password" required></label><button class="btn-primary full" type="submit">Đăng nhập <span>→</span></button></form><p class="login-foot">Tài khoản quản trị được khởi tạo lần đầu từ biến môi trường trong file <code>.env</code>.</p><a href="/" class="back-link">← Quay lại website</a></main></body></html><?php
    exit;
}

$admin = require_admin();
if ($flash === '' && isset($_GET['notice'])) {
    $flash = match ((string)$_GET['notice']) {
        'saved' => 'Đã lưu công thức thành công vào MySQL.',
        'deleted' => 'Đã xóa công thức.',
        'category_added' => 'Đã tạo danh mục.',
        'category_deleted' => 'Đã xóa danh mục.',
        default => '',
    };
}

$categories = $pdo->query('SELECT c.id,c.name,c.slug,c.description,COUNT(r.id) recipe_count FROM categories c LEFT JOIN recipes r ON r.category_id=c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
$stats = [
    'recipes' => (int)$pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn(),
    'published' => (int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE status='published'")->fetchColumn(),
    'drafts' => (int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE status='draft'")->fetchColumn(),
    'views' => (int)$pdo->query('SELECT COALESCE(SUM(views),0) FROM recipes')->fetchColumn(),
    'ingredients' => (int)$pdo->query('SELECT COUNT(*) FROM ingredients')->fetchColumn(),
];

$editor = in_array($action, ['new','edit'], true);
$current = null;
$ingredientText = '';
$stepText = '';
if ($editor) {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($action === 'edit' && $id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        if (!$current) { $action = ''; $editor = false; $flash = 'Không tìm thấy công thức.'; $flashType = 'error'; }
        else {
            $stmt = $pdo->prepare('SELECT i.name,ri.quantity FROM recipe_ingredients ri JOIN ingredients i ON i.id=ri.ingredient_id WHERE ri.recipe_id=? ORDER BY i.name');
            $stmt->execute([$id]);
            $ingredientText = implode("\n", array_map(static fn($x) => $x['name'] . ' | ' . $x['quantity'], $stmt->fetchAll()));
            $stmt = $pdo->prepare('SELECT instruction FROM recipe_steps WHERE recipe_id=? ORDER BY step_number');
            $stmt->execute([$id]);
            $stepText = implode("\n", array_column($stmt->fetchAll(), 'instruction'));
        }
    } elseif ($action === 'new') {
        $current = ['id'=>0,'title'=>'','description'=>'','category_id'=>$categories[0]['id'] ?? 0,'image_url'=>'','prep_minutes'=>0,'cook_minutes'=>0,'difficulty'=>'Dễ','servings'=>2,'status'=>'draft'];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'save_recipe') {
        $current = array_merge((array)$current, $_POST);
        $ingredientText = (string)($_POST['ingredients'] ?? '');
        $stepText = (string)($_POST['steps'] ?? '');
    }
}

$recipeSearch = trim((string)($_GET['q'] ?? ''));
$recipeStatus = (string)($_GET['status'] ?? 'all');
$sql = 'SELECT r.id,r.title,r.status,r.updated_at,r.views,r.prep_minutes,r.cook_minutes,c.name AS category FROM recipes r JOIN categories c ON c.id=r.category_id WHERE 1=1';
$params = [];
if ($recipeSearch !== '') { $sql .= ' AND (r.title LIKE ? OR c.name LIKE ?)'; $like='%'.$recipeSearch.'%'; $params=[$like,$like]; }
if (in_array($recipeStatus, ['published','draft'], true)) { $sql .= ' AND r.status=?'; $params[]=$recipeStatus; }
$sql .= ' ORDER BY r.updated_at DESC, r.id DESC LIMIT 100';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $recipes = $stmt->fetchAll();
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản trị | Bếp Nhà</title><link rel="stylesheet" href="/css/admin.css"><link rel="stylesheet" href="/css/admin-crud.css"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"></head><body>
<aside class="sidebar" id="sidebar"><a href="/admin.php" class="brand"><span class="brand-mark">✳</span><span>Bếp Nhà<small>ADMIN WORKSPACE</small></span></a><div class="side-label">KHÔNG GIAN LÀM VIỆC</div><nav class="side-nav"><a class="active" href="/admin.php"><span>▦</span>Tổng quan</a><a href="#recipes"><span>▤</span>Quản lý công thức <b><?=e($stats['recipes'])?></b></a><a href="#categories"><span>◈</span>Danh mục</a><a href="#ingredients"><span>♧</span>Nguyên liệu</a></nav><div class="side-label">HỆ THỐNG</div><nav class="side-nav"><a href="http://localhost:3000" target="_blank" rel="noopener"><span>◉</span>Giám sát</a><a href="http://localhost:8081" target="_blank" rel="noopener"><span>▦</span>phpMyAdmin</a><a href="/" target="_blank" rel="noopener"><span>↗</span>Xem website</a></nav><div class="sidebar-bottom"><div class="help-icon">?</div><div><strong>Bếp Nhà Recipe</strong><small>Quản trị dữ liệu thật</small></div><span>✳</span></div><div class="profile"><div class="profile-avatar">AD</div><div class="profile-text"><strong><?=e($admin['name'])?></strong><small><?=e($admin['email'])?></small></div><form method="post" action="/admin.php"><?=csrf_field()?><input type="hidden" name="action" value="logout"><button aria-label="Đăng xuất" title="Đăng xuất">↪</button></form></div></aside>
<main class="main"><header class="topbar"><button class="mobile-menu" id="menuBtn" aria-label="Mở menu">☰</button><div class="breadcrumb">Không gian làm việc <span>/</span> <strong><?= $editor ? ($action==='edit'?'Sửa công thức':'Thêm công thức') : 'Tổng quan' ?></strong></div><div class="top-actions"><span class="top-date">Dữ liệu trực tiếp từ MySQL</span><div class="user-mini">AD</div></div></header>
<div class="content">
<?php if ($flash !== ''): ?><div class="flash <?=e($flashType)?> page-flash"><?=e($flash)?></div><?php endif; ?>
<?php if ($editor): ?>
<section class="welcome-row"><div><div class="eyebrow">QUẢN LÝ NỘI DUNG</div><h1><?=$action==='edit'?'Chỉnh sửa công thức':'Thêm công thức mới'?></h1><p>Nguyên liệu và các bước nấu được lưu thành các bản ghi liên kết trong MySQL.</p></div><a href="/admin.php#recipes" class="btn-outline">← <span>Quay lại danh sách</span></a></section>
<form method="post" action="/admin.php?action=<?=e($action)?><?=((int)($current['id']??0)>0?'&id='.(int)$current['id']:'')?>" class="editor-form"><?=csrf_field()?><input type="hidden" name="action" value="save_recipe"><input type="hidden" name="id" value="<?=(int)($current['id']??0)?>">
<div class="editor-grid"><section class="editor-panel"><h2>Thông tin công thức</h2><label>Tên món ăn *<input name="title" maxlength="180" required value="<?=e($current['title']??'')?>" placeholder="Ví dụ: Phở bò truyền thống"></label><label>Mô tả *<textarea name="description" required rows="4" placeholder="Mô tả hương vị, đặc điểm món ăn..."><?=e($current['description']??'')?></textarea></label><label>URL ảnh (HTTP/HTTPS)<input type="url" name="image_url" value="<?=e($current['image_url']??'')?>" placeholder="https://..."></label><div class="form-two"><label>Danh mục *<select name="category_id" required><?php foreach($categories as $c): ?><option value="<?=(int)$c['id']?>" <?=((int)($current['category_id']??0)===(int)$c['id']?'selected':'')?>><?=e($c['name'])?></option><?php endforeach; ?></select></label><label>Độ khó<select name="difficulty"><?php foreach(['Dễ','Trung bình','Khó'] as $v): ?><option <?=((string)($current['difficulty']??'Dễ')===$v?'selected':'')?>><?=e($v)?></option><?php endforeach; ?></select></label></div><div class="form-three"><label>Chuẩn bị (phút)<input type="number" name="prep_minutes" min="0" max="1440" value="<?=e($current['prep_minutes']??0)?>" required></label><label>Nấu (phút)<input type="number" name="cook_minutes" min="0" max="1440" value="<?=e($current['cook_minutes']??0)?>" required></label><label>Khẩu phần<input type="number" name="servings" min="1" max="30" value="<?=e($current['servings']??2)?>" required></label></div><label>Trạng thái<select name="status"><option value="draft" <?=((string)($current['status']??'draft')==='draft'?'selected':'')?>>Bản nháp</option><option value="published" <?=((string)($current['status']??'draft')==='published'?'selected':'')?>>Đã xuất bản</option></select></label></section>
<div class="editor-side"><section class="editor-panel"><h2>Nguyên liệu *</h2><p class="muted">Mỗi dòng một nguyên liệu theo định dạng <code>Tên nguyên liệu | Số lượng</code>.</p><textarea name="ingredients" rows="10" required placeholder="Thịt bò | 300 g&#10;Bánh phở | 500 g&#10;Gừng | 1 củ"><?=e($ingredientText)?></textarea></section><section class="editor-panel"><h2>Cách nấu *</h2><p class="muted">Mỗi dòng được lưu thành một bước nấu theo thứ tự.</p><textarea name="steps" rows="9" required placeholder="Sơ chế nguyên liệu.&#10;Đun nóng nồi và cho nguyên liệu vào.&#10;Nêm gia vị vừa ăn rồi trình bày."><?=e($stepText)?></textarea></section></div></div><div class="editor-actions"><a href="/admin.php#recipes" class="btn-outline">Hủy</a><button type="submit" class="btn-primary">Lưu công thức <span>✓</span></button></div></form>
<?php else: ?>
<section class="welcome-row"><div><div class="eyebrow">BẢNG ĐIỀU KHIỂN</div><h1>Chào mừng, <?=e($admin['name'])?> <span>✦</span></h1><p>Các chỉ số bên dưới được truy vấn trực tiếp từ MySQL, không phải số liệu minh họa.</p></div><a href="/admin.php?action=new" class="btn-primary">＋ <span>Thêm công thức</span></a></section>
<section class="stats"><article class="stat-card"><div class="stat-top"><span>Tổng công thức</span><span class="stat-icon green">▤</span></div><div class="stat-value"><?=number_format($stats['recipes'])?></div><div class="stat-foot"><small>Tất cả trạng thái</small></div></article><article class="stat-card"><div class="stat-top"><span>Đã xuất bản</span><span class="stat-icon orange">◉</span></div><div class="stat-value"><?=number_format($stats['published'])?></div><div class="stat-foot"><small>Hiển thị ngoài website</small></div></article><article class="stat-card"><div class="stat-top"><span>Nguyên liệu</span><span class="stat-icon purple">♧</span></div><div class="stat-value"><?=number_format($stats['ingredients'])?></div><div class="stat-foot"><small>Danh mục nguyên liệu duy nhất</small></div></article><article class="stat-card"><div class="stat-top"><span>Lượt xem chi tiết</span><span class="stat-icon yellow">◷</span></div><div class="stat-value"><?=number_format($stats['views'])?></div><div class="stat-foot"><small>Tổng lượt xem lưu trong DB</small></div></article></section>
<section class="panel table-panel" id="recipes"><div class="panel-heading table-heading"><div><h2>Quản lý công thức</h2><p>Có thể thêm, sửa, xuất bản hoặc xóa công thức thực tế.</p></div><form class="table-actions admin-filter" method="get" action="/admin.php"><label class="table-search"><span>⌕</span><input name="q" value="<?=e($recipeSearch)?>" placeholder="Tìm công thức..."></label><select name="status"><option value="all">Tất cả trạng thái</option><option value="published" <?=$recipeStatus==='published'?'selected':''?>>Đã xuất bản</option><option value="draft" <?=$recipeStatus==='draft'?'selected':''?>>Bản nháp</option></select><button class="btn-primary" type="submit">Lọc</button></form></div><div class="table-wrap"><table><thead><tr><th>TÊN CÔNG THỨC</th><th>DANH MỤC</th><th>THỜI GIAN</th><th>LƯỢT XEM</th><th>TRẠNG THÁI</th><th>CẬP NHẬT</th><th>THAO TÁC</th></tr></thead><tbody><?php foreach($recipes as $r): ?><tr><td><div class="recipe-name"><span class="food-thumb mint">🍽️</span><span><strong><?=e($r['title'])?></strong><small>RC-<?=(int)$r['id']?></small></span></div></td><td><span class="category-tag"><?=e($r['category'])?></span></td><td><?=((int)$r['prep_minutes']+(int)$r['cook_minutes'])?> phút</td><td><?=number_format((int)$r['views'])?></td><td><span class="status <?=$r['status']==='published'?'published':'draft'?>"><i></i><?=$r['status']==='published'?'Đã xuất bản':'Bản nháp'?></span></td><td class="muted-cell"><?=e(date('d/m/Y H:i', strtotime($r['updated_at'])))?></td><td><div class="row-actions"><a class="small-action" href="/admin.php?action=edit&id=<?=(int)$r['id']?>">Sửa</a><form method="post" action="/admin.php" data-confirm="Bạn chắc chắn muốn xóa công thức này?"><?=csrf_field()?><input type="hidden" name="action" value="delete_recipe"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="small-action danger" type="submit">Xóa</button></form></div></td></tr><?php endforeach; ?><?php if(!$recipes): ?><tr><td colspan="7" class="empty-cell">Không tìm thấy công thức nào.</td></tr><?php endif; ?></tbody></table></div><div class="table-footer"><span>Hiển thị <strong><?=count($recipes)?></strong> công thức (tối đa 100 dòng)</span><a class="text-link" href="/">Xem website ↗</a></div></section>
<section class="category-admin" id="categories"><div class="category-admin-head"><div><div class="eyebrow">PHÂN LOẠI NỘI DUNG</div><h2>Danh mục món ăn</h2><p>Danh mục đang được tham chiếu bởi công thức trong cơ sở dữ liệu.</p></div></div><div class="category-admin-grid"><section class="editor-panel"><h3>Thêm danh mục</h3><form method="post" action="/admin.php#categories" class="stack-form"><?=csrf_field()?><input type="hidden" name="action" value="add_category"><label>Tên danh mục *<input name="name" maxlength="100" required placeholder="Ví dụ: Món nướng"></label><label>Mô tả<textarea name="description" rows="3" maxlength="255" placeholder="Mô tả ngắn về nhóm món ăn"></textarea></label><button class="btn-primary" type="submit">＋ Tạo danh mục</button></form></section><section class="editor-panel category-list"><h3>Danh mục hiện có (<?=count($categories)?>)</h3><?php foreach($categories as $c): ?><div class="category-row"><div><strong><?=e($c['name'])?></strong><small><?=e($c['description']??'')?> · <?=((int)$c['recipe_count'])?> công thức</small></div><form method="post" action="/admin.php#categories" data-confirm="Xóa danh mục này?"><?=csrf_field()?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="small-action danger" type="submit" <?=((int)$c['recipe_count']>0?'disabled title="Danh mục đang được sử dụng"':'')?>>Xóa</button></form></div><?php endforeach; ?></section></div></section>
<section class="system-hint" id="ingredients"><strong>Ghi chú dữ liệu:</strong> Nguyên liệu được quản lý khi sửa công thức và lưu qua quan hệ <code>recipe_ingredients</code>; các bước nấu lưu ở <code>recipe_steps</code>. Mở phpMyAdmin để kiểm tra trực tiếp bảng dữ liệu. <a href="http://localhost:8081" target="_blank" rel="noopener">Mở phpMyAdmin ↗</a></section>
<footer>© 2026 Bếp Nhà · Admin data from MySQL <span>·</span> <a href="http://localhost:3000" target="_blank" rel="noopener">Grafana ↗</a></footer>
<?php endif; ?></div></main><script src="/js/admin.js"></script></body></html>
