<?php
// ============================================
// SUIVI DE COMMANDE - Awa Ka Sugu
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

require_once '../includes/maintenance_check.php';

$titre_page = 'Suivi de commande - Awa Ka Sugu';
$meta_desc = 'Suivez l\'état de votre commande Awa Ka Sugu.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

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

$commande = null;
$details = [];
$error = '';

if (isset($_POST['rechercher'])) {
    $numero = trim($_POST['numero_commande'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    
    if (empty($numero) || empty($telephone)) {
        $error = 'Veuillez entrer le numéro de commande et votre téléphone.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM commandes WHERE numero_commande = ? AND telephone = ?");
        $stmt->execute([$numero, $telephone]);
        $commande = $stmt->fetch();
        
        if (!$commande) {
            $error = 'Aucune commande trouvée. Vérifiez vos informations.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
            $stmt->execute([$commande['id']]);
            $details = $stmt->fetchAll();
        }
    }
}

$statuts = [
    'en_attente' => ['label' => 'En attente de validation', 'class' => 'warning', 'icone' => 'clock-history', 'pourcentage' => 20],
    'confirmee' => ['label' => 'Commande confirmée', 'class' => 'info', 'icone' => 'check-circle', 'pourcentage' => 40],
    'en_preparation' => ['label' => 'En préparation', 'class' => 'primary', 'icone' => 'box-seam', 'pourcentage' => 60],
    'en_livraison' => ['label' => 'En livraison', 'class' => 'info', 'icone' => 'truck', 'pourcentage' => 80],
    'livree' => ['label' => 'Livrée', 'class' => 'success', 'icone' => 'check-all', 'pourcentage' => 100],
    'annulee' => ['label' => 'Annulée', 'class' => 'danger', 'icone' => 'x-circle', 'pourcentage' => 0]
];
?>

<style>
/* ═══════════════════════════════════════════
   SUIVI DE COMMANDE — DESIGN PREMIUM 2026
   ═══════════════════════════════════════════ */

:root {
    --gold: #C8922A;
    --gold-deep: #9A6E1A;
    --gold-light: #E8C070;
    --ink: #0D0D0D;
    --muted: #8A99AA;
    --line: #EEEAE5;
    --line-soft: #F4F1EC;
    --bg: #F8F7F5;
    --success: #27AE60;
    --danger: #E74C3C;
    --warning: #E67E22;
    --info: #3498DB;
    --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Jost', 'Inter', sans-serif;
    background: var(--bg);
    color: var(--ink);
}

/* ═══════════════════════════════════════════
   HERO SECTION — SOMBRE PREMIUM
   ═══════════════════════════════════════════ */
.hero-suivi {
    position: relative;
    background: linear-gradient(135deg, #0A0A0A 0%, #1A1510 50%, #0D0D0D 100%);
    padding: 90px 0 80px;
    text-align: center;
    overflow: hidden;
    isolation: isolate;
}

/* Halo doré subtil */
.hero-suivi::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 700px;
    height: 700px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 70%);
    z-index: 0;
    pointer-events: none;
}

/* Filet doré en bas */
.hero-suivi::after {
    content: '';
    position: absolute;
    left: 50%; transform: translateX(-50%);
    bottom: 0;
    width: min(240px, 60%);
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    z-index: 2;
}

.hero-suivi .hero-content {
    position: relative;
    z-index: 2;
    max-width: 700px;
    margin: 0 auto;
    padding: 0 24px;
}

.hero-suivi .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(200,146,42,0.08);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: var(--gold-light);
    padding: 8px 22px;
    border-radius: 50px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 3px;
    border: 1px solid rgba(200,146,42,0.2);
    margin-bottom: 24px;
}
.hero-suivi .hero-badge i {
    color: var(--gold);
    font-size: 0.75rem;
}

.hero-suivi h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2rem, 4.5vw, 3rem);
    font-weight: 600;
    color: #FFFFFF;
    line-height: 1.1;
    letter-spacing: -0.5px;
    margin-bottom: 16px;
}

.hero-suivi h1 span {
    color: var(--gold);
    font-style: italic;
    font-weight: 700;
}

.hero-suivi > .hero-content > p {
    color: rgba(255,255,255,0.55);
    font-size: 0.95rem;
    line-height: 1.7;
    font-weight: 300;
    max-width: 480px;
    margin: 0 auto;
}

/* ═══════════════════════════════════════════
   CONTAINER
   ═══════════════════════════════════════════ */
.container-custom {
    max-width: 860px;
    margin: 0 auto;
    padding: 0 24px 80px;
}

