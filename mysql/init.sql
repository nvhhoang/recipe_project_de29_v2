-- Schema + demo content for the Recipe coursework. Runs when MySQL initializes an empty data volume.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  image_url VARCHAR(500) NULL,
  prep_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cook_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  difficulty ENUM('Dễ','Trung bình','Khó') NOT NULL DEFAULT 'Dễ',
  servings TINYINT UNSIGNED NOT NULL DEFAULT 2,
  rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_recipes_category FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_recipe_status_created (status, created_at),
  INDEX idx_recipe_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
  recipe_id INT UNSIGNED NOT NULL,
  ingredient_id INT UNSIGNED NOT NULL,
  quantity VARCHAR(100) NOT NULL,
  PRIMARY KEY (recipe_id, ingredient_id),
  CONSTRAINT fk_ri_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_steps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id INT UNSIGNED NOT NULL,
  step_number SMALLINT UNSIGNED NOT NULL,
  instruction TEXT NOT NULL,
  UNIQUE KEY uq_recipe_step (recipe_id, step_number),
  CONSTRAINT fk_steps_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (name, slug, description) VALUES
('Món Việt','mon-viet','Hương vị thân quen của bữa cơm nhà'),
('Món Âu','mon-au','Đổi vị với món ăn phong cách Âu'),
('Món chay','mon-chay','Tươi lành, nhẹ nhàng mỗi ngày'),
('Tráng miệng','trang-mieng','Ngọt ngào để khép lại bữa ăn');

INSERT IGNORE INTO recipes (category_id,title,slug,description,image_url,prep_minutes,cook_minutes,difficulty,servings,rating,rating_count,is_featured,status) VALUES
((SELECT id FROM categories WHERE slug='mon-viet'),'Phở bò truyền thống','pho-bo-truyen-thong','Nước dùng trong, thơm mùi quế hồi, thịt bò mềm và bánh phở dai ngon.','https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?auto=format&fit=crop&w=900&q=85',30,90,'Trung bình',4,4.9,128,1,'published'),
((SELECT id FROM categories WHERE slug='mon-au'),'Mì Ý sốt bò bằm','mi-y-sot-bo-bam','Sốt cà chua đậm đà kết hợp thịt bò bằm và mì Ý dai mềm.','https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=900&q=85',10,25,'Dễ',2,4.8,96,1,'published'),
((SELECT id FROM categories WHERE slug='mon-chay'),'Salad bơ trứng','salad-bo-trung','Món salad tươi mát, nhiều màu sắc, phù hợp bữa sáng nhẹ nhàng.','https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=85',15,0,'Dễ',2,4.7,74,1,'published'),
((SELECT id FROM categories WHERE slug='mon-viet'),'Gỏi cuốn tôm thịt','goi-cuon-tom-thit','Cuốn tươi với tôm, thịt, rau thơm và nước chấm đậm vị.','https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=900&q=85',20,10,'Dễ',4,4.9,112,0,'published'),
((SELECT id FROM categories WHERE slug='trang-mieng'),'Bánh pancake mật ong','banh-pancake-mat-ong','Pancake mềm xốp, dùng cùng trái cây tươi và mật ong.','https://images.unsplash.com/photo-1528207776546-365bb710ee93?auto=format&fit=crop&w=900&q=85',10,10,'Dễ',2,4.6,58,0,'published'),
((SELECT id FROM categories WHERE slug='mon-au'),'Cá hồi áp chảo','ca-hoi-ap-chao','Cá hồi vàng giòn bên ngoài, mềm mọng bên trong cùng rau củ.','https://images.unsplash.com/photo-1467003909585-2f8a72700288?auto=format&fit=crop&w=900&q=85',10,15,'Trung bình',2,4.8,83,0,'published');

INSERT IGNORE INTO ingredients (name) VALUES
('Xương bò'),('Thịt bò'),('Thịt bò bằm'),('Bánh phở'),('Gừng'),('Hành tím'),('Quế'),('Hoa hồi'),('Mì Ý'),('Cà chua'),('Tỏi'),('Hành tây'),('Phô mai parmesan'),('Bơ'),('Trứng'),('Bơ quả'),('Xà lách'),('Tôm'),('Thịt ba chỉ'),('Bánh tráng'),('Bún tươi'),('Rau thơm'),('Bột mì'),('Sữa tươi'),('Mật ong'),('Cá hồi'),('Chanh vàng'),('Măng tây'),('Dầu olive'),('Muối'),('Tiêu');

