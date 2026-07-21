<?php
// ============================================
// INDEX - AWA KA SUGU
// Page d'accueil complète avec splash screen
// ============================================

session_name('PUBLIC_SESSION');
session_start();

// ============================================
// SPLASH SCREEN - AWA KA SUGU
// ============================================
$show_splash = !isset($_GET['splash_done']);

if ($show_splash) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title>Bienvenue chez Awa Ka Sugu</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Jost:wght@300;400;500;600;700&display=swap');
            @import url('https://fonts.googleapis.com/css2?family=Amiri&display=swap');

            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Jost', sans-serif;
                min-height: 100vh;
                background: linear-gradient(135deg, #0A0806 0%, #1A1510 50%, #0A0806 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                position: relative;
            }
            .particles {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                z-index: 1;
            }
            .particle {
                position: absolute;
                background: radial-gradient(circle, rgba(200,146,42,0.4), transparent);
                border-radius: 50%;
                animation: floatParticle 8s infinite ease-in-out;
            }
            @keyframes floatParticle {
                0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
                50% { opacity: 0.5; }
                100% { transform: translateY(-100px) translateX(50px); opacity: 0; }
            }
            .particle:nth-child(1) { width: 300px; height: 300px; top: -150px; left: -150px; animation-delay: 0s; }
            .particle:nth-child(2) { width: 200px; height: 200px; bottom: -100px; right: -100px; animation-delay: 1s; }
            .particle:nth-child(3) { width: 150px; height: 150px; top: 50%; left: 10%; animation-delay: 2s; }
            .particle:nth-child(4) { width: 100px; height: 100px; bottom: 20%; right: 15%; animation-delay: 0.5s; }
            .particle:nth-child(5) { width: 250px; height: 250px; top: 30%; right: -50px; animation-delay: 1.5s; }
            .particle:nth-child(6) { width: 80px; height: 80px; top: 70%; left: 20%; animation-delay: 2.5s; }

            .splash-container {
                position: relative;
                z-index: 10;
                text-align: center;
                padding: 40px;
                max-width: 600px;
                width: 90%;
                animation: fadeInScale 0.8s ease-out;
            }
            @keyframes fadeInScale {
                from { opacity: 0; transform: scale(0.9); }
                to { opacity: 1; transform: scale(1); }
            }
            .logo-wrapper {
                margin-bottom: 30px;
                animation: floatLogo 3s ease-in-out infinite;
            }
            @keyframes floatLogo {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            .logo-ring {
                position: relative;
                width: 100px;
                height: 100px;
                margin: 0 auto;
            }
            .logo-ring-outer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: linear-gradient(135deg, #C8922A, #F5D78C, #C8922A);
                animation: rotateRing 3s linear infinite;
            }
            @keyframes rotateRing {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .logo-ring-inner {
                position: absolute;
                top: 6px;
                left: 6px;
                width: calc(100% - 12px);
                height: calc(100% - 12px);
                border-radius: 50%;
                background: #0D0D0D;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .logo-ring-inner span {
                font-family: 'Playfair Display', serif;
                font-size: 2.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, #C8922A, #F5D78C);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
            }
            .welcome-message { margin-bottom: 25px; }
            .salutation {
                font-size: 2.2rem;
                font-family: 'Playfair Display', serif;
                color: #FFFFFF;
                text-shadow: 0 0 20px rgba(200,146,42,0.5), 0 2px 5px rgba(0,0,0,0.3);
                margin-bottom: 10px;
                letter-spacing: 2px;
                animation: glowPulse 2s ease-in-out infinite;
                font-weight: 700;
            }
            @keyframes glowPulse {
                0%, 100% { text-shadow: 0 0 10px rgba(200,146,42,0.3), 0 2px 5px rgba(0,0,0,0.3); }
                50% { text-shadow: 0 0 25px rgba(200,146,42,0.6), 0 2px 5px rgba(0,0,0,0.3); }
            }
            .arabic {
                font-size: 1.8rem;
                font-family: 'Amiri', serif;
                color: #C8922A;
                margin-bottom: 15px;
                direction: rtl;
                text-shadow: 0 0 10px rgba(200,146,42,0.3);
            }
            .greeting-text {
                font-size: 1rem;
                color: rgba(255,255,255,0.9);
                line-height: 1.5;
                font-weight: 500;
                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
            }
            .greeting-text strong { color: #C8922A; font-weight: 700; }
            .awa-photo {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                margin: 20px auto;
                border: 2px solid #C8922A;
                overflow: hidden;
                box-shadow: 0 8px 20px rgba(200,146,42,0.3);
                animation: pulseBorder 2s ease-in-out infinite;
            }
            @keyframes pulseBorder {
                0%, 100% { border-color: #C8922A; box-shadow: 0 8px 20px rgba(200,146,42,0.3); }
                50% { border-color: #E8B55A; box-shadow: 0 12px 30px rgba(200,146,42,0.5); }
            }
            .awa-photo img { width: 100%; height: 100%; object-fit: cover; }
            .citation {
                font-style: italic;
                color: rgba(255,255,255,0.7);
                font-size: 0.85rem;
                margin: 15px 0;
                padding: 12px 18px;
                background: rgba(200,146,42,0.1);
                border-radius: 20px;
                border-left: 3px solid #C8922A;
            }
            .citation i { color: #C8922A; margin-right: 8px; }
            .loader {
                margin-top: 25px;
                display: flex;
                justify-content: center;
                gap: 8px;
            }
            .loader-dot {
                width: 8px;
                height: 8px;
                background: #C8922A;
                border-radius: 50%;
                animation: bounce 1.4s ease-in-out infinite;
            }
            .loader-dot:nth-child(1) { animation-delay: 0s; }
            .loader-dot:nth-child(2) { animation-delay: 0.2s; }
            .loader-dot:nth-child(3) { animation-delay: 0.4s; }
            @keyframes bounce {
                0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
                40% { transform: scale(1); opacity: 1; }
            }
            .redirect-text {
                margin-top: 20px;
                font-size: 0.7rem;
                color: rgba(255,255,255,0.4);
                font-weight: 500;
                letter-spacing: 1px;
            }
            @media (max-width: 600px) {
                .salutation { font-size: 1.6rem; }
                .arabic { font-size: 1.3rem; }
                .greeting-text { font-size: 0.85rem; }
                .logo-ring { width: 75px; height: 75px; }
                .logo-ring-inner span { font-size: 1.8rem; }
                .awa-photo { width: 70px; height: 70px; }
            }
        </style>
    </head>
    <body>
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        <div class="splash-container">
            <div class="logo-wrapper">
                <div class="logo-ring">
                    <div class="logo-ring-outer"></div>
                    <div class="logo-ring-inner"><span>A</span></div>
                </div>
            </div>
            <div class="welcome-message">
                <div class="salutation">As-Salam Alaykoum</div>
                <div class="arabic">السلام عليكم</div>
                <p class="greeting-text">
                    <strong>Awa Doumbia</strong> vous souhaite la bienvenue<br>
                    dans l'univers <strong>Awa Ka Sugu</strong>
                </p>
            </div>
            <div class="awa-photo">
                <img src="assets/images/awa1.jpeg" alt="Awa Doumbia" onerror="this.src='https://via.placeholder.com/90x90/C8922A/FFF?text=Awa'">
            </div>
            <div class="citation">
                <i class="bi bi-chat-quote-fill"></i>
                "Que la paix et la bénédiction d'Allah soient sur vous."
            </div>
            <div class="loader">
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
            </div>
            <div class="redirect-text">
                Redirection vers le site dans <span id="countdown">5</span> secondes...
            </div>
        </div>
        <script>
            let timeLeft = 5;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                timeLeft--;
                if (countdownElement) {
                    countdownElement.textContent = timeLeft;
                }
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = '?splash_done=1';
                }
            }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// PAGE D'ACCUEIL - AWA KA SUGU
// ============================================
$titre_page = 'Accueil';
$meta_desc  = 'Awa Ka Sugu — Boutique IBA Design & Restaurant Sofia. Mode et cuisine malienne à Bamako.';

// ============================================
// INCLUSIONS
// ============================================
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// ============================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================
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
// RÉCUPÉRATION DES NOUVEAUTÉS (4 derniers produits)
// ============================================
$nouveautes = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE est_visible = 1 ORDER BY created_at DESC LIMIT 4");
    $stmt->execute();
    $nouveautes = $stmt->fetchAll();
} catch(PDOException $e) {
    $nouveautes = [];
}

$flash = get_message();

// ============================================
// FONCTION POUR OBTENIR L'IMAGE DU PRODUIT
// ============================================
function getProductImageHome($image) {
    if (empty($image)) {
        return 'https://placehold.co/400x500/F5F5F5/C8922A?text=Produit';
    }
    
    $image = trim($image);
    $image_name = pathinfo($image, PATHINFO_FILENAME);
    $extension = pathinfo($image, PATHINFO_EXTENSION);
    
    // Tous les dossiers possibles
    $dossiers = [
        // Voiles
        'uploads/produits/voile/',
        // Prêt-à-porter femme
        'uploads/produits/pret a porter femme/',
        // Tallons
        'uploads/produits/les tallons/',
        // Fermées
        'uploads/produits/fermés/',
        // Turbants
        'uploads/produits/les turbants/',
        // Foulards
        'uploads/produits/les foulards/',
        'uploads/produits/les foullards/',
        // Porte-monnaie
        'uploads/produits/port-monaie/',
        // Sacs à mains
        'uploads/produits/sacs a mains/',
        'uploads/produits/sacs-a-mains/',
        // Ensemble tallons sacs
        'uploads/produits/ensemble tallons sacs/',
        'uploads/produits/ensemble-tallons-sacs/',
        // Abayas
        'uploads/produits/abayas/',
        // Abayas enfants
        'uploads/produits/abayas pour enfants/',
        'uploads/produits/abayas-pour-enfants/',
        // Dossier principal
        'uploads/produits/',
    ];
    
    $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
    
    if (!empty($extension)) {
        $extensions = array_merge([$extension], $extensions);
    }
    
    foreach ($dossiers as $dossier) {
        foreach ($extensions as $ext) {
            $path = $dossier . $image_name . $ext;
            if (file_exists($path)) {
                return $path;
            }
        }
    }
    
    return 'https://placehold.co/400x500/F5F5F5/C8922A?text=' . urlencode($image_name);
}
?>

<style>
/* ========================================
   DESIGN PREMIUM AWA KA SUGU - PAGE ACCUEIL
   ======================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Jost', sans-serif;
    background: #FFFFFF;
    overflow-x: hidden;
}

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(60px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideRight {
    from { opacity: 0; transform: translateX(-60px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes slideLeft {
    from { opacity: 0; transform: translateX(60px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes floatSlow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

/* ===== HERO SECTION ===== */
.hero-premium {
    position: relative;
    min-height: 90vh;
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
    display: flex;
    align-items: center;
    overflow: hidden;
}

.hero-premium::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: repeating-linear-gradient(90deg, rgba(200,146,42,0.03) 0px, rgba(200,146,42,0.03) 1px, transparent 1px, transparent 60px);
    pointer-events: none;
}

.hero-premium::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 40%;
    height: 100%;
    background: radial-gradient(ellipse at 70% 50%, rgba(200,146,42,0.08), transparent);
    pointer-events: none;
}

.hero-premium .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 60px;
    width: 100%;
    z-index: 3;
    position: relative;
}