/* ═══════════════════════════════════════════
   CARTE DE RECHERCHE
   ═══════════════════════════════════════════ */
.search-card {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 44px 40px;
    margin-top: -60px;
    position: relative;
    z-index: 3;
    box-shadow: 0 24px 60px rgba(20,15,5,0.08);
    border: 1px solid rgba(200,146,42,0.08);
}

.search-card .search-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.45rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 6px;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.search-card .search-title i {
    color: var(--gold);
    font-size: 1.15rem;
}

.search-card .search-subtitle {
    color: var(--muted);
    font-size: 0.9rem;
    margin-bottom: 30px;
    line-height: 1.6;
}

.search-card .form-group {
    margin-bottom: 20px;
}

.search-card .form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.78rem;
    color: var(--ink);
    margin-bottom: 8px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.search-card .form-group label i {
    color: var(--gold);
    margin-right: 6px;
    font-size: 0.85rem;
}

.search-card .input-group-icon {
    position: relative;
}

.search-card .input-group-icon > i {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gold);
    font-size: 1.05rem;
    pointer-events: none;
    opacity: 0.7;
}

.search-card .form-control {
    width: 100%;
    padding: 15px 18px 15px 48px;
    border: 1.5px solid var(--line);
    border-radius: 14px;
    font-family: 'Jost', 'Inter', sans-serif;
    font-size: 0.95rem;
    transition: all 0.3s var(--ease);
    background: #FAFAF8;
    color: var(--ink);
    letter-spacing: 0.2px;
}

.search-card .form-control:focus {
    outline: none;
    border-color: var(--gold);
    background: #FFFFFF;
    box-shadow: 0 0 0 4px rgba(200,146,42,0.08);
}

.search-card .form-control::placeholder {
    color: #B0B0B0;
    font-weight: 400;
}

.btn-rechercher {
    width: 100%;
    padding: 17px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #0A0804;
    border: none;
    border-radius: 14px;
    font-family: 'Jost', 'Inter', sans-serif;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s var(--ease);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 14px;
    box-shadow: 0 8px 22px rgba(200,146,42,0.25);
}

.btn-rechercher:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 34px rgba(200,146,42,0.35);
}

.btn-rechercher i {
    font-size: 1.05rem;
    transition: transform 0.3s;
}
.btn-rechercher:hover i {
    transform: scale(1.15);
}

/* ═══════════════════════════════════════════
   ALERTES
   ═══════════════════════════════════════════ */
.alert-custom {
    border-radius: 14px;
    padding: 16px 20px;
    border: none;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    font-size: 0.9rem;
    font-weight: 500;
    animation: fadeIn 0.4s var(--ease);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-custom i { font-size: 1.3rem; flex-shrink: 0; }

.alert-custom.error {
    background: rgba(231,76,60,0.06);
    color: #A93226;
    border-left: 4px solid var(--danger);
}

/* ═══════════════════════════════════════════
   CARTE RÉSULTAT
   ═══════════════════════════════════════════ */
.result-card {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 44px 40px;
    margin-top: 32px;
    box-shadow: 0 24px 60px rgba(20,15,5,0.06);
    border: 1px solid rgba(200,146,42,0.08);
    animation: slideUp 0.6s var(--ease);
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 8px;
}

.result-header .order-number {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--ink);
    letter-spacing: -0.3px;
}
.result-header .order-number span {
    color: var(--gold);
    font-weight: 700;
}

.result-header .order-date {
    color: var(--muted);
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--line-soft);
    padding: 6px 14px;
    border-radius: 30px;
    font-weight: 500;
}
.result-header .order-date i {
    color: var(--gold);
    font-size: 0.85rem;
}

.result-sub {
    color: var(--muted);
    font-size: 0.85rem;
    margin-bottom: 32px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.result-sub i {
    color: var(--gold);
    font-size: 0.85rem;
}

/* ═══════════════════════════════════════════
   BARRE DE PROGRESSION
   ═══════════════════════════════════════════ */
.progress-wrapper {
    margin-bottom: 34px;
    padding: 20px 22px;
    background: var(--line-soft);
    border-radius: 16px;
}

.progress-custom {
    height: 8px;
    border-radius: 10px;
    background: #E8E3DA;
    overflow: hidden;
    margin-bottom: 12px;
    position: relative;
}

.progress-custom .progress-bar {
    height: 100%;
    border-radius: 10px;
    transition: width 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    position: relative;
    box-shadow: 0 2px 8px rgba(200,146,42,0.3);
}

/* Effet brillant qui traverse la barre */
.progress-custom .progress-bar::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    background-size: 200% 100%;
    animation: shimmerBar 2.5s linear infinite;
}

