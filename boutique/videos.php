<?php
// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

// ============================================
// VÉRIFICATION MAINTENANCE & DEPENDANCES
// ============================================
require_once '../includes/maintenance_check.php';

$titre_page = 'Vidéos & Tendances - Awa Ka Sugu';
require_once '../includes/header.php';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================
function getYoutubeId($url) {
    if (empty($url)) return '';
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}

function getYoutubeEmbedUrl($url) {
    $id = getYoutubeId($url);
    return !empty($id) ? "https://www.youtube.com/embed/" . $id . "?autoplay=0&rel=0&controls=1&enablejsapi=1" : $url;
}

function getVideoUrl($video) {
    if (!empty($video['fichier_video'])) {
        $path = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($path)) return $path;
    }
    if (isset($video['type']) && $video['type'] == 'local' && !empty($video['fichier_video'])) {
        return '../uploads/videos/' . $video['fichier_video'];
    }
    if (!empty($video['url_ou_fichier'])) {
        return getYoutubeEmbedUrl($video['url_ou_fichier']);
    }
    return '#';
}

function isLocalVideo($video) {
    if (!empty($video['fichier_video'])) {
        $path = '../uploads/videos/' . $video['fichier_video'];
        if (file_exists($path)) return true;
    }
    return (isset($video['type']) && $video['type'] == 'local' && !empty($video['fichier_video']));
}

// ============================================
// RÉCUPÉRATION DES VIDÉOS ET PRODUITS LIÉS
// ============================================
$videos = [];
try {
    $sql = "SELECT v.*, p.id AS p_id, p.nom AS produit_nom, p.prix AS produit_prix, p.prix_promo AS produit_promo, p.image_principale AS produit_image 
            FROM videos v 
            LEFT JOIN produits p ON v.produit_id = p.id 
            WHERE v.est_active = 1 
            ORDER BY v.created_at DESC";
    $stmt = $pdo->query($sql);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    try {
        $stmt = $pdo->query("SELECT * FROM videos WHERE est_active = 1 ORDER BY created_at DESC");
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $ex) {
        $videos = [];
    }
}