INSERT IGNORE INTO recipe_ingredients (recipe_id, ingredient_id, quantity)
SELECT r.id, i.id, x.quantity FROM (
  SELECT 'pho-bo-truyen-thong' slug,'Xương bò' ingredient,'500 g' quantity UNION ALL
  SELECT 'pho-bo-truyen-thong','Thịt bò','300 g' UNION ALL SELECT 'pho-bo-truyen-thong','Bánh phở','500 g' UNION ALL
  SELECT 'pho-bo-truyen-thong','Gừng','1 củ' UNION ALL SELECT 'pho-bo-truyen-thong','Hành tím','3 củ' UNION ALL
  SELECT 'pho-bo-truyen-thong','Quế','1 thanh' UNION ALL SELECT 'pho-bo-truyen-thong','Hoa hồi','3 cánh' UNION ALL
  SELECT 'mi-y-sot-bo-bam','Mì Ý','200 g' UNION ALL SELECT 'mi-y-sot-bo-bam','Thịt bò bằm','250 g' UNION ALL
  SELECT 'mi-y-sot-bo-bam','Cà chua','3 quả' UNION ALL SELECT 'mi-y-sot-bo-bam','Tỏi','2 tép' UNION ALL
  SELECT 'mi-y-sot-bo-bam','Hành tây','1/2 củ' UNION ALL SELECT 'mi-y-sot-bo-bam','Phô mai parmesan','Vừa đủ' UNION ALL
  SELECT 'salad-bo-trung','Bơ quả','1 quả' UNION ALL SELECT 'salad-bo-trung','Trứng','2 quả' UNION ALL
  SELECT 'salad-bo-trung','Xà lách','100 g' UNION ALL SELECT 'salad-bo-trung','Cà chua','5 quả bi' UNION ALL
  SELECT 'salad-bo-trung','Dầu olive','1 thìa' UNION ALL SELECT 'goi-cuon-tom-thit','Tôm','200 g' UNION ALL
  SELECT 'goi-cuon-tom-thit','Thịt ba chỉ','200 g' UNION ALL SELECT 'goi-cuon-tom-thit','Bánh tráng','10 cái' UNION ALL
  SELECT 'goi-cuon-tom-thit','Bún tươi','200 g' UNION ALL SELECT 'goi-cuon-tom-thit','Rau thơm','1 bó' UNION ALL
  SELECT 'banh-pancake-mat-ong','Bột mì','150 g' UNION ALL SELECT 'banh-pancake-mat-ong','Trứng','1 quả' UNION ALL
  SELECT 'banh-pancake-mat-ong','Sữa tươi','180 ml' UNION ALL SELECT 'banh-pancake-mat-ong','Mật ong','2 thìa' UNION ALL
  SELECT 'ca-hoi-ap-chao','Cá hồi','2 miếng' UNION ALL SELECT 'ca-hoi-ap-chao','Bơ','20 g' UNION ALL
  SELECT 'ca-hoi-ap-chao','Chanh vàng','1/2 quả' UNION ALL SELECT 'ca-hoi-ap-chao','Măng tây','100 g'
) x JOIN recipes r ON r.slug=x.slug JOIN ingredients i ON i.name=x.ingredient;

INSERT IGNORE INTO recipe_steps (recipe_id, step_number, instruction)
SELECT r.id, s.step_number, s.instruction FROM (
  SELECT 'pho-bo-truyen-thong' slug,1 step_number,'Nướng gừng và hành tím cho thơm.' instruction UNION ALL
  SELECT 'pho-bo-truyen-thong',2,'Ninh xương với quế, hồi trong thời gian dài và thường xuyên hớt bọt.' UNION ALL
  SELECT 'pho-bo-truyen-thong',3,'Trụng bánh phở, xếp thịt bò và chan nước dùng nóng.' UNION ALL
  SELECT 'pho-bo-truyen-thong',4,'Ăn kèm rau thơm, chanh và ớt.' UNION ALL
  SELECT 'mi-y-sot-bo-bam',1,'Luộc mì theo hướng dẫn trên bao bì rồi để ráo.' UNION ALL
  SELECT 'mi-y-sot-bo-bam',2,'Phi thơm tỏi và hành tây, cho thịt bò bằm vào xào.' UNION ALL
  SELECT 'mi-y-sot-bo-bam',3,'Thêm cà chua, nêm gia vị và đun đến khi sốt sánh.' UNION ALL
  SELECT 'mi-y-sot-bo-bam',4,'Trộn mì với sốt, rắc parmesan và thưởng thức.' UNION ALL
  SELECT 'salad-bo-trung',1,'Luộc trứng chín vừa, bóc vỏ và cắt miếng.' UNION ALL
  SELECT 'salad-bo-trung',2,'Rửa sạch rau và cà chua, để ráo.' UNION ALL
  SELECT 'salad-bo-trung',3,'Cắt bơ, trộn nhẹ cùng rau và trứng.' UNION ALL
  SELECT 'salad-bo-trung',4,'Rưới sốt dầu olive, chanh và nêm vừa ăn.' UNION ALL
  SELECT 'goi-cuon-tom-thit',1,'Luộc tôm và thịt, để nguội rồi thái lát.' UNION ALL
  SELECT 'goi-cuon-tom-thit',2,'Chuẩn bị bún và rau sống đã rửa sạch.' UNION ALL
  SELECT 'goi-cuon-tom-thit',3,'Nhúng bánh tráng, xếp nhân rồi cuốn chặt tay.' UNION ALL
  SELECT 'goi-cuon-tom-thit',4,'Pha nước chấm và dùng ngay.' UNION ALL
  SELECT 'banh-pancake-mat-ong',1,'Trộn bột mì, trứng, sữa và bơ thành hỗn hợp mịn.' UNION ALL
  SELECT 'banh-pancake-mat-ong',2,'Làm nóng chảo chống dính ở lửa nhỏ.' UNION ALL
  SELECT 'banh-pancake-mat-ong',3,'Đổ từng phần bột, lật khi mặt bánh nổi bọt.' UNION ALL
  SELECT 'banh-pancake-mat-ong',4,'Dùng cùng mật ong và trái cây.' UNION ALL
  SELECT 'ca-hoi-ap-chao',1,'Thấm khô cá, nêm muối tiêu.' UNION ALL
  SELECT 'ca-hoi-ap-chao',2,'Áp chảo mặt da trước đến khi vàng giòn.' UNION ALL
  SELECT 'ca-hoi-ap-chao',3,'Thêm bơ và tỏi, rưới bơ thơm lên cá.' UNION ALL
  SELECT 'ca-hoi-ap-chao',4,'Dùng cùng chanh và rau củ áp chảo.'
) s JOIN recipes r ON r.slug=s.slug;