@keyframes shimmerBar {
    0% { background-position: -100% 0; }
    100% { background-position: 200% 0; }
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.78rem;
    color: var(--muted);
    font-weight: 600;
    letter-spacing: 0.3px;
}
.progress-label span:first-child {
    color: var(--ink);
}

/* ═══════════════════════════════════════════
   TIMELINE
   ═══════════════════════════════════════════ */
.timeline {
    position: relative;
    padding-left: 34px;
    margin-bottom: 8px;
}

.timeline-item {
    position: relative;
    padding-bottom: 32px;
    padding-left: 22px;
    border-left: 2px solid var(--line-soft);
}

.timeline-item:last-child {
    border-left: 2px solid transparent;
    padding-bottom: 8px;
}

.timeline-item .timeline-dot {
    position: absolute;
    left: -10px;
    top: 4px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #E8E3DA;
    border: 3px solid #FFFFFF;
    transition: all 0.4s var(--ease);
    z-index: 2;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

.timeline-item .timeline-dot.active {
    background: var(--gold);
    border-color: var(--gold);
    box-shadow: 0 0 0 5px rgba(200,146,42,0.18);
    animation: pulseDot 2s infinite;
}

@keyframes pulseDot {
    0%, 100% { box-shadow: 0 0 0 5px rgba(200,146,42,0.18); }
    50%      { box-shadow: 0 0 0 8px rgba(200,146,42,0.08); }
}

.timeline-item .timeline-dot.completed {
    background: var(--success);
    border-color: var(--success);
    box-shadow: 0 0 0 5px rgba(39,174,96,0.15);
}

.timeline-item .timeline-dot.annulee {
    background: var(--danger);
    border-color: var(--danger);
    box-shadow: 0 0 0 5px rgba(231,76,60,0.15);
}

.timeline-item .timeline-content h5 {
    font-family: 'Jost', 'Inter', sans-serif;
    font-size: 0.98rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 4px;
    letter-spacing: -0.1px;
}

.timeline-item .timeline-content p {
    font-size: 0.82rem;
    color: var(--muted);
    margin: 0 0 6px;
    line-height: 1.5;
}

.timeline-item .timeline-content .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.status-badge.warning { background: rgba(230,126,34,0.1); color: #B35C00; }
.status-badge.info    { background: rgba(52,152,219,0.1); color: #1F5E89; }
.status-badge.primary { background: rgba(200,146,42,0.1); color: var(--gold-deep); }
.status-badge.success { background: rgba(39,174,96,0.1);  color: #1A7A40; }
.status-badge.danger  { background: rgba(231,76,60,0.1);  color: #A93226; }

/* ═══════════════════════════════════════════
   ALERTE FINALE
   ═══════════════════════════════════════════ */
.alert-final {
    border-radius: 16px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 24px;
    animation: fadeIn 0.5s var(--ease);
}

.alert-final i {
    font-size: 1.6rem;
    flex-shrink: 0;
}

.alert-final div {
    font-size: 0.9rem;
    line-height: 1.5;
}
.alert-final strong {
    display: block;
    margin-bottom: 2px;
    font-size: 0.95rem;
}

.alert-final.success {
    background: rgba(39,174,96,0.06);
    color: #1A7A40;
    border: 1px solid rgba(39,174,96,0.15);
}
.alert-final.success i {
    color: var(--success);
}

.alert-final.danger {
    background: rgba(231,76,60,0.06);
    color: #A93226;
    border: 1px solid rgba(231,76,60,0.15);
}
.alert-final.danger i {
    color: var(--danger);
}

/* ═══════════════════════════════════════════
   SÉPARATEUR
   ═══════════════════════════════════════════ */
.divider-soft {
    border: none;
    border-top: 1.5px dashed var(--line);
    margin: 28px 0;
}

/* ═══════════════════════════════════════════
   RÉCAPITULATIF
   ═══════════════════════════════════════════ */
.recap-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--ink);
    letter-spacing: -0.2px;
}
.recap-title i {
    color: var(--gold);
    font-size: 1rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin: 0 0 24px;
}

.info-item {
    background: var(--line-soft);
    padding: 16px 18px;
    border-radius: 14px;
    transition: all 0.3s var(--ease);
    border: 1px solid transparent;
}

.info-item:hover {
    background: #FFFFFF;
    border-color: rgba(200,146,42,0.15);
    box-shadow: 0 6px 16px rgba(200,146,42,0.06);
}

.info-item .label {
    font-size: 0.66rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    margin-bottom: 6px;
}

.info-item .label i {
    color: var(--gold);
    font-size: 0.8rem;
}

.info-item .value {
    font-weight: 600;
    color: var(--ink);
    font-size: 0.92rem;
    line-height: 1.4;
    word-break: break-word;
}

/* ═══════════════════════════════════════════
   TOTAL
   ═══════════════════════════════════════════ */
.total-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 22px 24px;
    margin-top: 8px;
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 100%);
    border-radius: 16px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 30px rgba(13,13,13,0.15);
}

.total-section::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity: 0.5;
}

