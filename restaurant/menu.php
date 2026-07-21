<?php
// ============================================
// MENU COMPLET - RESTAURANT SOFIA
// ============================================

session_name('PUBLIC_SESSION');
session_start();

require_once '../includes/maintenance_check.php';

$titre_page = 'Menu - Restaurant Sofia';
$meta_desc = 'Découvrez le menu complet du Restaurant Sofia par Awa Ka Sugu.';

$host = 'localhost';
$dbname = 'awakasugu_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Pas de base
}

// ============================================
// RÉCUPÉRER TOUS LES PLATS DE LA BASE DE DONNÉES
// ============================================
try {
    // Récupérer tous les plats visibles
    $stmt = $pdo->query("SELECT * FROM plats WHERE est_visible = 1 ORDER BY id");
    $tous_les_plats = $stmt->fetchAll();
    
    // Récupérer les gâteaux
    $stmt = $pdo->query("SELECT * FROM plats WHERE est_visible = 1 AND (categorie = 'Dessert' OR nom LIKE '%Gâteau%' OR nom LIKE '%Cupcake%') ORDER BY id");
    $gateaux = $stmt->fetchAll();
    
    // Récupérer le plat du jour
    $plat_du_jour = $pdo->query("SELECT * FROM plats WHERE est_plat_du_jour = 1 AND est_visible = 1 LIMIT 1")->fetch();
    
} catch(PDOException $e) {
    $tous_les_plats = [];
    $gateaux = [];
    $plat_du_jour = null;
}

// ============================================
// FONCTION SIMPLIFIÉE POUR LES IMAGES
// ============================================
function getImageUrl($nom_plat, $photo_bdd) {
    if(!empty($photo_bdd)) {
        return 'images/' . $photo_bdd;
    }
    return 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($nom_plat);
}

// ============================================
// TRAITEMENT RÉSERVATION
// ============================================
$reservation_success = false;
$reservation_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserver'])) {
    $nom = trim($_POST['nom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $heure = trim($_POST['heure'] ?? '');
    $personnes = (int)($_POST['personnes'] ?? 1);
    $message = trim($_POST['message'] ?? '');

    if (empty($nom) || empty($telephone) || empty($date) || empty($heure)) {
        $reservation_error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reservations (nom_client, telephone, date_reservation, heure_reservation, nb_personnes, notes, statut, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'en_attente', NOW())
            ");
            $stmt->execute([$nom, $telephone, $date, $heure, $personnes, $message]);
            $reservation_success = true;
            header('Location: reservation.php?success=1');
            exit;
        } catch(PDOException $e) {
            $reservation_error = 'Erreur lors de la réservation. Veuillez réessayer.';
        }
    }
}

