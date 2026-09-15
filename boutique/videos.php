<?php
// ============================================
// VIDÉOS & TENDANCES - Awa Ka Sugu
// Design Premium — Header raffiné
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

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

function getProductImage($image) {
    if (empty($image)) return 'https://placehold.co/100x100/C8922A/FFF?text=Article';
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    $dossiers = [
        '../uploads/produits/',
        '../uploads/produits/abayas/',
        '../uploads/produits/sacs a mains/',
        '../uploads/produits/les tallons/',
        '../uploads/produits/fermés/',
        '../uploads/produits/les turbants/',
        '../uploads/produits/les foulards/',
        '../uploads/produits/voile/',
        '../uploads/produits/port-monaie/',
        '../uploads/produits/pret a porter femme/',
    ];
    
    $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
    if (!empty($extension)) $extensions = array_merge([$extension], $extensions);
    
    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $test_path = $dossier . $image_name . $ext;
            if (file_exists($test_path)) return $test_path;
        }
    }
    return 'https://placehold.co/100x100/C8922A/FFF?text=Article';
}

// ============================================
// RÉCUPÉRATION DES VIDÉOS
// ============================================
$videos = [];
try {
    $sql = "SELECT v.*, 
                   p.id AS p_id, 
                   p.nom AS produit_nom, 
                   p.prix AS produit_prix, 
                   p.prix_promo AS produit_promo, 
                   p.image_principale AS produit_image 
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

$video_cible = isset($_GET['video']) ? (int)$_GET['video'] : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($titre_page) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ═══════════════════════════════════════════
           VIDÉOS — DESIGN PREMIUM
           ═══════════════════════════════════════════ */

        :root {
            --gold: #C8922A;
            --gold-light: #E8C482;
            --gold-deep: #9A6E1A;
            --ink: #0A0A0A;
            --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overscroll-behavior: none; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--ink);
            color: #fff;
            overflow: hidden;
            height: 100vh;
            height: 100dvh;
        }

        /* ═══════════════════════════════════════════
           HEADER PREMIUM — LOGO À CÔTÉ (monogramme + texte)
           ═══════════════════════════════════════════ */
        .tiktok-header {
            position: fixed;
            top: 0; left: 0; right: 0;
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

        /* Bloc logo — monogramme + texte côte à côte */
        .header-logo-group {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        /* Monogramme rond doré */
        .logo-monogram {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0D0D0D;
            border: 1.5px solid transparent;
            background-clip: padding-box;
            box-shadow: 
                0 4px 16px rgba(0,0,0,0.5),
                inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .logo-monogram::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 50%;
            padding: 1.5px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold), var(--gold-deep));
            -webkit-mask: 
                linear-gradient(#fff 0 0) content-box, 
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            pointer-events: none;
        }
        .logo-monogram span {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold), var(--gold-deep));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
            padding-bottom: 2px;
        }

        /* Texte du logo à côté du monogramme */
        .logo-text-group {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }
        .logo-main {
            font-family: 'Playfair Display', serif;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #FFFFFF;
            text-shadow: 0 2px 8px rgba(0,0,0,0.7);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .logo-main .gold {
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: transparent;
        }
        .logo-tagline {
            font-family: 'Inter', sans-serif;
            font-size: 0.52rem;
            font-weight: 500;
            letter-spacing: 2.4px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logo-tagline::before {
            content: '';
            width: 12px;
            height: 1px;
            background: linear-gradient(90deg, var(--gold), transparent);
        }

        /* Actions header */
        .tiktok-header .actions { display: flex; align-items: center; gap: 8px; }

        .tiktok-header .btn-nav {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            font-size: 0.95rem;
            text-decoration: none;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.15);
            color: #fff;
            background: rgba(0,0,0,0.35);
            transition: all 0.3s var(--ease);
        }
        .tiktok-header .btn-nav:hover {
            background: rgba(0,0,0,0.6);
            border-color: rgba(200,146,42,0.5);
            color: var(--gold-light);
            transform: translateY(-1px);
        }
        .tiktok-header .btn-nav.btn-shop {
            background: linear-gradient(135deg, var(--gold), var(--gold-deep));
            border: none;
            box-shadow: 0 6px 20px rgba(200,146,42,0.4);
        }
        .tiktok-header .btn-nav.btn-shop:hover {
            color: #fff;
            box-shadow: 0 8px 26px rgba(200,146,42,0.55);
        }

        /* ═══════════════════════════════════════════
           CONTAINER VIDÉOS
           ═══════════════════════════════════════════ */
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
        .video-player video,
        .video-player iframe {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: none;
        }

        /* ═══════════════════════════════════════════
           OVERLAY VIDÉO
           ═══════════════════════════════════════════ */
        .video-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 24px 82px 28px 20px;
            padding-bottom: calc(28px + env(safe-area-inset-bottom));
            background: linear-gradient(0deg, 
                rgba(0,0,0,0.98) 0%, 
                rgba(0,0,0,0.7) 40%, 
                rgba(0,0,0,0.2) 75%, 
                transparent 100%);
            pointer-events: none;
            z-index: 10;
        }
        .video-overlay > * { pointer-events: auto; }

        /* Bloc auteur */
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            margin-bottom: 14px; 
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--gold);
            box-shadow: 0 0 0 3px rgba(200,146,42,0.2), 0 4px 15px rgba(0,0,0,0.4);
            flex-shrink: 0;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .user-details { min-width: 0; }
        .user-details .user-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-shadow: 0 1px 3px rgba(0,0,0,0.6);
        }
        .user-details .user-name i {
            color: var(--gold);
            font-size: 0.85rem;
        }
        .user-details .user-handle {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        /* Titre & description */
        .video-title { 
            font-family: 'Playfair Display', serif;
            font-size: 1rem; 
            font-weight: 600; 
            color: #fff; 
            margin-bottom: 4px;
            text-shadow: 0 2px 6px rgba(0,0,0,0.6);
            line-height: 1.3;
        }
        .video-desc { 
            font-size: 0.78rem; 
            color: rgba(255,255,255,0.75); 
            line-height: 1.5; 
            margin-bottom: 14px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }

        /* Carte produit premium */
        .product-tag-card {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(20,20,20,0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(200,146,42,0.35);
            padding: 8px 8px 8px 8px;
            border-radius: 14px;
            max-width: min(340px, 82vw);
            box-shadow: 
                0 12px 32px rgba(0,0,0,0.5),
                inset 0 1px 0 rgba(255,255,255,0.06);
            position: relative;
            overflow: hidden;
        }
        .product-tag-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(200,146,42,0.6), transparent);
        }

        .product-tag-img {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .product-tag-info { flex: 1; min-width: 0; }
        .product-tag-title {
            font-size: 0.76rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }
        .product-tag-price { 
            font-size: 0.85rem; 
            color: var(--gold); 
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            letter-spacing: -0.3px;
        }

        /* Bouton unique "Acheter" */
        .btn-buy-now {
            background: linear-gradient(135deg, var(--gold), var(--gold-deep));
            color: #fff;
            border: none;
            padding: 9px 16px;
            border-radius: 22px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.25s var(--ease);
            white-space: nowrap;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(200,146,42,0.35);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-buy-now:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(200,146,42,0.5);
        }
        .btn-buy-now:active { transform: scale(0.96); }

        /* ═══════════════════════════════════════════
           ACTIONS LATÉRALES
           ═══════════════════════════════════════════ */
        .side-actions {
            position: absolute;
            right: 12px;
            bottom: calc(100px + env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            z-index: 20;
        }

        .side-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: #fff;
            background: none;
            border: none;
            cursor: pointer;
        }

        .side-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(15,15,15,0.7);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 
                0 6px 20px rgba(0,0,0,0.4),
                inset 0 1px 0 rgba(255,255,255,0.08);
            transition: all 0.3s var(--ease);
            color: #fff;
        }

        .side-btn:hover .side-icon-box { 
            transform: scale(1.1);
            border-color: rgba(200,146,42,0.5);
            color: var(--gold-light);
        }
        .side-btn:active .side-icon-box { transform: scale(0.95); }

        .side-btn span {
            font-size: 0.62rem;
            font-weight: 600;
            text-shadow: 0 2px 6px rgba(0,0,0,0.9);
            letter-spacing: 0.3px;
        }

        /* Barre de progression fine */
        .video-progress {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: rgba(255,255,255,0.12);
            z-index: 30;
        }
        .video-progress .bar { 
            height: 100%; 
            background: linear-gradient(90deg, var(--gold), var(--gold-light)); 
            width: 0%;
            box-shadow: 0 0 8px rgba(200,146,42,0.6);
            transition: width 0.1s linear;
        }

        /* ═══════════════════════════════════════════
           BOTTOM SHEET
           ═══════════════════════════════════════════ */
        .bottom-sheet-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 200;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s var(--ease);
        }
        .bottom-sheet-overlay.active { opacity: 1; pointer-events: auto; }

        .bottom-sheet {
            position: fixed;
            bottom: -100%;
            left: 0; right: 0;
            background: #0F0F0F;
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
            padding: 22px;
            padding-bottom: calc(22px + env(safe-area-inset-bottom));
            z-index: 201;
            transition: bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
            color: #fff;
            border-top: 1px solid rgba(200,146,42,0.35);
            max-height: 85vh;
            overflow-y: auto;
            max-width: 480px;
            margin: 0 auto;
            box-shadow: 0 -20px 60px rgba(0,0,0,0.6);
        }
        .bottom-sheet::before {
            content: '';
            position: absolute;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 4px;
            background: rgba(255,255,255,0.15);
            border-radius: 4px;
            margin-top: 8px;
        }
        .bottom-sheet.active { bottom: 0; }

        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            margin-top: 12px;
        }
        .sheet-product { 
            display: flex; 
            gap: 14px; 
            align-items: center; 
            flex: 1;
            min-width: 0;
        }
        .sheet-product img { 
            width: 62px; 
            height: 62px; 
            border-radius: 12px; 
            object-fit: cover;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .sheet-product-info { flex: 1; min-width: 0; }
        .sheet-product-title { 
            font-size: 0.92rem; 
            font-weight: 700; 
            color: #fff;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sheet-product-price { 
            color: var(--gold); 
            font-weight: 700; 
            font-size: 1rem;
            font-family: 'Playfair Display', serif;
            letter-spacing: -0.3px;
        }
        .btn-close-sheet { 
            background: rgba(255,255,255,0.08); 
            border: none; 
            color: #fff; 
            font-size: 1rem; 
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.25s var(--ease);
        }
        .btn-close-sheet:hover {
            background: rgba(255,255,255,0.15);
            transform: rotate(90deg);
        }

        .option-group { margin-bottom: 18px; }
        .option-label {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.5);
            margin-bottom: 10px;
            display: block;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .options-list { display: flex; gap: 8px; flex-wrap: wrap; }
        .option-chip {
            padding: 9px 18px;
            border-radius: 22px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            font-size: 0.78rem;
            color: #fff;
            cursor: pointer;
            transition: all 0.25s var(--ease);
            font-weight: 500;
        }
        .option-chip:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(200,146,42,0.4);
        }
        .option-chip.selected {
            background: linear-gradient(135deg, var(--gold), var(--gold-deep));
            color: #fff;
            border-color: transparent;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(200,146,42,0.4);
        }

        .qty-picker {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(255,255,255,0.06);
            padding: 6px 14px;
            border-radius: 24px;
            width: fit-content;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .qty-btn { 
            background: none; 
            border: none; 
            color: #fff; 
            font-size: 1.2rem; 
            cursor: pointer;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s var(--ease);
        }
        .qty-btn:hover {
            background: rgba(200,146,42,0.2);
            color: var(--gold-light);
        }
        .qty-val { 
            font-size: 0.9rem; 
            font-weight: 700; 
            min-width: 24px;
            text-align: center;
        }

        .btn-add-cart-final {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            border: none;
            border-radius: 32px;
            color: #0A0804;
            font-weight: 700;
            font-size: 0.85rem;
            margin-top: 20px;
            cursor: pointer;
            box-shadow: 0 8px 26px rgba(200,146,42,0.4);
            transition: all 0.3s var(--ease);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }
        .btn-add-cart-final:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 34px rgba(200,146,42,0.55); 
        }
        .btn-add-cart-final:active {
            transform: translateY(0) scale(0.98);
        }

        /* Toast */
        #toastNotif {
            position: fixed;
            top: calc(20px + env(safe-area-inset-top));
            left: 50%;
            transform: translateX(-50%) translateY(-120px);
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            color: #0A0804;
            padding: 12px 26px;
            border-radius: 32px;
            font-weight: 700;
            font-size: 0.82rem;
            z-index: 1000;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            white-space: nowrap;
            box-shadow: 0 8px 30px rgba(200,146,42,0.5);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #toastNotif.show { transform: translateX(-50%) translateY(0); }

        /* ═══════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════ */
        @media (max-width: 480px) {
            .tiktok-header { padding: 12px 14px; padding-top: calc(12px + env(safe-area-inset-top)); }
            .logo-monogram { width: 38px; height: 38px; }
            .logo-monogram span { font-size: 1.05rem; }
            .logo-main { font-size: 0.82rem; letter-spacing: 1.5px; }
            .logo-tagline { font-size: 0.48rem; letter-spacing: 2px; }
            .header-logo-group { gap: 10px; }
            .tiktok-header .btn-nav { width: 34px; height: 34px; font-size: 0.9rem; }
            
            .video-overlay { padding: 20px 72px 24px 16px; padding-bottom: calc(24px + env(safe-area-inset-bottom)); }
            .user-avatar { width: 36px; height: 36px; }
            .user-details .user-name { font-size: 0.85rem; }
            .user-details .user-handle { font-size: 0.65rem; }
            .video-title { font-size: 0.92rem; }
            .video-desc { font-size: 0.72rem; }
            
            .product-tag-card { max-width: 78vw; padding: 6px; gap: 10px; }
            .product-tag-img { width: 40px; height: 40px; }
            .product-tag-title { font-size: 0.7rem; }
            .product-tag-price { font-size: 0.78rem; }
            .btn-buy-now { padding: 8px 12px; font-size: 0.66rem; }
            
            .side-actions { right: 10px; bottom: calc(90px + env(safe-area-inset-bottom)); gap: 14px; }
            .side-icon-box { width: 42px; height: 42px; font-size: 1.05rem; }
            .side-btn span { font-size: 0.58rem; }
            
            .bottom-sheet { padding: 18px; }
            .sheet-product img { width: 54px; height: 54px; }
        }

        @media (max-width: 360px) {
            .logo-monogram { width: 34px; height: 34px; }
            .logo-monogram span { font-size: 0.95rem; }
            .logo-main { font-size: 0.72rem; }
            .logo-tagline { display: none; }
            .video-overlay { padding-right: 62px; }
            .product-tag-card { max-width: 74vw; }
            .btn-buy-now span { display: none; }
        }

        /* Écrans larges : recentrer le flux façon mobile */
        @media (min-width: 768px) {
            .video-item { display: flex; justify-content: center; background: #000; }
            .video-player {
                max-width: 480px;
                margin: 0 auto;
                height: 100%;
                box-shadow: 0 0 80px rgba(0,0,0,0.8);
            }
            .video-overlay,
            .side-actions {
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

<div id="toastNotif">
    <i class="bi bi-check-circle-fill"></i>
    <span>Produit ajouté au panier !</span>
</div>

<!-- ═══════════════════════════════════════════
     HEADER PREMIUM — Logo à côté du monogramme
     ═══════════════════════════════════════════ -->
<header class="tiktok-header">
    <a href="../index.php" class="header-logo-group">
        <div class="logo-monogram">
            <span>AK</span>
        </div>
        <div class="logo-text-group">
            <div class="logo-main">AWA KA <span class="gold">SUGU</span></div>
            <div class="logo-tagline">Boutique IBA Design</div>
        </div>
    </a>
    <div class="actions">
        <a href="../index.php" class="btn-nav" title="Accueil">
            <i class="bi bi-house-door-fill"></i>
        </a>
        <a href="../boutique/catalogue.php" class="btn-nav btn-shop" title="Boutique">
            <i class="bi bi-bag-heart-fill"></i>
        </a>
    </div>
</header>

<!-- ═══════════════════════════════════════════
     CONTENU VIDÉOS
     ═══════════════════════════════════════════ -->
<div class="video-container" id="videoContainer">
    <?php if (empty($videos)): ?>
        <div class="video-item">
            <div style="text-align:center; color:rgba(255,255,255,0.5);">
                <i class="bi bi-camera-reels" style="font-size: 3rem; color:var(--gold);"></i>
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
            $produitImg = getProductImage($video['produit_image'] ?? '');
        ?>
        <div class="video-item" data-index="<?= $index ?>" data-video-id="<?= (int)$video['id'] ?>">
            <div class="video-player">
                <?php if($isLocal): ?>
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
                        <div class="user-name">
                            Awa Doumbia 
                            <i class="bi bi-patch-check-fill"></i>
                        </div>
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
                        <button class="btn-buy-now" 
                                onclick="openBottomSheet(<?= $produitId ?>, '<?= addslashes($produitNom) ?>', '<?= $produitPrix ?>', '<?= $produitImg ?>')">
                            <i class="bi bi-bag-plus-fill"></i> Acheter
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="side-actions">
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

<!-- ═══════════════════════════════════════════
     BOTTOM SHEET
     ═══════════════════════════════════════════ -->
<div class="bottom-sheet-overlay" id="sheetOverlay" onclick="closeBottomSheet()"></div>
<div class="bottom-sheet" id="bottomSheet">
    <div class="sheet-header">
        <div class="sheet-product">
            <img src="" id="sheetImg" alt="Produit">
            <div class="sheet-product-info">
                <div class="sheet-product-title" id="sheetTitle">Produit</div>
                <div class="sheet-product-price" id="sheetPrice">0 FCFA</div>
            </div>
        </div>
        <button class="btn-close-sheet" onclick="closeBottomSheet()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="option-group">
        <span class="option-label">Couleur</span>
        <div class="options-list">
            <div class="option-chip selected" onclick="selectOption(this)">Standard</div>
            <div class="option-chip" onclick="selectOption(this)">Noir</div>
            <div class="option-chip" onclick="selectOption(this)">Or</div>
        </div>
    </div>

    <div class="option-group">
        <span class="option-label">Taille</span>
        <div class="options-list">
            <div class="option-chip selected" onclick="selectOption(this)">M</div>
            <div class="option-chip" onclick="selectOption(this)">L</div>
            <div class="option-chip" onclick="selectOption(this)">XL</div>
            <div class="option-chip" onclick="selectOption(this)">XXL</div>
        </div>
    </div>

    <div class="option-group" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="option-label" style="margin-bottom:0;">Quantité</span>
        <div class="qty-picker">
            <button class="qty-btn" onclick="changeQty(-1)">−</button>
            <span class="qty-val" id="sheetQty">1</span>
            <button class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
    </div>

    <button class="btn-add-cart-final" onclick="submitAddToCart()">
        <i class="bi bi-bag-check-fill"></i>
        Ajouter au panier
    </button>
</div>

<script>
// ═══════════════════════════════════════════
// VARIABLES GLOBALES
// ═══════════════════════════════════════════
const container = document.getElementById('videoContainer');
const videoItems = document.querySelectorAll('.video-item');
let videoElements = [];
let currentIndex = 0;
let currentSelectedProduct = null;
let qty = 1;
let audioUnlocked = false;

const videoCibleId = <?= (int)$video_cible ?>;

// ═══════════════════════════════════════════
// INITIALISATION DES VIDÉOS
// ═══════════════════════════════════════════
videoItems.forEach((item, index) => {
    const vid = document.getElementById('video-' + index);
    if (vid) {
        videoElements[index] = vid;
        
        vid.addEventListener('timeupdate', () => {
            const bar = document.getElementById('progressBar-' + index);
            if (bar && vid.duration) {
                bar.style.width = (vid.currentTime / vid.duration * 100) + '%';
            }
        });
        
        vid.addEventListener('click', function(e) {
            e.stopPropagation();
            if (this.paused) {
                unlockAudio();
                this.play();
                updatePlayState(index, true);
            } else {
                this.pause();
                updatePlayState(index, false);
            }
        });
    }
});

// ═══════════════════════════════════════════
// OBSERVER : lecture auto à l'entrée dans le viewport
// ═══════════════════════════════════════════
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const idx = parseInt(entry.target.getAttribute('data-index'));
            if (idx !== currentIndex) {
                if (videoElements[currentIndex]) {
                    videoElements[currentIndex].pause();
                    updatePlayState(currentIndex, false);
                }
                currentIndex = idx;
                playVideo(currentIndex);
            }
        }
    });
}, { root: container, threshold: 0.6 });