// AJOUT : id de la vidéo ciblée depuis le catalogue (?video=ID)
$video_cible = isset($_GET['video']) ? (int)$_GET['video'] : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($titre_page) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overscroll-behavior: none; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #000; 
            color: #fff;
            overflow: hidden;
            height: 100vh;
            height: 100dvh;
        }

        .tiktok-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 14px 20px;
            padding-top: calc(14px + env(safe-area-inset-top));
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(180deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%);
            pointer-events: none;
        }
        .tiktok-header > * { pointer-events: auto; }
        .tiktok-header .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: #C8922A;
            text-decoration: none;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .tiktok-header .logo span { color: #fff; }
        .tiktok-header .actions { display: flex; align-items: center; gap: 8px; }
        .tiktok-header .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 15px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            background: rgba(0,0,0,0.3);
            white-space: nowrap;
        }
        .tiktok-header .btn-nav.btn-shop {
            background: linear-gradient(135deg, #C8922A, #A6721E);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(200,146,42,0.3);
        }

        .video-container {
            height: 100vh;
            height: 100dvh;
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            scroll-behavior: smooth;
        }
        .video-container::-webkit-scrollbar { display: none; }

        .video-item {
            position: relative;
            height: 100vh;
            height: 100dvh;
            width: 100%;
            scroll-snap-align: start;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            overflow: hidden;
        }

        .video-player { width: 100%; height: 100%; }
        .video-player video, .video-player iframe {
            width: 100%; height: 100%; object-fit: cover; border: none;
        }

        .video-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px 76px 25px 16px;
            padding-bottom: calc(25px + env(safe-area-inset-bottom));
            background: linear-gradient(0deg, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.4) 65%, transparent 100%);
            pointer-events: none;
            z-index: 10;
        }
        .video-overlay > * { pointer-events: auto; }

        .user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
            border: 2px solid #C8922A; box-shadow: 0 0 10px rgba(200,146,42,0.4);
            flex-shrink: 0;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-details { min-width: 0; }
        .user-details .user-name { font-size: 0.9rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-details .user-handle { font-size: 0.7rem; color: rgba(255,255,255,0.65); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .video-title { font-size: 0.88rem; font-weight: 600; color: #fff; margin-bottom: 4px; }
        .video-desc { font-size: 0.78rem; color: rgba(255,255,255,0.75); line-height: 1.35; margin-bottom: 10px; }

        .product-tag-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(20, 20, 20, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(200, 146, 42, 0.4);
            padding: 6px 12px 6px 6px;
            border-radius: 12px;
            max-width: min(280px, 78vw);
            box-shadow: 0 8px 25px rgba(0,0,0,0.6);
        }
        .product-tag-img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
        .product-tag-info { flex: 1; min-width: 0; }
        .product-tag-title { font-size: 0.72rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .product-tag-price { font-size: 0.75rem; color: #C8922A; font-weight: 800; }
        .btn-buy-now {
            background: #C8922A;
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: transform 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-buy-now:active { transform: scale(0.95); }

        .side-actions {
            position: absolute;
            right: 10px;
            bottom: calc(85px + env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            z-index: 20;
        }
        .side-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: #fff;
            background: none;
            border: none;
            cursor: pointer;
        }
        .side-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .side-btn.gold .side-icon-box {
            background: linear-gradient(135deg, #C8922A, #A6721E);
            border: none;
            color: #fff;
        }
        .side-btn span { font-size: 0.62rem; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }

        .video-progress {
            position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: rgba(255,255,255,0.2); z-index: 30;
        }
        .video-progress .bar { height: 100%; background: #C8922A; width: 0%; }

        .bottom-sheet-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
            z-index: 200; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
        }
        .bottom-sheet-overlay.active { opacity: 1; pointer-events: auto; }
        
        .bottom-sheet {
            position: fixed; bottom: -100%; left: 0; right: 0; background: #141414;
            border-top-left-radius: 20px; border-top-right-radius: 20px; padding: 20px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom));
            z-index: 201; transition: bottom 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            color: #fff; border-top: 1px solid rgba(200, 146, 42, 0.3);
            max-height: 80vh; overflow-y: auto;
            max-width: 480px; margin: 0 auto;
        }
        .bottom-sheet.active { bottom: 0; }
        
        .sheet-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .sheet-product { display: flex; gap: 12px; align-items: center; }
        .sheet-product img { width: 60px; height: 60px; border-radius: 10px; object-fit: cover; }
        .sheet-product-title { font-size: 0.9rem; font-weight: 700; }
        .sheet-product-price { color: #C8922A; font-weight: 800; font-size: 0.95rem; }
        .btn-close-sheet { background: none; border: none; color: #fff; font-size: 1.4rem; cursor: pointer; }

        .option-group { margin-bottom: 15px; }
        .option-label { font-size: 0.75rem; color: rgba(255,255,255,0.6); margin-bottom: 8px; display: block; font-weight: 600; text-transform: uppercase; }
        .options-list { display: flex; gap: 8px; flex-wrap: wrap; }
        .option-chip {
            padding: 8px 16px; border-radius: 20px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
            font-size: 0.75rem; color: #fff; cursor: pointer; transition: all 0.2s;
        }
        .option-chip.selected { background: #C8922A; color: #000; border-color: #C8922A; font-weight: 700; }

        .qty-picker { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.08); padding: 4px 12px; border-radius: 20px; width: fit-content; }
        .qty-btn { background: none; border: none; color: #fff; font-size: 1.1rem; cursor: pointer; }
        .qty-val { font-size: 0.85rem; font-weight: 700; }

        .btn-add-cart-final {
            width: 100%; padding: 14px; background: linear-gradient(135deg, #C8922A, #E8B55A);
            border: none; border-radius: 30px; color: #000; font-weight: 800; font-size: 0.88rem;
            margin-top: 15px; cursor: pointer; box-shadow: 0 4px 20px rgba(200,146,42,0.4);
        }

        #toastNotif {
            position: fixed; top: calc(20px + env(safe-area-inset-top)); left: 50%; transform: translateX(-50%) translateY(-100px);
            background: #C8922A; color: #000; padding: 10px 24px; border-radius: 30px;
            font-weight: 700; font-size: 0.85rem; z-index: 1000; transition: transform 0.4s ease;
            white-space: nowrap;
        }
        #toastNotif.show { transform: translateX(-50%) translateY(0); }

        /* ============================================
           AJOUT : RESPONSIVE — écrans moyens et petits
           ============================================ */
        @media (max-width: 480px) {
            .tiktok-header { padding: 12px 14px; padding-top: calc(12px + env(safe-area-inset-top)); }
            .tiktok-header .logo { font-size: 0.95rem; }
            .tiktok-header .btn-nav { padding: 6px 12px; font-size: 0.65rem; }
            .video-overlay { padding: 16px 66px 20px 12px; padding-bottom: calc(20px + env(safe-area-inset-bottom)); }
            .user-avatar { width: 34px; height: 34px; }
            .user-details .user-name { font-size: 0.82rem; }
            .user-details .user-handle { font-size: 0.63rem; }
            .video-title { font-size: 0.8rem; }
            .video-desc { font-size: 0.72rem; }
            .product-tag-card { max-width: 74vw; padding: 5px 10px 5px 5px; }
            .product-tag-img { width: 34px; height: 34px; }
            .product-tag-title { font-size: 0.66rem; }
            .product-tag-price { font-size: 0.68rem; }
            .btn-buy-now { padding: 5px 10px; font-size: 0.6rem; }
            .side-actions { right: 8px; bottom: calc(75px + env(safe-area-inset-bottom)); gap: 12px; }
            .side-icon-box { width: 38px; height: 38px; font-size: 1.05rem; }
            .side-btn span { font-size: 0.56rem; }
            .bottom-sheet { padding: 16px; }
        }
        @media (max-width: 360px) {
            .tiktok-header .btn-nav span { display: none; }
            .tiktok-header .btn-nav { padding: 8px; }
            .video-overlay { padding-right: 58px; }
            .product-tag-card { max-width: 70vw; }
        }

        /* Écrans larges (tablette / desktop) : recentrer le flux façon mobile */
        @media (min-width: 768px) {
            .video-item { display: flex; justify-content: center; background: #000; }
            .video-player { max-width: 480px; margin: 0 auto; height: 100%; box-shadow: 0 0 60px rgba(0,0,0,0.6); }
            .video-overlay, .side-actions {
                left: 50%; 
                transform: translateX(-240px);
                max-width: 480px;
            }
            .side-actions { right: auto; transform: translateX(216px); }
            .video-progress { max-width: 480px; left: 50%; transform: translateX(-50%); }
        }
        @media (min-width: 768px) and (max-width: 1023px) {
            .video-player { max-width: 420px; }
            .video-overlay { transform: translateX(-210px); }
            .side-actions { transform: translateX(186px); }
            .video-progress { max-width: 420px; }
        }
    </style>
</head>
<body>

<div id="toastNotif"><i class="bi bi-check-circle-fill"></i> Produit ajouté au panier !</div>

<header class="tiktok-header">
    <a href="../index.php" class="logo">AWA KA <span>SUGU</span></a>
    <div class="actions">
        <a href="../index.php" class="btn-nav"><i class="bi bi-house"></i> <span>Accueil</span></a>
        <a href="../boutique/catalogue.php" class="btn-nav btn-shop"><i class="bi bi-bag-check-fill"></i> <span>Boutique</span></a>
    </div>
</header>

<div class="video-container" id="videoContainer">
    <?php if (empty($videos)): ?>
        <div class="video-item">
            <div style="text-align:center; color:rgba(255,255,255,0.5);">
                <i class="bi bi-camera-reels" style="font-size: 3rem; color:#C8922A;"></i>
                <h3 style="color:#fff; margin-top:10px;">Aucune vidéo disponible</h3>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($videos as $index => $video): 
            $isLocal = isLocalVideo($video);
            $videoUrl = getVideoUrl($video);
            $titre = htmlspecialchars($video['titre'] ?? 'Collection Awa Ka Sugu');
            $description = htmlspecialchars($video['description'] ?? '');
            
            $hasProduct = !empty($video['p_id']);
            $produitId = (int)($video['p_id'] ?? 0);
            $produitNom = htmlspecialchars($video['produit_nom'] ?? 'Article présent');
            $prixValeur = ($video['produit_promo'] > 0) ? $video['produit_promo'] : ($video['produit_prix'] ?? 0);
            $produitPrix = number_format($prixValeur, 0, ',', ' ') . ' FCFA';
            $produitImg = !empty($video['produit_image']) ? '../uploads/produits/' . $video['produit_image'] : 'https://placehold.co/100x100/C8922A/FFF?text=Article';
        ?>
        <div class="video-item" data-index="<?= $index ?>" data-video-id="<?= (int)$video['id'] ?>">
            <div class="video-player">
                <?php if($isLocal): ?>
                    <!-- ✅ CORRECTION : Suppression de muted et preload pour permettre le son -->
                    <video playsinline loop preload="metadata" id="video-<?= $index ?>" data-index="<?= $index ?>">
                        <source src="<?= $videoUrl ?>" type="video/mp4">
                    </video>
                <?php else: ?>
                    <iframe src="<?= $videoUrl ?>" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                <?php endif; ?>
            </div>

            <div class="video-overlay">
                <div class="user-info">
                    <div class="user-avatar">
                        <img src="../assets/images/awa1.jpeg" alt="Awa Doumbia" onerror="this.src='https://placehold.co/40x40/C8922A/FFF?text=A'">
                    </div>
                    <div class="user-details">
                        <div class="user-name">Awa Doumbia <i class="bi bi-patch-check-fill" style="color:#C8922A;"></i></div>
                        <div class="user-handle">@awadoumbia223 • Vendeuse Officielle</div>
                    </div>
                </div>

                <div class="video-title"><?= $titre ?></div>
                <?php if (!empty($description)): ?>
                    <div class="video-desc"><?= $description ?></div>
                <?php endif; ?>

                <?php if ($hasProduct): ?>
                    <div class="product-tag-card">
                        <img src="<?= $produitImg ?>" class="product-tag-img" alt="Produit">
                        <div class="product-tag-info">
                            <div class="product-tag-title"><?= $produitNom ?></div>
                            <div class="product-tag-price"><?= $produitPrix ?></div>
                        </div>
                        <button class="btn-buy-now" onclick="openBottomSheet(<?= $produitId ?>, '<?= addslashes($produitNom) ?>', '<?= $produitPrix ?>', '<?= $produitImg ?>')">
                            <i class="bi bi-bag-plus-fill"></i> Commander
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="side-actions">
                <?php if ($hasProduct): ?>
                    <button class="side-btn gold" onclick="openBottomSheet(<?= $produitId ?>, '<?= addslashes($produitNom) ?>', '<?= $produitPrix ?>', '<?= $produitImg ?>')" title="Acheter">
                        <div class="side-icon-box"><i class="bi bi-cart-check-fill"></i></div>
                        <span>Acheter</span>
                    </button>
                <?php endif; ?>

                <button class="side-btn" onclick="togglePlayPause(<?= $index ?>)" id="playBtn-<?= $index ?>">
                    <div class="side-icon-box"><i class="bi bi-pause-fill"></i></div>
                    <span class="state-label">Pause</span>
                </button>

                <button class="side-btn" onclick="partagerVideo()">
                    <div class="side-icon-box"><i class="bi bi-share-fill"></i></div>
                    <span>Partager</span>
                </button>
            </div>

            <div class="video-progress">
                <div class="bar" id="progressBar-<?= $index ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="bottom-sheet-overlay" id="sheetOverlay" onclick="closeBottomSheet()"></div>
<div class="bottom-sheet" id="bottomSheet">
    <div class="sheet-header">
        <div class="sheet-product">
            <img src="" id="sheetImg" alt="Produit">
            <div>
                <div class="sheet-product-title" id="sheetTitle">Produit</div>
                <div class="sheet-product-price" id="sheetPrice">0 FCFA</div>
            </div>
        </div>
        <button class="btn-close-sheet" onclick="closeBottomSheet()"><i class="bi bi-x-lg"></i></button>
    </div>

    <div class="option-group">
        <span class="option-label">Couleur</span>
        <div class="options-list" id="colorList">
            <div class="option-chip selected" onclick="selectOption(this, 'color')">Standard</div>
            <div class="option-chip" onclick="selectOption(this, 'color')">Noir</div>
            <div class="option-chip" onclick="selectOption(this, 'color')">Or</div>
        </div>
    </div>

    <div class="option-group">
        <span class="option-label">Taille</span>
        <div class="options-list" id="sizeList">
            <div class="option-chip selected" onclick="selectOption(this, 'size')">M</div>
            <div class="option-chip" onclick="selectOption(this, 'size')">L</div>
            <div class="option-chip" onclick="selectOption(this, 'size')">XL</div>
            <div class="option-chip" onclick="selectOption(this, 'size')">XXL</div>
        </div>
    </div>

    <div class="option-group" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="option-label" style="margin-bottom:0;">Quantité</span>
        <div class="qty-picker">
            <button class="qty-btn" onclick="changeQty(-1)">-</button>
            <span class="qty-val" id="sheetQty">1</span>
            <button class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
    </div>

    <button class="btn-add-cart-final" onclick="submitAddToCart()">Ajouter au Panier</button>
</div>

<script>
    const container = document.getElementById('videoContainer');
    const videoItems = document.querySelectorAll('.video-item');
    let videoElements = [];
    let currentIndex = 0;
    let currentSelectedProduct = null;
    let qty = 1;
    let isFirstInteraction = true;

    const videoCibleId = <?= (int)$video_cible ?>;

    videoItems.forEach((item, index) => {
        const vid = document.getElementById('video-' + index);
        if (vid) {
            videoElements[index] = vid;
            
            // ✅ CORRECTION : Gestion de l'audio automatique
            vid.addEventListener('loadedmetadata', function() {
                // La vidéo est chargée, on peut la démarrer sans son puis l'activer au premier clic
                console.log('Vidéo ' + index + ' chargée');
            });
            
            vid.addEventListener('timeupdate', () => {
                const bar = document.getElementById('progressBar-' + index);
                if (bar && vid.duration) {
                    bar.style.width = (vid.currentTime / vid.duration * 100) + '%';
                }
            });
            
            // ✅ CORRECTION : Activation automatique du son au clic
            vid.addEventListener('click', function(e) {
                e.stopPropagation();
                if (this.muted) {
                    this.muted = false;
                    updatePlayState(index, true);
                    showToast('🔊 Son activé');
                } else {
                    // Alterner lecture/pause au clic
                    if (this.paused) {
                        this.play();
                    } else {
                        this.pause();
                    }
                }
            });
        }
    });

    // ✅ CORRECTION : Observer pour la lecture automatique avec son activé
    const observerOptions = {
        root: container,
        threshold: 0.6
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const idx = parseInt(entry.target.getAttribute('data-index'));
                if (idx !== currentIndex) {
                    // Mettre en pause l'ancienne vidéo
                    if (videoElements[currentIndex]) {
                        videoElements[currentIndex].pause();
                        updatePlayState(currentIndex, false);
                    }
                    currentIndex = idx;
                    
                    // Lire la nouvelle vidéo
                    const vid = videoElements[currentIndex];
                    if (vid) {
                        // ✅ CORRECTION : Démarrer avec le son actif (pas de muted)
                        vid.muted = false;
                        vid.play().then(() => {
                            updatePlayState(currentIndex, true);
                            // ✅ CORRECTION : Activer le son automatiquement dès que possible
                            if (vid.muted) {
                                vid.muted = false;
                            }
                        }).catch((err) => {
                            // Si la lecture échoue (autoplay bloqué), on attend une interaction
                            console.log('Lecture automatique bloquée, attente interaction');
                            updatePlayState(currentIndex, false);
                            // ✅ CORRECTION : Au premier clic sur la page, tout se débloque
                            if (isFirstInteraction) {
                                document.addEventListener('click', function unblockAudio() {
                                    document.removeEventListener('click', unblockAudio);
                                    isFirstInteraction = false;
                                    if (videoElements[currentIndex]) {
                                        videoElements[currentIndex].muted = false;
                                        videoElements[currentIndex].play().then(() => {
                                            updatePlayState(currentIndex, true);
                                        }).catch(() => {});
                                    }
                                }, { once: true });
                            }
                        });
                    }
                }
            }
        });
    }, observerOptions);

    videoItems.forEach(item => observer.observe(item));

    // ✅ CORRECTION : Activer le son sur toute interaction avec la page
    document.addEventListener('click', function() {
        if (isFirstInteraction) {
            isFirstInteraction = false;
            const vid = videoElements[currentIndex];
            if (vid) {
                vid.muted = false;
                vid.play().then(() => {
                    updatePlayState(currentIndex, true);
                }).catch(() => {});
            }
        }
    }, { once: false });

    function togglePlayPause(index) {
        const vid = videoElements[index];
        if (!vid) return;

        if (vid.paused) {
            // ✅ CORRECTION : Désactiver le mute avant de jouer
            vid.muted = false;
            vid.play().then(() => {
                updatePlayState(index, true);
            }).catch(() => {});
        } else {
            vid.pause();
            updatePlayState(index, false);
        }
    }

    function updatePlayState(index, isPlaying) {
        const btn = document.getElementById('playBtn-' + index);
        if (!btn) return;
        const icon = btn.querySelector('i');
        const label = btn.querySelector('.state-label');
        if (isPlaying) {
            icon.className = 'bi bi-pause-fill';
            label.textContent = 'Pause';
        } else {
            icon.className = 'bi bi-play-fill';
            label.textContent = 'Lecture';
        }
    }

    function openBottomSheet(id, title, price, img) {
        currentSelectedProduct = id;
        qty = 1;
        document.getElementById('sheetQty').textContent = qty;
        document.getElementById('sheetTitle').textContent = title;
        document.getElementById('sheetPrice').textContent = price;
        document.getElementById('sheetImg').src = img;

        document.getElementById('sheetOverlay').classList.add('active');
        document.getElementById('bottomSheet').classList.add('active');
        
        // ✅ CORRECTION : Pause de la vidéo pendant le panier
        const vid = videoElements[currentIndex];
        if (vid) {
            vid.pause();
            updatePlayState(currentIndex, false);
        }
    }

    function closeBottomSheet() {
        document.getElementById('sheetOverlay').classList.remove('active');
        document.getElementById('bottomSheet').classList.remove('active');
        
        // ✅ CORRECTION : Remettre la vidéo en lecture après fermeture
        const vid = videoElements[currentIndex];
        if (vid && vid.paused) {
            vid.muted = false;
            vid.play().then(() => {
                updatePlayState(currentIndex, true);
            }).catch(() => {});
        }
    }

    function selectOption(chip, type) {
        const parent = chip.parentElement;
        parent.querySelectorAll('.option-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');
    }

    function changeQty(delta) {
        qty = Math.max(1, qty + delta);
        document.getElementById('sheetQty').textContent = qty;
    }

    function submitAddToCart() {
        if (!currentSelectedProduct) return;

        const formData = new FormData();
        formData.append('action', 'ajouter_panier');
        formData.append('produit_id', currentSelectedProduct);
        formData.append('quantite', qty);

        fetch('../boutique/catalogue.php', {
            method: 'POST',
            body: formData
        })
        .then(async res => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                return { success: res.ok, message: "Produit ajouté" };
            }
        })
        .then(data => {
            closeBottomSheet();
            if (data.success || data.status === 'success') {
                showToast("Produit ajouté au panier !");
            } else {
                alert(data.message || "Erreur lors de l'ajout");
            }
        })
        .catch(() => alert("Erreur lors de la connexion"));
    }

    function showToast(msg) {
        const toast = document.getElementById('toastNotif');
        toast.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${msg}`;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function partagerVideo() {
        if (navigator.share) {
            navigator.share({ title: 'Awa Ka Sugu - Vidéo', url: window.location.href });
        } else {
            navigator.clipboard.writeText(window.location.href);
            showToast("Lien copié !");
        }
    }

    // ✅ CORRECTION : Démarrage automatique avec son dès que la page est chargée
    window.addEventListener('load', () => {
        let startIndex = 0;

        if (videoCibleId > 0) {
            const target = document.querySelector('.video-item[data-video-id="' + videoCibleId + '"]');
            if (target) {
                target.scrollIntoView({ behavior: 'auto', block: 'start' });
                startIndex = parseInt(target.getAttribute('data-index'));
            }
        }

        currentIndex = startIndex;

        const vid = videoElements[startIndex];
        if (vid) {
            // ✅ CORRECTION : Démarrer avec le son activé
            vid.muted = false;
            vid.play().then(() => {
                updatePlayState(startIndex, true);
            }).catch(() => {
                // Si autoplay bloqué, on attend une interaction
                updatePlayState(startIndex, false);
                isFirstInteraction = true;
            });
        }
    });

    // ✅ CORRECTION : Gestion du volume au survol (pour desktop)
    document.addEventListener('mouseenter', function() {
        const vid = videoElements[currentIndex];
        if (vid && !vid.muted) {
            vid.volume = 0.5;
        }
    }, { once: false });
</script>

<?php require_once '../includes/footer.php'; ?>