// ✅ INCLURE HEADER APRÈS TOUS LES TRAITEMENTS
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Restaurant Sofia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           STYLES PRINCIPAUX
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #FAF8F5; color: #1A2C3E; overflow-x: hidden; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(60px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(200,146,42,0.15); }
            50% { box-shadow: 0 0 50px rgba(200,146,42,0.3); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .animate-fade-up {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
        }
        .animate-fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ============================================
           HERO
           ============================================ */
        .hero-restaurant {
            position: relative;
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            background: linear-gradient(160deg, #0D0D0D 0%, #1A1510 40%, #0D0D0D 100%);
        }
        .hero-restaurant::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(200,146,42,0.08) 0%, transparent 60%),
                radial-gradient(circle at 80% 50%, rgba(200,146,42,0.04) 0%, transparent 60%);
            z-index: 1;
        }
        .hero-restaurant .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }
        .hero-restaurant .floating-elements span {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(200,146,42,0.2);
            border-radius: 50%;
            animation: float 10s ease-in-out infinite;
        }
        .hero-restaurant .floating-elements span:nth-child(1) { top: 10%; left: 5%; animation-delay: 0s; width: 8px; height: 8px; }
        .hero-restaurant .floating-elements span:nth-child(2) { top: 20%; left: 15%; animation-delay: 1.2s; }
        .hero-restaurant .floating-elements span:nth-child(3) { top: 35%; left: 30%; animation-delay: 2.4s; }
        .hero-restaurant .floating-elements span:nth-child(4) { top: 55%; left: 45%; animation-delay: 0.6s; width: 10px; height: 10px; }
        .hero-restaurant .floating-elements span:nth-child(5) { top: 70%; left: 60%; animation-delay: 1.8s; }
        .hero-restaurant .floating-elements span:nth-child(6) { top: 85%; left: 75%; animation-delay: 3s; }
        .hero-restaurant .floating-elements span:nth-child(7) { top: 15%; left: 85%; animation-delay: 0.9s; }
        .hero-restaurant .floating-elements span:nth-child(8) { top: 65%; left: 10%; animation-delay: 2.1s; }
        .hero-restaurant .floating-elements span:nth-child(9) { top: 90%; left: 35%; animation-delay: 2.7s; }
        .hero-restaurant .floating-elements span:nth-child(10) { top: 45%; left: 90%; animation-delay: 0.3s; }

        .hero-restaurant .container {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .hero-restaurant .badge {
            display: inline-block;
            background: rgba(200,146,42,0.15);
            backdrop-filter: blur(10px);
            color: #C8922A;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 5px;
            text-transform: uppercase;
            padding: 8px 28px;
            border-radius: 50px;
            margin-bottom: 25px;
            border: 1px solid rgba(200,146,42,0.15);
            animation: fadeInUp 1s ease;
        }
        .hero-restaurant h1 {
            font-family: 'Playfair Display', serif;
            font-size: 4.5rem;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 15px;
            animation: fadeInUp 1s ease 0.2s both;
            text-shadow: 0 4px 30px rgba(0,0,0,0.3);
            line-height: 1.05;
        }
        .hero-restaurant h1 .highlight {
            color: #C8922A;
            position: relative;
            display: inline-block;
        }
        .hero-restaurant h1 .highlight::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #C8922A, #E8B55A);
            border-radius: 4px;
        }
        .hero-restaurant .subtitle {
            font-size: 1.15rem;
            color: rgba(255,255,255,0.5);
            max-width: 550px;
            margin: 0 auto 30px;
            line-height: 1.8;
            animation: fadeInUp 1s ease 0.4s both;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        .hero-restaurant .subtitle strong {
            color: #C8922A;
            font-weight: 600;
        }
        .hero-restaurant .deco {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            animation: fadeInUp 1s ease 0.6s both;
            margin-bottom: 35px;
        }
        .hero-restaurant .deco .line {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #C8922A, transparent);
        }
        .hero-restaurant .deco .dot {
            width: 8px;
            height: 8px;
            background: #C8922A;
            border-radius: 50%;
            animation: glowPulse 2s infinite;
        }
        .hero-restaurant .btn-hero {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 16px 48px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.4s ease;
            animation: fadeInUp 1s ease 0.8s both;
            box-shadow: 0 8px 40px rgba(200,146,42,0.25);
            position: relative;
            overflow: hidden;
        }
        .hero-restaurant .btn-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        .hero-restaurant .btn-hero:hover::before {
            left: 100%;
        }
        .hero-restaurant .btn-hero:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 12px 50px rgba(200,146,42,0.4);
            color: #FFFFFF;
        }

        .container-custom { max-width: 1300px; margin: 0 auto; padding: 0 20px; }

        /* ============================================
           NAVIGATION RAPIDE
           ============================================ */
        .quick-nav {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 10px 16px;
            margin: 20px 0 30px;
            border: 1px solid rgba(200,146,42,0.08);
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            justify-content: center;
            box-shadow: 0 4px 30px rgba(0,0,0,0.05);
            position: sticky;
            top: 75px;
            z-index: 99;
            transition: all 0.3s ease;
        }
        .quick-nav.scrolled {
            box-shadow: 0 8px 40px rgba(0,0,0,0.1);
            border-color: rgba(200,146,42,0.15);
        }
        .quick-nav a {
            color: #5A6B7A;
            text-decoration: none;
            font-size: 0.65rem;
            font-weight: 500;
            padding: 5px 14px;
            border-radius: 30px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            position: relative;
        }
        .quick-nav a::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #C8922A;
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        .quick-nav a:hover::after {
            width: 50%;
        }
        .quick-nav a:hover {
            background: rgba(200,146,42,0.08);
            color: #C8922A;
            transform: translateY(-2px);
        }
        .quick-nav a i { font-size: 0.8rem; color: #C8922A; }

        /* ============================================
           CATÉGORIE - AVEC IMAGES
           ============================================ */
        .menu-category {
            margin-bottom: 50px;
        }
        .menu-category .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 3px solid #C8922A;
            position: relative;
        }
        .menu-category .header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 30%;
            height: 3px;
            background: linear-gradient(90deg, #E8B55A, transparent);
            border-radius: 0 0 3px 0;
        }
        .menu-category .header .cat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid rgba(200,146,42,0.15);
        }
        .menu-category .header .cat-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .menu-category .header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            color: #1A2C3E;
            flex: 1;
        }
        .menu-category .header .count {
            font-size: 0.7rem;
            color: #8A99AA;
            background: #F0F2F5;
            padding: 2px 14px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* ============================================
           GRILLE PRODUITS - IMAGES EN OBJECT FIT COVER
           ============================================ */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 25px;
        }

        .menu-item {
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #EEEAE5;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            position: relative;
        }
        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #C8922A, #E8B55A, #C8922A);
            transform: scaleX(0);
            transition: transform 0.5s ease;
            z-index: 2;
        }
        .menu-item:hover::before {
            transform: scaleX(1);
        }
        .menu-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 55px rgba(0,0,0,0.08);
            border-color: rgba(200,146,42,0.12);
        }

        .menu-item .item-image {
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: #F8F6F4;
            position: relative;
        }
        .menu-item .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s ease;
        }
        .menu-item:hover .item-image img {
            transform: scale(1.08);
        }
        .menu-item .item-image .badge-plat {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(200,146,42,0.9);
            color: #fff;
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            backdrop-filter: blur(4px);
        }
        .menu-item .item-image .badge-plat.populaire {
            background: linear-gradient(135deg, #E74C3C, #C0392B);
        }
        .menu-item .item-image .badge-plat.nouveau {
            background: linear-gradient(135deg, #27AE60, #1A7A4A);
        }
        .menu-item .item-image .badge-plat.sofia {
            background: linear-gradient(135deg, #C8922A, #9A6E1A);
        }

        .menu-item .item-body {
            padding: 16px 18px 18px;
        }
        .menu-item .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
            gap: 10px;
        }
        .menu-item .item-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1A2C3E;
            flex: 1;
            transition: color 0.3s ease;
        }
        .menu-item:hover .item-name {
            color: #C8922A;
        }
        .menu-item .item-price {
            font-size: 1.05rem;
            font-weight: 700;
            color: #C8922A;
            white-space: nowrap;
        }
        .menu-item .item-desc {
            font-size: 0.78rem;
            color: #8A99AA;
            line-height: 1.5;
            margin-bottom: 12px;
            min-height: 36px;
        }
        .menu-item .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            border-top: 1px solid #F0F2F5;
            padding-top: 12px;
        }

        /* ============================================
           BOUTON COMMANDER
           ============================================ */
        .btn-order {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 7px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 700;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .btn-order::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.4s;
        }
        .btn-order:hover::before {
            left: 100%;
        }
        .btn-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(200,146,42,0.3);
            color: #FFFFFF;
        }

        /* ============================================
           PLAT DU JOUR
           ============================================ */
        .plat-jour-section {
            background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
            border-radius: 24px;
            padding: 45px 50px;
            margin: 20px 0 40px;
            border: 2px solid rgba(200,146,42,0.25);
            position: relative;
            overflow: hidden;
            display: flex;
            gap: 45px;
            align-items: center;
            box-shadow: 0 10px 50px rgba(200,146,42,0.08);
        }
        .plat-jour-section::before {
            content: '⭐';
            position: absolute;
            top: -40px;
            right: -40px;
            font-size: 12rem;
            opacity: 0.04;
        }
        .plat-jour-section .badge-jour {
            position: absolute;
            top: 15px;
            left: 25px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            padding: 5px 20px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .plat-jour-section .badge-jour i {
            font-size: 0.8rem;
        }
        .plat-jour-image {
            flex: 0 0 280px;
            height: 280px;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid rgba(200,146,42,0.25);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        .plat-jour-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .plat-jour-image img:hover {
            transform: scale(1.05);
        }
        .plat-jour-image .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2D2D2D;
            color: rgba(255,255,255,0.15);
            font-size: 4rem;
        }
        .plat-jour-info { flex: 1; }
        .plat-jour-info .label {
            display: inline-block;
            background: rgba(200,146,42,0.12);
            color: #C8922A;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 4px 16px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .plat-jour-info h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
            color: #FFFFFF;
            margin-bottom: 8px;
        }
        .plat-jour-info .desc {
            color: rgba(255,255,255,0.6);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 15px;
            max-width: 500px;
        }
        .plat-jour-info .price {
            font-size: 2.2rem;
            font-weight: 700;
            color: #C8922A;
            margin-bottom: 18px;
        }
        .btn-commander-platjour {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 12px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-commander-platjour::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.4s;
        }
        .btn-commander-platjour:hover::before {
            left: 100%;
        }
        .btn-commander-platjour:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 35px rgba(200,146,42,0.35);
            color: #FFFFFF;
        }

        /* ============================================
           GÂTEAUX
           ============================================ */
        .gateaux-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #FFF9F0 0%, #FDF5E6 50%, #FFF9F0 100%);
            border-radius: 30px;
            margin: 30px 0 50px;
            position: relative;
            overflow: hidden;
        }
        .gateaux-section::before {
            content: '🎂';
            position: absolute;
            top: -80px;
            right: -60px;
            font-size: 18rem;
            opacity: 0.03;
        }
        .gateaux-section .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding: 0 30px;
        }
        .gateaux-section .header .cat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid rgba(200,146,42,0.15);
        }
        .gateaux-section .header .cat-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gateaux-section .header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: #1A2C3E;
            flex: 1;
        }
        .gateaux-section .header .count {
            font-size: 0.7rem;
            color: #8A99AA;
            background: #F0F2F5;
            padding: 3px 14px;
            border-radius: 20px;
            font-weight: 500;
        }
        .gateaux-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            padding: 0 30px;
        }
        .gateau-item {
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #EEEAE5;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            position: relative;
        }
        .gateau-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.08);
            border-color: rgba(200,146,42,0.15);
        }
        .gateau-item .gateau-image {
            height: 200px;
            overflow: hidden;
            background: #F8F6F4;
            position: relative;
        }
        .gateau-item .gateau-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .gateau-item:hover .gateau-image img {
            transform: scale(1.06);
        }
        .gateau-item .gateau-image .badge-plat {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(200,146,42,0.9);
            color: #fff;
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            backdrop-filter: blur(4px);
        }
        .gateau-item .gateau-image .badge-plat.populaire {
            background: linear-gradient(135deg, #E74C3C, #C0392B);
        }
        .gateau-item .gateau-image .badge-plat.nouveau {
            background: linear-gradient(135deg, #27AE60, #1A7A4A);
        }
        .gateau-item .gateau-image .badge-plat.sofia {
            background: linear-gradient(135deg, #C8922A, #9A6E1A);
        }
        .gateau-item .gateau-body {
            padding: 18px 20px 20px;
        }
        .gateau-item .gateau-body h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1A2C3E;
            margin-bottom: 4px;
        }
        .gateau-item .gateau-body .desc {
            font-size: 0.78rem;
            color: #8A99AA;
            line-height: 1.5;
            margin-bottom: 12px;
            min-height: 40px;
        }
        .gateau-item .gateau-body .prix {
            font-size: 1rem;
            font-weight: 700;
            color: #C8922A;
            margin-bottom: 12px;
        }
        .gateau-item .gateau-body .prix strong {
            font-size: 1.1rem;
        }
        .btn-commander-gateau {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 700;
            transition: all 0.3s;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .btn-commander-gateau::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.4s;
        }
        .btn-commander-gateau:hover::before {
            left: 100%;
        }
        .btn-commander-gateau:hover {
            background: linear-gradient(135deg, #9A6E1A, #C8922A);
            color: #FFFFFF;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(200,146,42,0.3);
        }

        /* ============================================
           ESPACE ENFANTS
           ============================================ */
        .jeux-section {
            background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
            border-radius: 24px;
            margin: 30px 0 50px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid rgba(200,146,42,0.12);
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .jeux-section .jeux-image {
            height: 100%;
            min-height: 280px;
            overflow: hidden;
            position: relative;
        }
        .jeux-section .jeux-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .jeux-section:hover .jeux-image img {
            transform: scale(1.05);
        }
        .jeux-section .jeux-image .overlay-jeux {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 25px;
            background: linear-gradient(to top, rgba(0,0,0,0.6), transparent);
        }
        .jeux-section .jeux-image .overlay-jeux .tag {
            color: #C8922A;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .jeux-section .jeux-content {
            padding: 40px 35px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        .jeux-section .jeux-content .label {
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #C8922A;
            font-weight: 600;
            display: inline-block;
            background: rgba(200,146,42,0.12);
            padding: 4px 16px;
            border-radius: 20px;
            margin-bottom: 10px;
            align-self: flex-start;
        }
        .jeux-section .jeux-content h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: #FFFFFF;
            margin-bottom: 8px;
        }
        .jeux-section .jeux-content p {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .jeux-section .jeux-features {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 5px 0 15px;
        }
        .jeux-section .jeux-features .feature {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.4);
            font-size: 0.8rem;
        }
        .jeux-section .jeux-features .feature i {
            color: #27AE60;
            font-size: 1.1rem;
        }
        .jeux-section .jeux-prix {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .jeux-section .jeux-prix .price {
            font-size: 2.2rem;
            font-weight: 700;
            color: #C8922A;
        }
        .jeux-section .jeux-prix .price small {
            font-size: 1rem;
            color: rgba(255,255,255,0.4);
            font-weight: 400;
        }

        /* ============================================
           RÉSERVATION
           ============================================ */
        .reservation-section {
            background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
            border-radius: 24px;
            padding: 45px 50px;
            margin: 30px 0 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            border: 1px solid rgba(200,146,42,0.12);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        .reservation-left { position: relative; z-index: 1; }
        .reservation-left .label {
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #C8922A;
            font-weight: 600;
            display: inline-block;
            background: rgba(200,146,42,0.12);
            padding: 4px 16px;
            border-radius: 20px;
            margin-bottom: 10px;
        }
        .reservation-left h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: #FFFFFF;
            margin-bottom: 10px;
        }
        .reservation-left h2 span { color: #C8922A; }
        .reservation-left p {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .reservation-left .infos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .reservation-left .info-item {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        .reservation-left .info-item:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(200,146,42,0.1);
        }
        .reservation-left .info-item i {
            color: #C8922A;
            font-size: 1.2rem;
            display: block;
            margin-bottom: 5px;
        }
        .reservation-left .info-item .val {
            color: #fff;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .reservation-left .info-item .lbl {
            color: rgba(255,255,255,0.3);
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .reservation-right { position: relative; z-index: 1; }
        .reservation-right .form-group {
            margin-bottom: 14px;
        }
        .reservation-right .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 4px;
        }
        .reservation-right .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            font-family: 'Jost', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        .reservation-right .form-control:focus {
            outline: none;
            border-color: #C8922A;
            background: rgba(255,255,255,0.08);
            box-shadow: 0 0 0 3px rgba(200,146,42,0.1);
        }
        .reservation-right .form-control::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .reservation-right .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .btn-reserver {
            width: 100%;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: #1A1A1A;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        .btn-reserver::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.4s;
        }
        .btn-reserver:hover::before {
            left: 100%;
        }
        .btn-reserver:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 35px rgba(200,146,42,0.3);
            color: #FFFFFF;
        }

        .alert-success-reserv {
            background: rgba(39,174,96,0.15);
            border-left: 4px solid #27AE60;
            color: #27AE60;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error-reserv {
            background: rgba(231,76,60,0.15);
            border-left: 4px solid #E74C3C;
            color: #E74C3C;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ============================================
           GALERIE
           ============================================ */
        .galerie-section {
            margin: 40px 0 60px;
        }
        .galerie-section .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid #C8922A;
        }
        .galerie-section .header .cat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid rgba(200,146,42,0.15);
        }
        .galerie-section .header .cat-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .galerie-section .header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            color: #1A2C3E;
            flex: 1;
        }
        .galerie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 15px;
        }
        .galerie-item {
            border-radius: 14px;
            overflow: hidden;
            aspect-ratio: 1/1;
            border: 2px solid #EEEAE5;
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
        }
        .galerie-item:hover {
            transform: scale(1.04);
            border-color: #C8922A;
            box-shadow: 0 10px 35px rgba(0,0,0,0.1);
            z-index: 2;
        }
        .galerie-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .galerie-item:hover img {
            transform: scale(1.05);
        }

        /* ============================================
           FOOTER INFOS
           ============================================ */
        .footer-infos {
            background: #0D0D0D;
            border-radius: 24px;
            padding: 30px 40px;
            margin: 30px 0 40px;
            text-align: center;
            border: 1px solid rgba(200,146,42,0.1);
        }
        .footer-infos p {
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
        }
        .footer-infos p strong {
            color: #C8922A;
        }
        .footer-infos .contact-row {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .footer-infos .contact-row span {
            color: rgba(255,255,255,0.4);
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }
        .footer-infos .contact-row span:hover {
            color: rgba(255,255,255,0.7);
        }
        .footer-infos .contact-row span i {
            color: #C8922A;
        }
        .footer-infos .copyright {
            color: rgba(255,255,255,0.12);
            font-size: 0.65rem;
            margin-top: 15px;
            letter-spacing: 1px;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .hero-restaurant h1 { font-size: 3.5rem; }
            .reservation-section { grid-template-columns: 1fr; padding: 30px 25px; }
            .jeux-section { grid-template-columns: 1fr; }
            .jeux-section .jeux-image { min-height: 200px; }
            .gateaux-grid { grid-template-columns: repeat(2, 1fr); }
            .plat-jour-section { flex-direction: column; text-align: center; padding: 30px 25px; }
            .plat-jour-image { flex: 0 0 220px; height: 220px; width: 100%; max-width: 320px; }
            .plat-jour-info .desc { max-width: 100%; }
        }
        @media (max-width: 768px) {
            .hero-restaurant h1 { font-size: 2.8rem; }
            .hero-restaurant .subtitle { font-size: 1rem; }
            .menu-grid { grid-template-columns: 1fr 1fr; }
            .gateaux-grid { grid-template-columns: 1fr 1fr; }
            .galerie-grid { grid-template-columns: repeat(3, 1fr); }
            .quick-nav a { font-size: 0.55rem; padding: 4px 8px; }
            .reservation-left .infos { grid-template-columns: 1fr; }
            .reservation-right .form-row { grid-template-columns: 1fr; }
            .menu-item .item-image { height: 160px; }
            .footer-infos .contact-row { gap: 12px; flex-direction: column; align-items: center; }
            .jeux-section .jeux-features { gap: 12px; }
            .jeux-section .jeux-prix { flex-direction: column; align-items: flex-start; }
            .gateau-item .gateau-image { height: 160px; }
            .plat-jour-section .badge-jour { top: 10px; left: 15px; font-size: 0.5rem; padding: 4px 14px; }
        }
        @media (max-width: 500px) {
            .hero-restaurant h1 { font-size: 2.2rem; }
            .menu-grid { grid-template-columns: 1fr; max-width: 320px; margin: 0 auto; }
            .gateaux-grid { grid-template-columns: 1fr; max-width: 320px; margin: 0 auto; }
            .galerie-grid { grid-template-columns: repeat(2, 1fr); }
            .hero-restaurant .btn-hero { padding: 14px 32px; font-size: 0.9rem; }
            .quick-nav { top: 65px; padding: 6px 10px; }
            .quick-nav a { font-size: 0.5rem; padding: 3px 6px; }
            .reservation-section { padding: 20px 16px; }
            .gateaux-section .header, .gateaux-grid { padding: 0 16px; }
            .jeux-section .jeux-content .price { font-size: 2rem; }
            .plat-jour-section { padding: 24px 18px; }
            .plat-jour-info h2 { font-size: 1.6rem; }
            .plat-jour-info .price { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

<!-- ============================================
     FLASH MESSAGE
     ============================================ -->
<?php 
if (isset($_SESSION['message_success'])): ?>
    <div style="background: #D4EDDA; color: #0A3622; padding: 12px 20px; text-align: center; border-bottom: 2px solid #27AE60;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['message_success']) ?>
    </div>
    <?php unset($_SESSION['message_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['message_error'])): ?>
    <div style="background: #F8D7DA; color: #721C24; padding: 12px 20px; text-align: center; border-bottom: 2px solid #E74C3C;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_SESSION['message_error']) ?>
    </div>
    <?php unset($_SESSION['message_error']); ?>
<?php endif; ?>

<!-- ============================================
     HERO
     ============================================ -->
<section class="hero-restaurant">
    <div class="floating-elements">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>
    <div class="container">
        <div class="badge">Restaurant Sofia</div>
        <h1>
            L'art de la <span class="highlight">gastronomie</span><br>
            à la malienne
        </h1>
        <p class="subtitle">
            Découvrez une cuisine généreuse et variée, préparée avec passion 
            par <strong>Awa Doumbia</strong>.
        </p>
        <div class="deco">
            <span class="line"></span>
            <span class="dot"></span>
            <span class="line"></span>
        </div>
        <a href="#reservation" class="btn-hero">
            <i class="bi bi-calendar-check"></i> Réserver une table
        </a>
    </div>
</section>

<div class="container-custom">

    <!-- ============================================
         NAVIGATION RAPIDE
         ============================================ -->
    <nav class="quick-nav" id="quickNav">
        <a href="#plat-jour"><i class="bi bi-star-fill" style="color:#C8922A;"></i> Plat du jour</a>
        <a href="#shawarmas"><i class="bi bi-bag"></i> Shawarmas</a>
        <a href="#tacos"><i class="bi bi-bag"></i> Tacos</a>
        <a href="#sandwichs"><i class="bi bi-bag"></i> Sandwichs</a>
        <a href="#burgers"><i class="bi bi-bag"></i> Burgers</a>
        <a href="#specialites"><i class="bi bi-star"></i> Spécialités</a>
        <a href="#pizzas"><i class="bi bi-pizza"></i> Pizzas</a>
        <a href="#plats"><i class="bi bi-egg-fried"></i> Plats</a>
        <a href="#box"><i class="bi bi-box"></i> Box</a>
        <a href="#boissons"><i class="bi bi-cup-straw"></i> Boissons</a>
        <a href="#gateaux"><i class="bi bi-cake"></i> Gâteaux</a>
        <a href="#jeux"><i class="bi bi-controller"></i> Enfants</a>
        <a href="#reservation"><i class="bi bi-calendar-check"></i> Réserver</a>
        <a href="#galerie"><i class="bi bi-images"></i> Galerie</a>
    </nav>

    <!-- ============================================
         PLAT DU JOUR
         ============================================ -->
    <?php if($plat_du_jour): ?>
    <div class="plat-jour-section animate-fade-up" id="plat-jour">
        <span class="badge-jour"><i class="bi bi-star-fill"></i> Plat du jour</span>
        <div class="plat-jour-image">
            <?php if(!empty($plat_du_jour['photo'])): ?>
                <img src="images/<?= htmlspecialchars($plat_du_jour['photo']) ?>" alt="<?= htmlspecialchars($plat_du_jour['nom']) ?>">
            <?php else: ?>
                <div class="no-image"><i class="bi bi-image"></i></div>
            <?php endif; ?>
        </div>
        <div class="plat-jour-info">
            <span class="label">⭐ Sélection du Chef</span>
            <h2><?= htmlspecialchars($plat_du_jour['nom']) ?></h2>
            <p class="desc"><?= htmlspecialchars($plat_du_jour['description'] ?? '') ?></p>
            <div class="price"><?= number_format($plat_du_jour['prix'], 0, ',', ' ') ?> FCFA</div>
            <a href="commande_repas.php?plat_id=<?= $plat_du_jour['id'] ?>" class="btn-commander-platjour">
                <i class="bi bi-bag-plus"></i> Commander maintenant
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         SHAWARMAS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="shawarmas">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Chawarma viande.jpg.jpeg" alt="Shawarmas" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=S'">
            </div>
            <h2>Shawarmas</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    foreach($tous_les_plats as $p) {
                        if(strpos(strtolower($p['nom']), 'shawarma') !== false || strpos(strtolower($p['nom']), 'chawarma') !== false) $count++;
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php foreach($tous_les_plats as $p): 
                if(strpos(strtolower($p['nom']), 'shawarma') !== false || strpos(strtolower($p['nom']), 'chawarma') !== false):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         TACOS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="tacos">
        <div class="header">
            <div class="cat-icon">
                <img src="images/tacos viande.jpg" alt="Tacos" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=T'">
            </div>
            <h2>Tacos</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    foreach($tous_les_plats as $p) {
                        if(strpos(strtolower($p['nom']), 'tacos') !== false) $count++;
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php foreach($tous_les_plats as $p): 
                if(strpos(strtolower($p['nom']), 'tacos') !== false):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         SANDWICHS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="sandwichs">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Sandwich Viande.jpg" alt="Sandwichs" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=Sa'">
            </div>
            <h2>Sandwichs</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    foreach($tous_les_plats as $p) {
                        if(strpos(strtolower($p['nom']), 'sandwich') !== false || strpos(strtolower($p['nom']), 'baguettino') !== false || strpos(strtolower($p['nom']), 'club') !== false) $count++;
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php foreach($tous_les_plats as $p): 
                if(strpos(strtolower($p['nom']), 'sandwich') !== false || strpos(strtolower($p['nom']), 'baguettino') !== false || strpos(strtolower($p['nom']), 'club') !== false):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         BURGERS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="burgers">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Hamburger Viande.jpg" alt="Burgers" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=B'">
            </div>
            <h2>Burgers</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    foreach($tous_les_plats as $p) {
                        if(strpos(strtolower($p['nom']), 'burger') !== false || strpos(strtolower($p['nom']), 'hamburger') !== false) $count++;
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php foreach($tous_les_plats as $p): 
                if(strpos(strtolower($p['nom']), 'burger') !== false || strpos(strtolower($p['nom']), 'hamburger') !== false):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         SPÉCIALITÉS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="specialites">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Brioche Viande.jpg" alt="Spécialités" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=Sp'">
            </div>
            <h2>Spécialités Sofia</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    $specialites_list = ['brioche', 'chicken rolls', 'nuggets', 'wings', 'tenders', 'kids', 'fataya'];
                    foreach($tous_les_plats as $p) {
                        $nom_lower = strtolower($p['nom']);
                        foreach($specialites_list as $spec) {
                            if(strpos($nom_lower, $spec) !== false) { $count++; break; }
                        }
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php 
            $specialites_list = ['brioche', 'chicken rolls', 'nuggets', 'wings', 'tenders', 'kids', 'fataya'];
            foreach($tous_les_plats as $p): 
                $nom_lower = strtolower($p['nom']);
                $is_specialite = false;
                foreach($specialites_list as $spec) {
                    if(strpos($nom_lower, $spec) !== false) { $is_specialite = true; break; }
                }
                if($is_specialite):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         PIZZAS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="pizzas">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Pizza Marguerite.jpg" alt="Pizzas" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=P'">
            </div>
            <h2>Pizzas</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    foreach($tous_les_plats as $p) {
                        if(strpos(strtolower($p['nom']), 'pizza') !== false) $count++;
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php foreach($tous_les_plats as $p): 
                if(strpos(strtolower($p['nom']), 'pizza') !== false):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         PLATS SOFIA - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="plats">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Lasagnes.jpg" alt="Plats" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=Pl'">
            </div>
            <h2>Plats Sofia</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    $plats_list = ['lasagnes', 'poulet mayo', 'big mag', 'steak', 'crêpe', 'croissant'];
                    foreach($tous_les_plats as $p) {
                        $nom_lower = strtolower($p['nom']);
                        foreach($plats_list as $plat) {
                            if(strpos($nom_lower, $plat) !== false) { $count++; break; }
                        }
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php 
            $plats_list = ['lasagnes', 'poulet mayo', 'big mag', 'steak', 'crêpe', 'croissant'];
            foreach($tous_les_plats as $p): 
                $nom_lower = strtolower($p['nom']);
                $is_plat = false;
                foreach($plats_list as $plat) {
                    if(strpos($nom_lower, $plat) !== false) { $is_plat = true; break; }
                }
                if($is_plat):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         BOX SOFIA - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="box">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Box Petit Four.jpg" alt="Box" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=Bx'">
            </div>
            <h2>Box Sofia</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    foreach($tous_les_plats as $p) {
                        if(strpos(strtolower($p['nom']), 'box') !== false) $count++;
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php foreach($tous_les_plats as $p): 
                if(strpos(strtolower($p['nom']), 'box') !== false):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         BOISSONS - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="menu-category animate-fade-up" id="boissons">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Coca Cola.jpg" alt="Boissons" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=B'">
            </div>
            <h2>Boissons</h2>
            <span class="count">
                <?php 
                    $count = 0;
                    $boissons_list = ['smoothie', 'milkshake', 'cocktail', 'mojito', 'coca', 'fanta', 'sprite', 'seven', 'ira', 'jus', 'walter', 'sunset', 'kanou'];
                    foreach($tous_les_plats as $p) {
                        $nom_lower = strtolower($p['nom']);
                        foreach($boissons_list as $boisson) {
                            if(strpos($nom_lower, $boisson) !== false) { $count++; break; }
                        }
                    }
                    echo $count;
                ?>
            </span>
        </div>
        <div class="menu-grid">
            <?php 
            $boissons_list = ['smoothie', 'milkshake', 'cocktail', 'mojito', 'coca', 'fanta', 'sprite', 'seven', 'ira', 'jus', 'walter', 'sunset', 'kanou'];
            foreach($tous_les_plats as $p): 
                $nom_lower = strtolower($p['nom']);
                $is_boisson = false;
                foreach($boissons_list as $boisson) {
                    if(strpos($nom_lower, $boisson) !== false) { $is_boisson = true; break; }
                }
                if($is_boisson):
                    $image_url = !empty($p['photo']) ? 'images/' . $p['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($p['nom']);
            ?>
            <div class="menu-item">
                <div class="item-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($p['nom']) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($p['nom'])?>'">
                    <?php if($p['est_plat_du_jour']): ?>
                        <span class="badge-plat sofia">⭐ Plat du jour</span>
                    <?php endif; ?>
                </div>
                <div class="item-body">
                    <div class="item-header">
                        <span class="item-name"><?= htmlspecialchars($p['nom']) ?></span>
                        <span class="item-price"><?= number_format($p['prix'], 0, ',', ' ') ?> F</span>
                    </div>
                    <div class="item-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                    <div class="item-footer">
                        <a href="commande_repas.php?plat_id=<?= $p['id'] ?>" class="btn-order"><i class="bi bi-cart-plus"></i> Commander</a>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============================================
         GÂTEAUX - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="gateaux-section animate-fade-up" id="gateaux">
        <div class="header">
            <div class="cat-icon">
                <img src="images/Gâteau Anniversaire.jpg" alt="Gâteaux" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=G'">
            </div>
            <h2>Gâteaux Événementiels</h2>
            <span class="count"><?= count($gateaux) ?></span>
        </div>
        <div class="gateaux-grid">
            <?php if(!empty($gateaux)): 
                foreach($gateaux as $g): 
                    $badge_class = '';
                    $badge_text = '';
                    $nom = $g['nom'];
                    
                    if(strpos($nom, 'Anniversaire') !== false) {
                        $badge_class = 'populaire';
                        $badge_text = '⭐ Populaire';
                    } elseif(strpos($nom, 'Mariage') !== false) {
                        $badge_class = 'sofia';
                        $badge_text = '❤️ Sur mesure';
                    } elseif(strpos($nom, 'Enfant') !== false) {
                        $badge_class = 'nouveau';
                        $badge_text = '🎈 Enfant';
                    } elseif(strpos($nom, 'Hadj') !== false || strpos($nom, 'Mecque') !== false) {
                        $badge_class = 'hadj';
                        $badge_text = '🕋 Hadj';
                    } elseif(strpos($nom, 'Aïd') !== false) {
                        $badge_class = 'nouveau';
                        $badge_text = '⭐ Aïd';
                    } elseif(strpos($nom, 'Ramadan') !== false) {
                        $badge_class = 'ramadan';
                        $badge_text = '🌙 Ramadan';
                    } elseif(strpos($nom, 'Entreprise') !== false) {
                        $badge_class = 'entreprise';
                        $badge_text = '🏢 Entreprise';
                    } elseif(strpos($nom, 'Baptême') !== false) {
                        $badge_class = 'sofia';
                        $badge_text = '⛪ Baptême';
                    } elseif(strpos($nom, 'Cupcakes') !== false) {
                        $badge_class = 'nouveau';
                        $badge_text = '🧁 Sur mesure';
                    }
                    
                    $image_url = !empty($g['photo']) ? 'images/' . $g['photo'] : 'https://placehold.co/400x300/C8922A/FFF?text=' . urlencode($nom);
            ?>
            <div class="gateau-item">
                <div class="gateau-image">
                    <img src="<?= $image_url ?>" alt="<?= htmlspecialchars($nom) ?>" onerror="this.src='https://placehold.co/400x300/C8922A/FFF?text=<?= urlencode($nom)?>'">
                    <?php if($badge_text): ?>
                        <span class="badge-plat <?= $badge_class ?>"><?= $badge_text ?></span>
                    <?php endif; ?>
                </div>
                <div class="gateau-body">
                    <h3><?= htmlspecialchars($nom) ?></h3>
                    <div class="desc"><?= htmlspecialchars($g['description'] ?? '') ?></div>
                    <div class="prix">À partir de <strong><?= number_format($g['prix'], 0, ',', ' ') ?> F</strong></div>
                    <a href="commande_gateau.php?gateau_id=<?= $g['id'] ?>" class="btn-commander-gateau">
                        <i class="bi bi-cart-plus"></i> Commander
                    </a>
                </div>
            </div>
            <?php 
                endforeach; 
            else: ?>
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:#999;">
                <i class="bi bi-cake" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                <p>Aucun gâteau disponible pour le moment.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================
         ESPACE ENFANTS
         ============================================ -->
    <div class="jeux-section animate-fade-up" id="jeux">
        <div class="jeux-image">
            <img src="jeu.jpeg" alt="Espace Enfants Sofia" onerror="this.src='https://placehold.co/600x400/C8922A/FFF?text=Espace+Enfants'">
            <div class="overlay-jeux">
                <span class="tag">Espace Loisirs</span>
            </div>
        </div>
        <div class="jeux-content">
            <span class="label">Espace Loisirs</span>
            <h2>Espace Enfants</h2>
            <p>Un espace dédié aux enfants pour jouer et s'amuser en toute sécurité pendant que vous dégustez vos plats.</p>
            <div class="jeux-features">
                <div class="feature"><i class="bi bi-joystick"></i> <span>Jeux variés</span></div>
                <div class="feature"><i class="bi bi-shield-check"></i> <span>Espace sécurisé</span></div>
                <div class="feature"><i class="bi bi-people"></i> <span>Animations</span></div>
            </div>
            <div class="jeux-prix">
                <span class="price">2 000 F <small>/ enfant</small></span>
                <a href="reservation.php?type=jeu" class="btn-order" style="margin-top:10px;display:inline-flex;">
                    <i class="bi bi-calendar-check"></i> Réserver
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================
         RÉSERVATION
         ============================================ -->
    <div class="reservation-section animate-fade-up" id="reservation">
        <div class="reservation-left">
            <span class="label">Réservez votre table</span>
            <h2>Réservez chez <span>Sofia</span></h2>
            <p>Réservez votre table pour vivre une expérience culinaire unique dans une ambiance chaleureuse.</p>
            <div class="infos">
                <div class="info-item">
                    <i class="bi bi-geo-alt"></i>
                    <div class="val">Sebenikoro, face mosquée</div>
                    <div class="lbl">Adresse</div>
                </div>
                <div class="info-item">
                    <i class="bi bi-telephone"></i>
                    <div class="val">+223 74 74 03 03</div>
                    <div class="lbl">Téléphone</div>
                </div>
                <div class="info-item">
                    <i class="bi bi-clock"></i>
                    <div class="val">Lun - Sam : 8h - 21h</div>
                    <div class="lbl">Horaires</div>
                </div>
                <div class="info-item">
                    <i class="bi bi-people"></i>
                    <div class="val">Groupes acceptés</div>
                    <div class="lbl">Capacité</div>
                </div>
            </div>
        </div>
        <div class="reservation-right">
            <?php if($reservation_success): ?>
                <div class="alert-success-reserv">
                    <i class="bi bi-check-circle-fill"></i> Réservation confirmée ! Nous vous attendons.
                </div>
            <?php endif; ?>
            <?php if($reservation_error): ?>
                <div class="alert-error-reserv">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= $reservation_error ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nom complet <span style="color:#E74C3C;">*</span></label>
                    <input type="text" name="nom" class="form-control" placeholder="Votre nom" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone <span style="color:#E74C3C;">*</span></label>
                        <input type="tel" name="telephone" class="form-control" placeholder="77 00 00 00" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="votre@email.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date <span style="color:#E74C3C;">*</span></label>
                        <input type="date" name="date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Heure <span style="color:#E74C3C;">*</span></label>
                        <input type="time" name="heure" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nombre de personnes</label>
                    <input type="number" name="personnes" class="form-control" value="1" min="1" max="20" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <input type="text" name="message" class="form-control" placeholder="Demandes spéciales...">
                </div>
                <button type="submit" name="reserver" class="btn-reserver">
                    <i class="bi bi-calendar-check"></i> Réserver maintenant
                </button>
            </form>
        </div>
    </div>

    <!-- ============================================
         GALERIE - AVEC IMAGE EN ICONE
         ============================================ -->
    <div class="galerie-section animate-fade-up" id="galerie">
        <div class="header">
            <div class="cat-icon">
                <img src="pt2.jpeg" alt="Galerie" onerror="this.src='https://placehold.co/80x80/C8922A/FFF?text=Ga'">
            </div>
            <h2>Galerie Restaurant Sofia</h2>
        </div>
        <div class="galerie-grid" id="galerieGrid">
            <?php for($i=2; $i<=10; $i++): ?>
            <div class="galerie-item" onclick="openLightbox(<?= $i-2 ?>)">
                <img src="pt<?= $i ?>.jpeg" alt="Photo Restaurant Sofia" onerror="this.src='https://placehold.co/300x300/C8922A/FFF?text=Photo'">
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div class="lightbox" id="lightbox" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.92);z-index:99999;justify-content:center;align-items:center;padding:20px;" onclick="closeLightbox()">
        <button onclick="event.stopPropagation(); closeLightbox()" style="position:absolute;top:25px;right:35px;color:#fff;font-size:2.5rem;background:rgba(255,255,255,0.1);width:50px;height:50px;border-radius:50%;border:none;cursor:pointer;">✕</button>
        <img id="lightboxImage" src="" alt="Agrandissement" style="max-width:90%;max-height:85%;object-fit:contain;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
    </div>

    <!-- ============================================
         FOOTER INFOS
         ============================================ -->
    <div class="footer-infos">
        <p>
            <i class="bi bi-chat-quote-fill" style="color:#C8922A;"></i>
            Nous ne sommes pas parfaits, mais vos remarques nous aident à nous améliorer.
            Pour toute suggestion, contactez-nous sur WhatsApp au <strong>66 74 69 85</strong>
        </p>
        <div class="contact-row">
            <span><i class="bi bi-geo-alt"></i> Sebenikoro, face mosquée Mahi Ouattara</span>
            <span><i class="bi bi-telephone"></i> +223 74 74 03 03</span>
            <span><i class="bi bi-whatsapp"></i> +223 66 74 69 85</span>
            <span><i class="bi bi-truck"></i> Livraison partout à Bamako</span>
        </div>
        <div class="copyright">
            @sofiaboulangerie74740303 &bull; Restaurant Sofia &bull; <?= date('Y') ?>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// ============================================
// ANIMATION AU SCROLL
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-fade-up');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    });
    
    elements.forEach(el => observer.observe(el));

    // STICKY NAV
    const quickNav = document.getElementById('quickNav');
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 100) {
            quickNav.classList.add('scrolled');
        } else {
            quickNav.classList.remove('scrolled');
        }
    });
});

// ============================================
// LIGHTBOX
// ============================================
const galleryImages = [];
<?php for($i=2; $i<=10; $i++): ?>
galleryImages.push('pt<?= $i ?>.jpeg');
<?php endfor; ?>

function openLightbox(index) {
    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImage');
    img.src = galleryImages[index] || 'https://placehold.co/800x600/C8922A/FFF?text=Photo';
    lightbox.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>
</body>
</html>