.total-section .total-label {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.total-section .total-label i {
    color: var(--gold);
    font-size: 0.9rem;
}

.total-section .total-amount {
    font-family: 'Playfair Display', serif;
    font-size: 1.7rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: -0.5px;
}

/* ═══════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════ */
@media (max-width: 768px) {
    .hero-suivi { padding: 70px 0 60px; }
    .hero-suivi h1 { font-size: 2rem; }
    .hero-suivi > .hero-content > p { font-size: 0.88rem; }
    
    .search-card { padding: 32px 26px; margin-top: -50px; }
    .search-card .search-title { font-size: 1.2rem; }
    
    .result-card { padding: 32px 26px; }
    .result-header .order-number { font-size: 1.2rem; }
    
    .info-grid { grid-template-columns: 1fr; gap: 12px; }
    
    .total-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 20px;
    }
    .total-section .total-amount { font-size: 1.5rem; }
    
    .timeline { padding-left: 24px; }
    .timeline-item { padding-left: 18px; padding-bottom: 26px; }
}

@media (max-width: 500px) {
    .container-custom { padding: 0 16px 60px; }
    .hero-suivi { padding: 56px 0 50px; }
    .hero-suivi h1 { font-size: 1.7rem; }
    .hero-suivi .hero-badge { font-size: 0.58rem; padding: 6px 16px; letter-spacing: 2px; }
    
    .search-card { padding: 26px 20px; margin-top: -44px; border-radius: 20px; }
    .search-card .search-title { font-size: 1.05rem; }
    .search-card .search-subtitle { font-size: 0.82rem; margin-bottom: 24px; }
    .search-card .form-control { padding: 13px 16px 13px 44px; font-size: 0.88rem; }
    .search-card .form-group label { font-size: 0.72rem; }
    .btn-rechercher { font-size: 0.85rem; padding: 15px; }
    
    .result-card { padding: 26px 20px; border-radius: 20px; }
    .result-header { flex-direction: column; align-items: flex-start; gap: 10px; }
    .result-header .order-number { font-size: 1.1rem; }
    .result-header .order-date { font-size: 0.72rem; padding: 5px 12px; }
    
    .progress-wrapper { padding: 16px 18px; }
    .progress-label { font-size: 0.7rem; }
    
    .recap-title { font-size: 0.98rem; }
    .info-item { padding: 14px 16px; }
    .info-item .value { font-size: 0.85rem; }
    
    .total-section .total-amount { font-size: 1.35rem; }
}
</style>

<!-- ═══════════════════════════════════════════
     HERO
     ═══════════════════════════════════════════ -->
<section class="hero-suivi">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="bi bi-truck"></i>
            Suivi de commande
        </div>
        <h1>Où est <span>ma commande</span> ?</h1>
        <p>Entrez le numéro de votre commande et votre téléphone pour suivre son évolution en temps réel.</p>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     CONTENU PRINCIPAL
     ═══════════════════════════════════════════ -->