.hero-premium .row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 60px;
}

.hero-content {
    flex: 1;
    max-width: 580px;
    animation: slideRight 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(200,146,42,0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(200,146,42,0.3);
    padding: 8px 20px 8px 16px;
    border-radius: 50px;
    margin-bottom: 30px;
}

.hero-badge i { color: #C8922A; font-size: 0.8rem; }
.hero-badge span { color: #C8922A; font-size: 0.7rem; font-weight: 500; letter-spacing: 2px; text-transform: uppercase; }

.hero-content h1 {
    font-family: 'Playfair Display', serif;
    font-size: 4.2rem;
    font-weight: 800;
    color: #FFFFFF;
    line-height: 1.1;
    margin-bottom: 25px;
}

.hero-content h1 span {
    color: #C8922A;
    position: relative;
    display: inline-block;
}

.hero-content h1 span::after {
    content: '';
    position: absolute;
    bottom: 8px;
    left: 0;
    width: 100%;
    height: 8px;
    background: rgba(200,146,42,0.3);
    border-radius: 4px;
    z-index: -1;
}

.hero-description {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.7);
    line-height: 1.7;
    margin-bottom: 35px;
}

.hero-buttons {
    display: flex;
    gap: 20px;
    margin-bottom: 50px;
    flex-wrap: wrap;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #1A1A1A;
    padding: 14px 38px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
    letter-spacing: 1px;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(200,146,42,0.4);
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.btn-primary:hover::before { left: 100%; }
.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(200,146,42,0.6);
    color: #FFFFFF;
}

.btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: transparent;
    border: 2px solid rgba(200,146,42,0.6);
    color: #C8922A;
    padding: 14px 38px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.btn-outline:hover {
    background: rgba(200,146,42,0.1);
    border-color: #C8922A;
    transform: translateY(-3px);
    color: #E8B55A;
}

.hero-stats {
    display: flex;
    gap: 50px;
    padding-top: 30px;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.stat-item { flex: 1; }
.stat-number {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 800;
    color: #C8922A;
    line-height: 1;
}
.stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-top: 5px;
}

.hero-image {
    flex: 1;
    position: relative;
    animation: slideLeft 0.8s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
}

.hero-image .image-wrapper {
    position: relative;
    border-radius: 30px;
    overflow: hidden;
    background: linear-gradient(135deg, rgba(200,146,42,0.1), transparent);
}

.hero-image img {
    width: 100%;
    max-width: 480px;
    border-radius: 30px;
    display: block;
    margin: 0 auto;
    transition: all 0.5s;
    border: 2px solid rgba(200,146,42,0.2);
}

.hero-image img:hover {
    transform: scale(1.02);
    border-color: rgba(200,146,42,0.5);
}

.floating-card {
    position: absolute;
    bottom: 30px;
    left: -20px;
    background: rgba(20, 20, 20, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 15px 25px;
    display: flex;
    align-items: center;
    gap: 14px;
    border: 1px solid rgba(200,146,42,0.3);
    animation: floatSlow 3s ease-in-out infinite;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.floating-card i { font-size: 1.6rem; color: #C8922A; }
.floating-card .text { font-size: 0.85rem; color: rgba(255,255,255,0.8); }
.floating-card .text strong { color: #C8922A; display: block; font-size: 1rem; }

/* ===== SECTIONS ===== */
.section { padding: 100px 0; }
.container-custom { max-width: 1300px; margin: 0 auto; padding: 0 40px; }

.section-header { text-align: center; margin-bottom: 60px; }
.section-subtitle {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #C8922A;
    margin-bottom: 12px;
    display: inline-block;
    position: relative;
}
.section-subtitle::before,
.section-subtitle::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 30px;
    height: 1px;
    background: #C8922A;
}
.section-subtitle::before { left: -40px; }
.section-subtitle::after { right: -40px; }

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.3rem;
    font-weight: 600;
    color: #1A1A1A;
    margin-bottom: 15px;
}
.section-title em { font-style: italic; color: #C8922A; }

.section-line {
    width: 60px;
    height: 2px;
    background: #C8922A;
    margin: 0 auto;
    position: relative;
}
.section-line::before,
.section-line::after {
    content: '';
    position: absolute;
    width: 8px;
    height: 8px;
    background: #C8922A;
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
}
.section-line::before { left: -12px; }
.section-line::after { right: -12px; }

/* ===== CARDS ===== */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
}

.service-card {
    background: #FFFFFF;
    border: 1px solid #F0F0F0;
    border-radius: 20px;
    padding: 40px 25px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-decoration: none;
    display: block;
}
.service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 40px rgba(0,0,0,0.08);
    border-color: #C8922A;
}
.service-icon {
    width: 70px;
    height: 70px;
    background: rgba(200,146,42,0.1);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    transition: all 0.3s;
}
.service-card:hover .service-icon {
    background: #C8922A;
    transform: rotateY(180deg);
}
.service-card:hover .service-icon i {
    color: white;
    transform: rotateY(180deg);
}
.service-icon i { font-size: 2rem; color: #C8922A; transition: all 0.3s; }
.service-card h3 { font-size: 1.1rem; font-weight: 600; color: #1A1A1A; margin-bottom: 10px; }
.service-card p { font-size: 0.85rem; color: #8A99AA; line-height: 1.6; }

/* ===== PRODUITS ===== */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}

.product-item {
    background: #FFFFFF;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s ease;
    border: 1px solid #F0F0F0;
}
.product-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 30px rgba(0,0,0,0.08);
    border-color: #C8922A;
}
.product-image {
    position: relative;
    height: 280px;
    overflow: hidden;
    background: #F8F8F8;
}
.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}
.product-item:hover .product-image img { transform: scale(1.05); }
.product-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #C8922A;
    color: white;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
}
.product-info { padding: 16px; text-align: center; }
.product-name { font-size: 0.9rem; font-weight: 500; color: #1A1A1A; margin-bottom: 6px; }
.product-price { font-size: 0.95rem; font-weight: 700; color: #C8922A; margin-bottom: 12px; }

.btn-buy-now {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #C8922A;
    color: white;
    padding: 10px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.3s;
    width: 100%;
    position: relative;
    overflow: hidden;
}
.btn-buy-now::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}
.btn-buy-now:hover::before { left: 100%; }
.btn-buy-now:hover { background: #9A6E1A; transform: translateY(-2px); }

.btn-view-all {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: transparent;
    border: 1.5px solid #C8922A;
    color: #C8922A;
    padding: 12px 32px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-view-all:hover {
    background: #C8922A;
    color: white;
    transform: translateY(-2px);
}

/* ===== WHY SECTION ===== */
.why-section {
    background: linear-gradient(135deg, #FFF9F0 0%, #FFFFFF 50%, #FFF9F0 100%);
    position: relative;
    overflow: hidden;
}
.why-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 20% 50%, rgba(200,146,42,0.03) 0%, transparent 50%);
    pointer-events: none;
}
.why-grid-modern {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}
.why-card-modern {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 35px 25px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border: 1px solid rgba(200,146,42,0.1);
}
.why-card-modern::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #C8922A, #E8B55A, #C8922A);
    transform: scaleX(0);
    transition: transform 0.5s ease;
}
.why-card-modern:hover::before { transform: scaleX(1); }
.why-card-modern:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 35px rgba(200,146,42,0.12);
    border-color: rgba(200,146,42,0.2);
}
.why-icon-modern {
    width: 75px;
    height: 75px;
    background: linear-gradient(135deg, rgba(200,146,42,0.1), rgba(200,146,42,0.05));
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    transition: all 0.4s;
}
.why-card-modern:hover .why-icon-modern {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    transform: scale(1.05) rotate(8deg);
}
.why-card-modern:hover .why-icon-modern i { color: white; transform: scale(1.1); }
.why-icon-modern i { font-size: 1.8rem; color: #C8922A; transition: all 0.4s; }
.why-card-modern h4 { font-size: 1.15rem; font-weight: 700; margin-bottom: 10px; color: #1A1A1A; }
.why-card-modern p { font-size: 0.85rem; color: #8A99AA; line-height: 1.6; }

/* ===== NEWSLETTER ===== */
.newsletter-section {
    background: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
    border-radius: 24px;
    padding: 60px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.newsletter-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(200,146,42,0.08) 0%, transparent 70%);
    animation: floatSlow 20s ease-in-out infinite;
}
.newsletter-section h3 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: #FFFFFF; margin-bottom: 12px; position: relative; z-index: 1; }
.newsletter-section p { color: rgba(255,255,255,0.5); margin-bottom: 30px; position: relative; z-index: 1; }
.newsletter-form-modern {
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    max-width: 500px;
    margin: 0 auto;
}
.newsletter-input-group { flex: 1; min-width: 280px; position: relative; }
.newsletter-input-group i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #C8922A;
    font-size: 1rem;
}
.newsletter-form-modern input {
    width: 100%;
    padding: 16px 20px 16px 48px;
    border: 2px solid rgba(200,146,42,0.2);
    border-radius: 50px;
    font-family: 'Jost', sans-serif;
    outline: none;
    transition: all 0.3s;
    background: rgba(255,255,255,0.05);
    color: white;
    font-size: 0.95rem;
}
.newsletter-form-modern input:focus {
    border-color: #C8922A;
    background: rgba(255,255,255,0.1);
    box-shadow: 0 0 0 3px rgba(200,146,42,0.2);
}
.newsletter-form-modern button {
    padding: 16px 36px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #1A1A1A;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}
.newsletter-form-modern button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(200,146,42,0.3);
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    color: #FFFFFF;
}

/* ===== AWA LEGACY ===== */
.awa-legacy-section {
    padding: 100px 0;
    background: linear-gradient(135deg, #FFF9F0 0%, #FDF5E6 50%, #FFF9F0 100%);
    position: relative;
    overflow: hidden;
}
.legacy-wrapper {
    display: grid;
    grid-template-columns: 1fr 1.2fr;
    gap: 60px;
    align-items: center;
}
.legacy-photo-card { position: relative; animation: fadeInScale 0.8s ease-out; }
.legacy-photo-frame {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    padding: 8px;
    border-radius: 30px;
    box-shadow: 0 30px 50px rgba(0,0,0,0.1);
}
.legacy-photo-frame img { width: 100%; border-radius: 24px; display: block; transition: all 0.5s; }
.legacy-photo-frame:hover img { transform: scale(1.02); }
.legacy-photo-badge {
    position: absolute;
    bottom: -20px;
    right: 20px;
    background: #FFFFFF;
    padding: 12px 20px;
    border-radius: 50px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.legacy-photo-badge i { font-size: 1.4rem; color: #C8922A; }
.legacy-content { animation: slideUp 0.8s ease-out; }
.legacy-tag {
    display: inline-block;
    background: rgba(200,146,42,0.12);
    color: #C8922A;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 3px;
    text-transform: uppercase;
    padding: 6px 14px;
    border-radius: 30px;
    margin-bottom: 20px;
}
.legacy-content h2 { font-family: 'Playfair Display', serif; font-size: 2.2rem; font-weight: 700; color: #1A1A1A; margin-bottom: 20px; }
.legacy-content h2 span { color: #C8922A; }
.legacy-intro { font-size: 1rem; color: #4A5568; line-height: 1.7; margin-bottom: 25px; }

.legacy-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}
.legacy-stat {
    background: #FFFFFF;
    padding: 15px;
    border-radius: 16px;
    text-align: center;
    border: 1px solid rgba(200,146,42,0.1);
    transition: all 0.3s;
}
.legacy-stat:hover { transform: translateY(-3px); border-color: rgba(200,146,42,0.3); }
.legacy-stat-number { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 800; color: #C8922A; }
.legacy-stat-label { font-size: 0.7rem; color: #718096; margin-top: 5px; }

.legacy-actions {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid rgba(200,146,42,0.1);
}
.legacy-actions h3 { font-size: 1rem; font-weight: 700; color: #1A1A1A; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
.legacy-actions h3 i { color: #C8922A; }
.actions-list { display: flex; flex-direction: column; gap: 12px; }
.action-item { display: flex; align-items: flex-start; gap: 12px; }
.action-item i { color: #C8922A; font-size: 1rem; margin-top: 2px; }
.action-item p { font-size: 0.85rem; color: #4A5568; line-height: 1.5; }
.action-item strong { color: #1A1A1A; }

.legacy-quote {
    background: linear-gradient(135deg, rgba(200,146,42,0.08), rgba(200,146,42,0.02));
    border-left: 3px solid #C8922A;
    padding: 18px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}
.legacy-quote i { color: #C8922A; font-size: 1.2rem; opacity: 0.5; margin-bottom: 8px; display: inline-block; }
.legacy-quote p { font-style: italic; color: #2D3748; line-height: 1.6; margin-bottom: 8px; }
.legacy-quote span { font-size: 0.75rem; color: #A0AEC0; }

.legacy-social { display: flex; gap: 15px; flex-wrap: wrap; }
.social-icon-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: #FFFFFF;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid rgba(200,146,42,0.2);
    flex: 1;
    min-width: 120px;
    justify-content: center;
}
.social-icon-link i { font-size: 1.2rem; }
.social-icon-link span { font-size: 0.8rem; font-weight: 500; }
.social-icon-link.tiktok { color: #000000; }
.social-icon-link.tiktok:hover { background: #000000; color: #FFFFFF; }
.social-icon-link.instagram { color: #E4405F; }
.social-icon-link.instagram:hover { background: #E4405F; color: #FFFFFF; }
.social-icon-link.facebook { color: #1877F2; }
.social-icon-link.facebook:hover { background: #1877F2; color: #FFFFFF; }
.social-icon-link:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

/* ===== RESPONSIVE ===== */
@media (max-width: 1100px) {
    .cards-grid, .products-grid, .why-grid-modern { grid-template-columns: repeat(2, 1fr); }
    .legacy-wrapper { grid-template-columns: 1fr; gap: 40px; }
    .legacy-photo-card { max-width: 450px; margin: 0 auto; }
}
@media (max-width: 900px) {
    .hero-premium .row { flex-direction: column; text-align: center; }
    .hero-content { max-width: 100%; }
    .hero-buttons { justify-content: center; }
    .hero-stats { justify-content: center; }
    .floating-card { left: 50%; transform: translateX(-50%); bottom: -20px; }
    .section-subtitle::before, .section-subtitle::after { display: none; }
}
@media (max-width: 700px) {
    .cards-grid, .products-grid, .why-grid-modern { grid-template-columns: 1fr; }
    .container-custom { padding: 0 20px; }
    .section { padding: 60px 0; }
    .section-title { font-size: 1.8rem; }
    .hero-content h1 { font-size: 2.5rem; }
    .newsletter-section { padding: 40px 20px; }
    .legacy-social { flex-direction: column; }
    .hero-premium .container { padding: 0 30px; }
}
</style>

<!-- ===== FLASH MESSAGE ===== -->
<?php if ($flash): ?>
    <div style="background: #D4EDDA; color: #0A3622; padding: 12px 20px; text-align: center;">
        <?= $flash['texte'] ?>
    </div>
<?php endif; ?>

<!-- ===== HERO SECTION ===== -->
<section class="hero-premium">
    <div class="container">
        <div class="row">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="bi bi-gem"></i>
                    <span>AWA KA SUGU</span>
                </div>
                <h1>L'élégance <span>à la malienne</span></h1>
                <p class="hero-description">
                    Découvrez IBA Design, la référence de la mode modeste au Mali.
                    Robes, abayas, foulards et accessoires d'exception, livrés chez vous à Bamako.
                </p>
                <div class="hero-buttons">
                    <a href="boutique/catalogue.php" class="btn-primary">
                        <i class="bi bi-bag"></i> Explorer la collection
                    </a>
                    <a href="restaurant/menu.php" class="btn-outline">
                        <i class="bi bi-cup-hot"></i> Découvrir Sofia
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-number" id="counterTikTok">0</div>
                        <div class="stat-label">Abonnés TikTok</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="counterInstagram">0</div>
                        <div class="stat-label">Abonnés Instagram</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="counterSales">0</div>
                        <div class="stat-label">Commandes</div>
                    </div>
                </div>
            </div>
            <div class="hero-image">
                <div class="image-wrapper">
                    <img src="assets/images/awa1.jpeg" alt="Awa Doumbia" onerror="this.src='https://via.placeholder.com/500x500/F5F0E8/C8922A?text=Awa'">
                </div>
                <div class="floating-card">
                    <i class="bi bi-chat-quote-fill"></i>
                    <div class="text">
                        <strong>Awa Doumbia</strong>
                        Fondatrice IBA Design
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tiktokCount = 309000;
        const instaCount = 45000;
        const salesCount = 1520;

        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const value = Math.floor(easeOutQuart * (end - start) + start);
                element.innerText = value.toLocaleString() + '+';
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(document.getElementById('counterTikTok'), 0, tiktokCount, 2000);
                    animateCounter(document.getElementById('counterInstagram'), 0, instaCount, 2000);
                    animateCounter(document.getElementById('counterSales'), 0, salesCount, 2000);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        observer.observe(document.querySelector('.hero-stats'));
    });
</script>

<!-- ===== NOS ESPACES ===== -->
<section class="section">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-subtitle">Nos espaces</span>
            <h2 class="section-title">Tout ce qu'<em>Awa</em> vous offre</h2>
            <div class="section-line"></div>
        </div>
        <div class="cards-grid">
            <a href="boutique/catalogue.php" class="service-card">
                <div class="service-icon"><i class="bi bi-bag-heart"></i></div>
                <h3>IBA Design</h3>
                <p>Mode modeste, robes, abayas, foulards et accessoires</p>
            </a>
            <a href="restaurant/menu.php" class="service-card">
                <div class="service-icon"><i class="bi bi-cup-hot"></i></div>
                <h3>Restaurant Sofia</h3>
                <p>Cuisine malienne authentique, plats faits maison</p>
            </a>
            <a href="boutique/promotions.php" class="service-card">
                <div class="service-icon"><i class="bi bi-percent"></i></div>
                <h3>Promotions</h3>
                <p>Offres spéciales et réductions exclusives</p>
            </a>
            <a href="boutique/suivi.php" class="service-card">
                <div class="service-icon"><i class="bi bi-truck"></i></div>
                <h3>Suivi commande</h3>
                <p>Suivez votre commande en temps réel</p>
            </a>
        </div>
    </div>
</section>

<!-- ===== NOUVEAUTÉS ===== -->
<section class="section" style="background: #F8F9FA;">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-subtitle">Dernières créations</span>
            <h2 class="section-title">Nouvelles <em>arrivées</em></h2>
            <div class="section-line"></div>
        </div>
        
        <?php if (!empty($nouveautes)): ?>
            <div class="products-grid">
                <?php foreach ($nouveautes as $p): 
                    $image_produit = getProductImageHome($p['image_principale'] ?? '');
                    $prix_affiché = $p['prix'];
                    if (!empty($p['prix_promo']) && $p['prix_promo'] > 0 && $p['prix_promo'] < $p['prix']) {
                        $prix_affiché = $p['prix_promo'];
                    }
                ?>
                <div class="product-item">
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($image_produit) ?>" 
                             alt="<?= htmlspecialchars($p['nom']) ?>"
                             onerror="this.src='https://placehold.co/400x500/F5F5F5/C8922A?text=<?= urlencode($p['nom']) ?>'">
                        <div class="product-badge">Nouveau</div>
                    </div>
                    <div class="product-info">
                        <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                        <div class="product-price"><?= number_format($prix_affiché, 0, ',', ' ') ?> FCFA</div>
                        <a href="boutique/produit.php?id=<?= $p['id'] ?>" class="btn-buy-now">
                            <i class="bi bi-lightning-charge"></i> Acheter maintenant
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <p style="color: #8A99AA; font-size: 1.1rem;">Aucun produit disponible pour le moment.</p>
                <p style="color: #B0B0B0; font-size: 0.9rem;">Revenez bientôt pour découvrir nos nouveautés.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($nouveautes) && count($nouveautes) >= 4): ?>
            <div class="text-center" style="margin-top: 40px;">
                <a href="boutique/nouveautes.php" class="btn-view-all">
                    <i class="bi bi-eye"></i> Voir toutes les nouveautés
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== POURQUOI CHOISIR ===== -->
<section class="section why-section">
    <div class="container-custom">
        <div class="section-header">
            <span class="section-subtitle">Nos engagements</span>
            <h2 class="section-title">Pourquoi choisir <em>Awa Ka Sugu</em> ?</h2>
            <div class="section-line"></div>
        </div>
        <div class="why-grid-modern">
            <div class="why-card-modern">
                <div class="why-icon-modern"><i class="bi bi-shield-check"></i></div>
                <h4>Paiement sécurisé</h4>
                <p>Orange Money & Wave acceptés</p>
            </div>
            <div class="why-card-modern">
                <div class="why-icon-modern"><i class="bi bi-truck"></i></div>
                <h4>Livraison Bamako</h4>
                <p>Livraison rapide partout à Bamako</p>
            </div>
            <div class="why-card-modern">
                <div class="why-icon-modern"><i class="bi bi-award"></i></div>
                <h4>Qualité garantie</h4>
                <p>Sélectionné par Awa Doumbia</p>
            </div>
            <div class="why-card-modern">
                <div class="why-icon-modern"><i class="bi bi-headset"></i></div>
                <h4>Service client</h4>
                <p>Support réactif 7j/7</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== NEWSLETTER ===== -->
<section class="section" style="padding: 0 40px 100px;">
    <div class="container-custom">
        <div class="newsletter-section">
            <h3>Restez informée</h3>
            <p>Recevez vos newsletters, offres et invitations.</p>
            <form class="newsletter-form-modern" method="GET" action="client/inscription.php">
                <div class="newsletter-input-group">
                    <i class="bi bi-envelope"></i>
                    <input type="email" name="email" placeholder="Votre adresse email" required>
                </div>
                <button type="submit">
                    <i class="bi bi-send"></i> S'inscrire
                </button>
            </form>
        </div>
    </div>
</section>

<!-- ===== SECTION AWA DOUMBIA ===== -->
<section class="awa-legacy-section">
    <div class="container-custom">
        <div class="legacy-wrapper">
            <div class="legacy-photo-card">
                <div class="legacy-photo-frame">
                    <img src="assets/images/awa2.jpeg" alt="Awa Doumbia"
                        onerror="this.src='https://via.placeholder.com/500x500/C8922A/FFF?text=Awa'">
                </div>
                <div class="legacy-photo-badge">
                    <i class="bi bi-instagram"></i>
                    <span>@awadoumbia223</span>
                </div>
            </div>
            <div class="legacy-content">
                <span class="legacy-tag">❤️ UN CŒUR GRAND COMME LE MALI</span>
                <h2>Awa Doumbia, <span>bien plus qu'une influenceuse</span></h2>
                <p class="legacy-intro">
                    Connue sous le nom d'<strong>Awa Ka Sugu</strong>, Awa Doumbia est une figure emblématique du Mali.
                    Derrière l'entrepreneure à succès se cache une femme au grand cœur, dévouée aux plus démunis.
                </p>
                <div class="legacy-stats">
                    <div class="legacy-stat">
                        <div class="legacy-stat-number">128M+</div>
                        <div class="legacy-stat-label">FCFA mobilisés</div>
                    </div>
                    <div class="legacy-stat">
                        <div class="legacy-stat-number">309K+</div>
                        <div class="legacy-stat-label">Abonnés TikTok</div>
                    </div>
                    <div class="legacy-stat">
                        <div class="legacy-stat-number">45K+</div>
                        <div class="legacy-stat-label">Abonnés Instagram</div>
                    </div>
                </div>
                <div class="legacy-actions">
                    <h3><i class="bi bi-heart-fill"></i> Ses actions qui ont marqué</h3>
                    <div class="actions-list">
                        <div class="action-item">
                            <i class="bi bi-fire"></i>
                            <p><strong>Incendie de Sougounicoura (2026)</strong> — Awa Doumbia a mobilisé <strong>128 095 086 FCFA</strong> pour les victimes.</p>
                        </div>
                        <div class="action-item">
                            <i class="bi bi-droplet"></i>
                            <p><strong>Don de sang pour les FAMa</strong> — Organisation d'une journée de don de sang.</p>
                        </div>
                        <div class="action-item">
                            <i class="bi bi-moon-stars"></i>
                            <p><strong>Ramadan 2022 à Tambacounda</strong> — Offrande de repas de rupture du jeûne.</p>
                        </div>
                    </div>
                </div>
                <div class="legacy-quote">
                    <i class="bi bi-quote"></i>
                    <p>"Si aujourd'hui je tiens debout… c'est parce que tu ne te fais jamais assise."</p>
                    <span>— Awa Doumbia</span>
                </div>
                <div class="legacy-social">
                    <a href="https://www.tiktok.com/@awadoumbia223" target="_blank" class="social-icon-link tiktok">
                        <i class="bi bi-tiktok"></i> <span>TikTok</span>
                    </a>
                    <a href="https://www.instagram.com/awadoumbia223" target="_blank" class="social-icon-link instagram">
                        <i class="bi bi-instagram"></i> <span>Instagram</span>
                    </a>
                    <a href="https://www.facebook.com/awadoumbia223" target="_blank" class="social-icon-link facebook">
                        <i class="bi bi-facebook"></i> <span>Facebook</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>