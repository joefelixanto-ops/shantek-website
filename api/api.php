<?php
// ═══════════════════════════════════════════════════
//  api.php — Shantek MySQL API
//  Upload to: public_html/shantek/api/api.php
//  Handles: products, categories, slides, company, stats, testimonials
// ═══════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── ROUTE ──
switch ($action) {

    // ════════════════════════════════
    //  PUBLIC ENDPOINTS (no auth)
    // ════════════════════════════════

    case 'get_all':
        // Returns everything the website needs in one call
        $db = getDB();
        $out = [];

        // Company
        $rows = $db->query("SELECT key_name, val FROM company")->fetchAll();
        foreach ($rows as $r) $out['company'][$r['key_name']] = $r['val'];

        // Products (active only, with category)
        $out['products'] = $db->query("
            SELECT p.id, p.name, p.description, p.price, p.badge,
                   p.spec1, p.spec2, p.spec3, p.image_url,
                   c.name AS cat, c.emoji, c.slug AS cat_slug
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            ORDER BY p.sort_order ASC, p.id ASC
        ")->fetchAll();

        // Categories
        $out['categories'] = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();

        // Slides
        $out['slides'] = $db->query("SELECT * FROM slides ORDER BY sort_order")->fetchAll();

        // Testimonials
        $out['testimonials'] = $db->query("SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order")->fetchAll();

        // Stats
        $out['stats'] = $db->query("SELECT num, label FROM stats ORDER BY sort_order")->fetchAll();

        success($out, 'OK');
        break;

    case 'get_products':
        // Paginated + searchable product listing
        $db       = getDB();
        $page     = max(1, intval($_GET['page'] ?? 1));
        $limit    = min(50, max(1, intval($_GET['limit'] ?? 20)));
        $offset   = ($page - 1) * $limit;
        $search   = trim($_GET['search'] ?? '');
        $cat_slug = trim($_GET['category'] ?? '');

        $where  = ['p.is_active = 1'];
        $params = [];

        if ($search) {
            $where[]  = '(p.name LIKE ? OR p.description LIKE ? OR p.spec1 LIKE ? OR p.spec2 LIKE ?)';
            $like     = "%$search%";
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($cat_slug) {
            $where[]  = 'c.slug = ?';
            $params[] = $cat_slug;
        }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);

        // Total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id=c.id $whereSQL");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Products
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.description, p.price, p.badge,
                   p.spec1, p.spec2, p.spec3, p.image_url,
                   c.name AS cat, c.emoji, c.slug AS cat_slug, c.id AS category_id
            FROM products p
            JOIN categories c ON p.category_id = c.id
            $whereSQL
            ORDER BY p.sort_order ASC, p.id ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$limit, $offset]));

        success([
            'products'     => $stmt->fetchAll(),
            'total'        => (int)$total,
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => ceil($total / $limit),
            'has_next'     => ($page * $limit) < $total,
            'has_prev'     => $page > 1,
        ]);
        break;

    case 'get_categories':
        $cats = getDB()->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
        success(['categories' => $cats]);
        break;

    // ════════════════════════════════
    //  ADMIN ENDPOINTS (auth required)
    // ════════════════════════════════

    case 'login':
        if ($method !== 'POST') error('POST only', 405);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!checkRateLimit($ip)) error('Too many attempts. Locked for '.LOCKOUT_MINUTES.' minutes.', 429);
        if (!verifyPassword($body['password'] ?? '')) {
            recordLoginAttempt($ip);
            $db  = getDB();
            $ago = date('Y-m-d H:i:s', strtotime('-'.LOCKOUT_MINUTES.' minutes'));
            $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND attempted_at>?");
            $stmt->execute([$ip, $ago]);
            $attempts = $stmt->fetchColumn();
            $left = MAX_LOGIN_ATTEMPTS - $attempts;
            error("Wrong password. $left attempts remaining.", 401);
        }
        clearLoginAttempts($ip);
        // Return all data for admin panel
        $db  = getDB();
        $out = [];
        $rows = $db->query("SELECT key_name, val FROM company")->fetchAll();
        foreach ($rows as $r) $out['company'][$r['key_name']] = $r['val'];
        $out['products']     = $db->query("SELECT p.*, c.name AS cat FROM products p JOIN categories c ON p.category_id=c.id ORDER BY p.sort_order,p.id")->fetchAll();
        $out['categories']   = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
        $out['slides']       = $db->query("SELECT * FROM slides ORDER BY sort_order")->fetchAll();
        $out['testimonials'] = $db->query("SELECT * FROM testimonials ORDER BY sort_order")->fetchAll();
        $out['stats']        = $db->query("SELECT * FROM stats ORDER BY sort_order")->fetchAll();
        success($out, 'Login successful');
        break;

    case 'save_product':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        $db = getDB();
        $d  = sanitize($body);

        // Get category_id from name
        $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$d['cat'] ?? 'Platform Scale']);
        $catId = $stmt->fetchColumn() ?: 1;

        if (!empty($d['id']) && is_numeric($d['id'])) {
            // UPDATE
            $stmt = $db->prepare("UPDATE products SET name=?,category_id=?,description=?,price=?,badge=?,spec1=?,spec2=?,spec3=?,image_url=? WHERE id=?");
            $stmt->execute([$d['name'],$catId,$d['desc']??'',$d['price']??'',$d['badge']??'',$d['spec1']??'',$d['spec2']??'',$d['spec3']??'',$d['img']??'',$d['id']]);
            success(['id' => (int)$d['id']], 'Product updated');
        } else {
            // INSERT
            $stmt = $db->prepare("INSERT INTO products (name,category_id,description,price,badge,spec1,spec2,spec3,image_url) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$d['name'],$catId,$d['desc']??'',$d['price']??'',$d['badge']??'',$d['spec1']??'',$d['spec2']??'',$d['spec3']??'',$d['img']??'']);
            success(['id' => (int)$db->lastInsertId()], 'Product added');
        }
        break;

    case 'delete_product':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        $id = intval($body['id'] ?? 0);
        if (!$id) error('Invalid ID');
        getDB()->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        success([], 'Product deleted');
        break;

    case 'save_company':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        $db     = getDB();
        $fields = ['name','tagline','phone','whatsapp','email','address','about','domain'];
        foreach ($fields as $f) {
            if (isset($body[$f])) {
                $db->prepare("INSERT INTO company (key_name,val) VALUES (?,?) ON DUPLICATE KEY UPDATE val=?")->execute([$f, $body[$f], $body[$f]]);
            }
        }
        success([], 'Company info saved');
        break;

    case 'save_slide':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        $db = getDB();
        $d  = sanitize($body);
        if (!empty($d['id'])) {
            $stmt = $db->prepare("UPDATE slides SET title=?,subtitle=?,desc_text=?,badge=?,btn1=?,btn2=?,bg_image=? WHERE id=?");
            $stmt->execute([$d['title']??'',$d['subtitle']??'',$d['desc']??'',$d['badge']??'',$d['btn1']??'',$d['btn2']??'',$d['bg']??'',$d['id']]);
        }
        success([], 'Slide saved');
        break;

    case 'save_stats':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        $db   = getDB();
        $list = $body['stats'] ?? [];
        foreach ($list as $s) {
            $db->prepare("UPDATE stats SET num=?,label=? WHERE id=?")->execute([$s['num'],$s['label'],$s['id']]);
        }
        success([], 'Stats saved');
        break;

    case 'save_testimonial':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        $db = getDB(); $d = sanitize($body);
        if (!empty($d['id'])) {
            $db->prepare("UPDATE testimonials SET name=?,role=?,review=?,stars=? WHERE id=?")->execute([$d['name'],$d['role'],$d['text']??'',$d['stars']??5,$d['id']]);
        }
        success([], 'Testimonial saved');
        break;

    case 'upload_image':
        if ($method !== 'POST') error('POST only', 405);
        authCheck($body);
        require_once __DIR__ . '/upload_handler.php';
        handleUpload($body);
        break;

    case 'list_images':
        $imgDir  = IMAGES_PATH;
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!is_dir($imgDir)) { success(['images'=>[],'count'=>0]); break; }
        $files = []; $all = scandir($imgDir);
        foreach ($all as $f) {
            if ($f==='.'||$f==='..'||$f==='.htaccess') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext,$allowed)) continue;
            $files[] = ['name'=>$f,'url'=>IMAGES_URL.$f,'size_kb'=>round(filesize($imgDir.$f)/1024,1)];
        }
        usort($files, fn($a,$b)=>strcmp($a['name'],$b['name']));
        success(['images'=>array_column($files,'name'),'details'=>$files,'count'=>count($files)]);
        break;

    default:
        error('Unknown action: '.$action, 404);
}

// ── HELPERS ──
function authCheck($body) {
    if (!verifyPassword($body['password'] ?? '')) error('Unauthorized', 401);
}
function sanitize($d) {
    array_walk_recursive($d, function(&$v) {
        if (is_string($v) && !str_starts_with($v,'data:image/')) {
            $v = strip_tags($v);
            $v = preg_replace('/<\?php.*?\?>/is','',$v);
        }
    });
    return $d;
}
?>