<div class="container-custom">

    <!-- Carte de recherche -->
    <div class="search-card">
        <div class="search-title">
            <i class="bi bi-search"></i> Suivez votre commande
        </div>
        <p class="search-subtitle">Remplissez les champs ci-dessous pour connaître l'état de votre commande.</p>

        <form method="POST">
            <div class="form-group">
                <label><i class="bi bi-receipt"></i> Numéro de commande</label>
                <div class="input-group-icon">
                    <i class="bi bi-file-earmark-text"></i>
                    <input type="text" name="numero_commande" class="form-control" placeholder="Ex: AWA-20260619-3419" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-phone"></i> Téléphone</label>
                <div class="input-group-icon">
                    <i class="bi bi-telephone"></i>
                    <input type="tel" name="telephone" class="form-control" placeholder="77 00 00 00" required>
                </div>
            </div>

            <button type="submit" name="rechercher" class="btn-rechercher">
                <i class="bi bi-search"></i> Suivre ma commande
            </button>
        </form>

        <?php if($error): ?>
            <div class="alert-custom error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Résultat -->
    <?php if($commande): 
        $statut = $commande['statut'];
        $info = $statuts[$statut] ?? $statuts['en_attente'];
        $etapes = ['en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree'];
        $statut_index = array_search($statut, $etapes);
        $pourcentage = $info['pourcentage'];
        $telephone_affichage = $commande['telephone'] ?? 'Non renseigné';
    ?>
    <div class="result-card">
        <!-- En-tête -->
        <div class="result-header">
            <div class="order-number">
                Commande <span>#<?= htmlspecialchars($commande['numero_commande']) ?></span>
            </div>
            <div class="order-date">
                <i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($commande['created_at'])) ?>
            </div>
        </div>
        <div class="result-sub">
            <i class="bi bi-clock"></i> Passée le <?= date('d/m/Y à H:i', strtotime($commande['created_at'])) ?>
        </div>

        <!-- Barre de progression -->
        <div class="progress-wrapper">
            <div class="progress-custom">
                <div class="progress-bar" style="width: <?= $pourcentage ?>%;"></div>
            </div>
            <div class="progress-label">
                <span><?= $info['label'] ?></span>
                <span><?= $pourcentage ?>%</span>
            </div>
        </div>

        <!-- Timeline -->
        <div class="timeline">
            <?php foreach($etapes as $index => $etape):
                $infoEtape = $statuts[$etape];
                $isActive = ($statut == $etape);
                $isCompleted = ($statut_index > $index);
                $isAnnulee = ($statut == 'annulee');
                
                $dotClass = '';
                if($isAnnulee && $index == 0) $dotClass = 'annulee';
                elseif($isCompleted) $dotClass = 'completed';
                elseif($isActive) $dotClass = 'active';
            ?>
            <div class="timeline-item">
                <div class="timeline-dot <?= $dotClass ?>"></div>
                <div class="timeline-content">
                    <h5><?= $infoEtape['label'] ?></h5>
                    <?php if($isAnnulee && $index == 0): ?>
                        <p>Commande annulée</p>
                        <span class="status-badge danger">
                            <i class="bi bi-x-circle"></i> Annulée
                        </span>
                    <?php elseif($isActive): ?>
                        <p>En cours de traitement...</p>
                        <span class="status-badge warning">
                            <i class="bi bi-hourglass-split"></i> En cours
                        </span>
                    <?php elseif($isCompleted): ?>
                        <p>Terminé</p>
                        <span class="status-badge success">
                            <i class="bi bi-check-lg"></i> Validé
                        </span>
                    <?php else: ?>
                        <p>En attente</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Alerte finale -->
        <?php if($statut == 'livree'): ?>
            <div class="alert-final success">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <strong>Commande livrée !</strong>
                    <span>Votre commande a été livrée avec succès. Merci pour votre confiance !</span>
                </div>
            </div>
        <?php elseif($statut == 'annulee'): ?>
            <div class="alert-final danger">
                <i class="bi bi-x-circle-fill"></i>
                <div>
                    <strong>Commande annulée</strong>
                    <span>Cette commande a été annulée. Contactez-nous pour plus d'informations.</span>
                </div>
            </div>
        <?php endif; ?>

        <hr class="divider-soft">

        <!-- Informations -->
        <div class="recap-title">
            <i class="bi bi-info-circle"></i> Récapitulatif de la commande
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="label"><i class="bi bi-geo-alt"></i> Adresse de livraison</div>
                <div class="value"><?= nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')) ?></div>
            </div>
            <div class="info-item">
                <div class="label"><i class="bi bi-credit-card"></i> Mode de paiement</div>
                <div class="value">
                    <?php 
                    $paiements = [
                        'livraison' => 'Paiement à la livraison',
                        'orange_money' => 'Orange Money',
                        'wave' => 'Wave',
                        'moov_money' => 'Moov Money',
                        'carte' => 'Carte bancaire',
                        'especes' => 'Espèces'
                    ];
                    $mode = $commande['mode_paiement'] ?? 'livraison';
                    echo htmlspecialchars($paiements[$mode] ?? $mode);
                    ?>
                </div>
            </div>
            <div class="info-item">
                <div class="label"><i class="bi bi-person"></i> Client</div>
                <div class="value"><?= htmlspecialchars($commande['nom_client']) ?></div>
            </div>
            <div class="info-item">
                <div class="label"><i class="bi bi-phone"></i> Téléphone</div>
                <div class="value"><?= htmlspecialchars($telephone_affichage) ?></div>
            </div>
        </div>

        <!-- Total -->
        <div class="total-section">
            <span class="total-label">
                <i class="bi bi-cash-stack"></i> Montant total
            </span>
            <span class="total-amount"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>