videoItems.forEach(item => observer.observe(item));

// ═══════════════════════════════════════════
// FONCTIONS DE LECTURE
// ═══════════════════════════════════════════
function playVideo(index) {
    const vid = videoElements[index];
    if (!vid) return;

    if (audioUnlocked) {
        vid.muted = false;
    } else {
        vid.muted = true;
    }

    vid.play().then(() => {
        updatePlayState(index, true);
        if (vid.muted && audioUnlocked) vid.muted = false;
    }).catch(() => {
        updatePlayState(index, false);
    });
}

function togglePlayPause(index) {
    const vid = videoElements[index];
    if (!vid) return;

    if (vid.paused) {
        unlockAudio();
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

// ═══════════════════════════════════════════
// DÉBLOCAGE AUDIO
// ═══════════════════════════════════════════
function unlockAudio() {
    if (audioUnlocked) return;
    audioUnlocked = true;
    const vid = videoElements[currentIndex];
    if (vid) {
        vid.muted = false;
        if (vid.paused) {
            vid.play().then(() => {
                updatePlayState(currentIndex, true);
            }).catch(() => {});
        }
    }
}

document.addEventListener('click', unlockAudio, { once: true });
document.addEventListener('touchstart', unlockAudio, { once: true });

// ═══════════════════════════════════════════
// BOTTOM SHEET
// ═══════════════════════════════════════════
function openBottomSheet(id, title, price, img) {
    currentSelectedProduct = id;
    qty = 1;
    document.getElementById('sheetQty').textContent = qty;
    document.getElementById('sheetTitle').textContent = title;
    document.getElementById('sheetPrice').textContent = price;
    document.getElementById('sheetImg').src = img;

    document.getElementById('sheetOverlay').classList.add('active');
    document.getElementById('bottomSheet').classList.add('active');
    
    const vid = videoElements[currentIndex];
    if (vid) {
        vid.pause();
        updatePlayState(currentIndex, false);
    }
}

function closeBottomSheet() {
    document.getElementById('sheetOverlay').classList.remove('active');
    document.getElementById('bottomSheet').classList.remove('active');
    
    const vid = videoElements[currentIndex];
    if (vid && vid.paused) {
        vid.play().then(() => {
            updatePlayState(currentIndex, true);
        }).catch(() => {});
    }
}

function selectOption(chip) {
    chip.parentElement.querySelectorAll('.option-chip').forEach(c => c.classList.remove('selected'));
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

// ═══════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════
function showToast(msg) {
    const toast = document.getElementById('toastNotif');
    toast.querySelector('span').textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// ═══════════════════════════════════════════
// PARTAGE
// ═══════════════════════════════════════════
function partagerVideo() {
    if (navigator.share) {
        navigator.share({ title: 'Awa Ka Sugu - Vidéo', url: window.location.href });
    } else {
        navigator.clipboard.writeText(window.location.href);
        showToast("Lien copié !");
    }
}

// ═══════════════════════════════════════════
// DÉMARRAGE
// ═══════════════════════════════════════════
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
    playVideo(startIndex);
});
</script>

<?php require_once '../includes/footer.php'; ?>