<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';

$pdo = null;
$dbError = false;
$recipes = [];
$categories = [];
$selected = null;
$q = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['category'] ?? 'Tất cả'));

try {
    $pdo = db();
    $categories = $pdo->query('SELECT id, name, slug, description FROM categories ORDER BY name')->fetchAll();
    $sql = "SELECT r.id, r.title, r.slug, r.description, r.image_url AS image, r.prep_minutes, r.cook_minutes,
                   (r.prep_minutes + r.cook_minutes) AS time, r.difficulty AS level, r.servings,
                   r.rating, r.rating_count AS reviews, r.views, c.name AS category
            FROM recipes r JOIN categories c ON c.id = r.category_id WHERE r.status = 'published'";
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (r.title LIKE ? OR r.description LIKE ? OR c.name LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    if ($cat !== '' && $cat !== 'Tất cả') {
        $sql .= ' AND c.name = ?';
        $params[] = $cat;
    }
    $sql .= ' ORDER BY r.is_featured DESC, r.created_at DESC, r.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();

    foreach ($recipes as &$r) {
        $r['image'] = $r['image'] ?: 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85';
        $r['rating'] = number_format((float)$r['rating'], 1);
        $r['time'] = (int)$r['time'];
        $r['reviews'] = (int)$r['reviews'];
    }
    unset($r);

    if (isset($_GET['recipe'])) {
        $detail = $pdo->prepare("SELECT r.*, r.image_url AS image, (r.prep_minutes + r.cook_minutes) AS time, r.difficulty AS level, c.name AS category
                                 FROM recipes r JOIN categories c ON c.id = r.category_id
                                 WHERE r.id = ? AND r.status = 'published' LIMIT 1");
        $detail->execute([(int)$_GET['recipe']]);
        $selected = $detail->fetch() ?: null;
        if ($selected) {
            $selected['image'] = $selected['image'] ?: 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85';
            $selected['rating'] = number_format((float)$selected['rating'], 1);
            $ingredients = $pdo->prepare('SELECT i.name, ri.quantity FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id WHERE ri.recipe_id = ? ORDER BY i.name');
            $ingredients->execute([$selected['id']]);
            $selected['ingredients'] = array_map(static fn($item) => trim($item['quantity'] . ' ' . $item['name']), $ingredients->fetchAll());
            $steps = $pdo->prepare('SELECT instruction FROM recipe_steps WHERE recipe_id = ? ORDER BY step_number');
            $steps->execute([$selected['id']]);
            $selected['steps'] = array_column($steps->fetchAll(), 'instruction');
            $pdo->prepare('UPDATE recipes SET views = views + 1 WHERE id = ?')->execute([$selected['id']]);
            app_log('INFO', 'Recipe detail viewed', ['recipe_id' => (int)$selected['id'], 'slug' => $selected['slug']]);
        }
    }
} catch (Throwable $ex) {
    $dbError = true;
    app_log('ERROR', 'Public recipe page failed to load data', ['error' => $ex->getMessage()]);
}

$categoriesForCards = [
    'Món Việt' => ['🍲', 'Hương vị thân quen', 'cat-green'],
    'Món Âu' => ['🍝', 'Đổi vị thật tinh tế', 'cat-peach'],
    'Món chay' => ['🥑', 'Tươi lành mỗi ngày', 'cat-lime'],
    'Tráng miệng' => ['🍰', 'Ngọt ngào cuối bữa', 'cat-pink'],
];
$categoryNames = array_merge(['Tất cả'], array_column($categories, 'name'));
$recipeCount = count($recipes);
?><!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bếp Nhà — Cảm hứng nấu ăn mỗi ngày</title>
<meta name="description" content="Khám phá công thức nấu ăn ngon, nguyên liệu và cách nấu từng bước.">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="/"><span class="brand-mark">✳</span><span>Bếp Nhà<small>COOK • SHARE • LOVE</small></span></a>
  <nav class="main-nav"><a class="active" href="/">Trang chủ</a><a href="#recipes">Công thức</a><a href="#categories">Danh mục</a><a href="#about">Về Bếp Nhà</a></nav>
  <div class="header-actions"><a class="saved-link" href="#recipes">♡ <span>Yêu thích</span></a><a class="btn btn-dark" href="#recipes">Khám phá món <span>↗</span></a><button class="menu-toggle" aria-label="Mở menu">☰</button></div>
</header>
<main>
<section class="hero">
  <div class="hero-copy">
    <div class="eyebrow"><span class="eyebrow-line"></span> BẾP NHÀ · CẢM HỨNG MỖI NGÀY</div>
    <h1>Nấu ngon hơn,<br><em>sống vui hơn.</em></h1>
    <p>Gom góp những công thức ngon lành, dễ làm để mỗi bữa cơm nhà đều trở thành một điều đáng nhớ.</p>
    <form class="search-form" action="/" method="get"><span class="search-icon">⌕</span><input name="q" value="<?=e($q)?>" placeholder="Bạn muốn nấu món gì hôm nay?" aria-label="Tìm công thức"><button type="submit">Tìm công thức <span>→</span></button></form>
    <div class="hero-meta"><div class="avatar-stack"><span>🍜</span><span>🥗</span><span>🍰</span></div><div><strong><?=array_sum(array_map(static fn($c) => 1, $categories))?> danh mục món ăn</strong><small>Nguyên liệu rõ ràng, cách nấu từng bước</small></div><div class="hero-rating"><b>✦ Công thức thật</b><small>Được lưu trong MySQL</small></div></div>
  </div>
  <div class="hero-visual"><div class="hero-image"><img src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1200&q=90" alt="Bữa ăn tươi ngon trên bàn"></div><div class="floating-note"><span class="note-icon">✦</span><div><b>Ngon từ căn bếp</b><small>Niềm vui từ những điều giản dị</small></div></div><div class="hero-stamp">MADE WITH<br><b>♡</b><br>AT HOME</div></div>
</section>
<?php if ($dbError): ?><div class="section"><div class="empty-state"><span>⚠️</span><h3>Chưa kết nối được cơ sở dữ liệu</h3><p>Kiểm tra container MySQL và nhật ký ứng dụng. Website không dùng dữ liệu giả khi MySQL bị lỗi.</p></div></div><?php endif; ?>
<section class="trust-strip"><div><span>✳</span> Công thức dễ làm</div><div><span>♧</span> Nguyên liệu gần gũi</div><div><span>♡</span> Dữ liệu từ MySQL</div><div><span>⌁</span> Mỗi ngày một món ngon</div></section>
<section class="section categories-section" id="categories">
  <div class="section-heading"><div><div class="eyebrow">TÌM CẢM HỨNG</div><h2>Hôm nay ăn gì?</h2><p>Chọn một danh mục, để Bếp Nhà gợi ý cho bạn.</p></div><a class="text-link" href="#recipes">Tất cả công thức <span>↗</span></a></div>
  <div class="category-grid">
  <?php foreach ($categories as $c): $info = $categoriesForCards[$c['name']] ?? ['🍽️', 'Khám phá món ngon', 'cat-green']; ?>
    <a class="category-card <?=e($info[2])?>" href="/?category=<?=urlencode($c['name'])?>#recipes"><span class="category-emoji"><?=e($info[0])?></span><span class="category-name"><?=e($c['name'])?></span><span class="category-desc"><?=e($c['description'] ?: $info[1])?></span><span class="category-arrow">↗</span></a>
  <?php endforeach; ?>
  </div>
</section>
<section class="section recipes-section" id="recipes">
  <div class="section-heading"><div><div class="eyebrow">ĐƯỢC YÊU THÍCH</div><h2>Công thức nổi bật <span class="spark">✳</span></h2><p>Danh sách món ăn lấy trực tiếp từ cơ sở dữ liệu.</p></div><div class="recipe-controls"><span class="results-count"><?=$recipeCount?> công thức</span><a class="text-link" href="/">Đặt lại bộ lọc ↺</a></div></div>
  <div class="filter-row"><?php foreach ($categoryNames as $c): ?><a class="filter-chip <?=($cat === $c ? 'selected' : '')?>" href="/?category=<?=urlencode($c)?>#recipes"><?=e($c)?></a><?php endforeach; ?></div>
  <div class="recipe-grid">
  <?php if (!$recipes): ?><div class="empty-state"><span>🥣</span><h3>Chưa tìm thấy món phù hợp</h3><p>Thử từ khóa khác hoặc chọn danh mục khác nhé.</p><a class="btn btn-dark" href="/">Xem tất cả công thức</a></div><?php endif; ?>
  <?php foreach ($recipes as $r): ?><article class="recipe-card"><div class="recipe-photo"><a class="recipe-image-link" href="/?recipe=<?=(int)$r['id']?>"><img src="<?=e($r['image'])?>" alt="<?=e($r['title'])?>" loading="lazy"></a><span class="photo-category"><?=e($r['category'])?></span><button class="heart-btn" type="button" aria-label="Lưu công thức" data-save="<?=(int)$r['id']?>">♡</button></div><div class="recipe-info"><div class="recipe-meta"><span>◷ <?=((int)$r['time'])?> phút</span><span>·</span><span><?=e($r['level'])?></span></div><h3><a href="/?recipe=<?=(int)$r['id']?>"><?=e($r['title'])?></a></h3><p><?=e($r['description'])?></p><div class="recipe-footer"><span class="rating">★ <?=e($r['rating'])?> <small>(<?=((int)$r['reviews'])?>)</small></span><a class="round-arrow" href="/?recipe=<?=(int)$r['id']?>" aria-label="Xem công thức">↗</a></div></div></article><?php endforeach; ?>
  </div>
</section>
<section class="newsletter" id="about"><div class="newsletter-icon">✉</div><div><div class="eyebrow">GÓC BẾP CỦA BẠN</div><h2>Mỗi tuần một chút cảm hứng.</h2><p>Cùng khám phá nguyên liệu và cách nấu những món ngon tại nhà.</p></div><a class="btn btn-dark" href="#recipes">Khám phá công thức <span>↗</span></a></section>
</main>
<footer class="footer"><a class="brand" href="/"><span class="brand-mark">✳</span><span>Bếp Nhà<small>COOK • SHARE • LOVE</small></span></a><p>Nấu bằng sự tử tế, chia sẻ bằng yêu thương.</p><span>© 2026 Bếp Nhà · Recipe Project</span></footer>
<?php if ($selected): ?><div class="modal-backdrop" id="recipe-modal"><section class="recipe-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"><a class="modal-close" href="/#recipes" aria-label="Đóng">×</a><img class="modal-cover" src="<?=e($selected['image'])?>" alt="<?=e($selected['title'])?>"><div class="modal-body"><span class="photo-category inline"><?=e($selected['category'])?></span><h2 id="modal-title"><?=e($selected['title'])?></h2><p><?=e($selected['description'])?></p><div class="modal-stats"><span>◷ <?=((int)$selected['time'])?> phút</span><span>✦ <?=e($selected['level'])?></span><span>★ <?=e($selected['rating'])?></span></div><div class="modal-columns"><div><h3>Nguyên liệu</h3><ul><?php foreach (($selected['ingredients'] ?? []) as $i): ?><li><?=e($i)?></li><?php endforeach; ?></ul></div><div><h3>Cách làm</h3><ol><?php foreach (($selected['steps'] ?? []) as $s): ?><li><?=e($s)?></li><?php endforeach; ?></ol></div></div><a class="btn btn-dark" href="/#recipes">Quay lại công thức <span>↗</span></a></div></section></div><?php elseif (isset($_GET['recipe']) && !$selected): ?><div class="modal-backdrop"><section class="recipe-modal"><a class="modal-close" href="/#recipes">×</a><div class="modal-body"><h2>Không tìm thấy công thức</h2><p>Công thức có thể đã bị xóa hoặc chưa được xuất bản.</p></div></section></div><?php endif; ?>
<script src="/js/app.js"></script>
</body></html>
