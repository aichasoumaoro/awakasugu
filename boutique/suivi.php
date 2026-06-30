<?php
// ============================================
// SUIVI DE COMMANDE - Awa Ka Sugu
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
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

// Recherche de commande
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

// Statuts
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
/* ============================================
   DESIGN MODERNE SUIVI COMMANDE
   ============================================ */

@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Jost:wght@300;400;500;600;700;800&display=swap');

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Jost', sans-serif;
    background: #F8F7F5;
    color: #1A1A1A;
}

/* ===== HERO SECTION ===== */
.hero-suivi {
    background: linear-gradient(165deg, #0A0A0A 0%, #1A1A1A 40%, #2A1A0A 100%);
    padding: 80px 0 60px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hero-suivi::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(ellipse at 30% 50%, rgba(200,146,42,0.06) 0%, transparent 60%);
    animation: glowPulse 6s ease-in-out infinite;
}

@keyframes glowPulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 1; }
}

.hero-suivi::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #C8922A, #F5D98E, #C8922A, transparent);
    background-size: 200% 100%;
    animation: shimmerLine 4s linear infinite;
}

@keyframes shimmerLine {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.hero-suivi .hero-content {
    position: relative;
    z-index: 2;
    max-width: 700px;
    margin: 0 auto;
    padding: 0 20px;
}

.hero-suivi .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(200,146,42,0.12);
    backdrop-filter: blur(10px);
    color: #C8922A;
    padding: 6px 20px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    border: 1px solid rgba(200,146,42,0.2);
    margin-bottom: 20px;
}

.hero-suivi h1 {
    font-family: 'Playfair Display', serif;
    font-size: 3.2rem;
    font-weight: 700;
    color: #FFFFFF;
    line-height: 1.1;
    margin-bottom: 15px;
}

.hero-suivi h1 span {
    color: #C8922A;
}

.hero-suivi p {
    color: rgba(255,255,255,0.5);
    font-size: 1.05rem;
    line-height: 1.7;
}

/* ===== CONTAINER ===== */
.container-custom {
    max-width: 820px;
    margin: 0 auto;
    padding: 0 20px 60px;
}

/* ===== CARTE DE RECHERCHE ===== */
.search-card {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 40px;
    margin-top: -40px;
    position: relative;
    z-index: 3;
    box-shadow: 0 20px 60px rgba(0,0,0,0.08);
    border: 1px solid rgba(200,146,42,0.08);
}

.search-card .search-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.4rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 8px;
}

.search-card .search-subtitle {
    color: #8A99AA;
    font-size: 0.9rem;
    margin-bottom: 25px;
}

.search-card .form-group {
    margin-bottom: 18px;
}

.search-card .form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: #0D0D0D;
    margin-bottom: 6px;
}

.search-card .form-group label i {
    color: #C8922A;
    margin-right: 6px;
}

.search-card .form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #E8E5E0;
    border-radius: 14px;
    font-family: 'Jost', sans-serif;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #FAFAF8;
}

.search-card .form-control:focus {
    outline: none;
    border-color: #C8922A;
    background: #FFFFFF;
    box-shadow: 0 0 0 4px rgba(200,146,42,0.08);
}

.search-card .form-control::placeholder {
    color: #B0B0B0;
}

.search-card .input-group-icon {
    position: relative;
}

.search-card .input-group-icon i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #B0B0B0;
    font-size: 1.1rem;
}

.search-card .input-group-icon .form-control {
    padding-left: 46px;
}

.btn-rechercher {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: #1A1A1A;
    border: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 8px;
}

.btn-rechercher:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(200,146,42,0.3);
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    color: #FFFFFF;
}

.btn-rechercher i {
    font-size: 1.2rem;
}

/* ===== ALERTES ===== */
.alert-custom {
    border-radius: 16px;
    padding: 18px 24px;
    border: none;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 25px;
}

.alert-custom i {
    font-size: 1.5rem;
}

.alert-custom.error {
    background: #FEF3F2;
    color: #721C24;
    border-left: 4px solid #E74C3C;
}

.alert-custom.success {
    background: #D4EDDA;
    color: #155724;
    border-left: 4px solid #27AE60;
}

.alert-custom.warning {
    background: #FFF3CD;
    color: #856404;
    border-left: 4px solid #E67E22;
}

/* ===== CARTE RÉSULTAT ===== */
.result-card {
    background: #FFFFFF;
    border-radius: 24px;
    padding: 40px;
    margin-top: 30px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.06);
    border: 1px solid rgba(200,146,42,0.08);
    animation: slideUp 0.5s ease-out;
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
    gap: 15px;
    margin-bottom: 6px;
}

.result-header .order-number {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #0D0D0D;
}

.result-header .order-number span {
    color: #C8922A;
}

.result-header .order-date {
    color: #8A99AA;
    font-size: 0.85rem;
}

.result-header .order-date i {
    margin-right: 6px;
}

.result-sub {
    color: #8A99AA;
    font-size: 0.9rem;
    margin-bottom: 25px;
}

/* ===== PROGRESS BAR ===== */
.progress-wrapper {
    margin-bottom: 30px;
}

.progress-custom {
    height: 6px;
    border-radius: 10px;
    background: #F0EDE8;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-custom .progress-bar {
    height: 100%;
    border-radius: 10px;
    transition: width 1s ease;
    background: linear-gradient(90deg, #C8922A, #E8B55A);
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #8A99AA;
}

/* ===== TIMELINE ===== */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding-bottom: 28px;
    padding-left: 20px;
    border-left: 2px solid #F0EDE8;
}

.timeline-item:last-child {
    border-left: 2px solid transparent;
}

.timeline-item .timeline-dot {
    position: absolute;
    left: -8px;
    top: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #E8E5E0;
    border: 2px solid #F0EDE8;
    transition: all 0.4s ease;
    z-index: 2;
}

.timeline-item .timeline-dot.active {
    background: #C8922A;
    border-color: #C8922A;
    box-shadow: 0 0 0 4px rgba(200,146,42,0.2);
}

.timeline-item .timeline-dot.completed {
    background: #27AE60;
    border-color: #27AE60;
    box-shadow: 0 0 0 4px rgba(39,174,96,0.2);
}

.timeline-item .timeline-dot.annulee {
    background: #E74C3C;
    border-color: #E74C3C;
    box-shadow: 0 0 0 4px rgba(231,76,60,0.2);
}

.timeline-item .timeline-content h5 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 2px;
}

.timeline-item .timeline-content p {
    font-size: 0.8rem;
    color: #8A99AA;
    margin: 0;
}

.timeline-item .timeline-content .status-badge {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    margin-top: 4px;
}

.status-badge.warning { background: #FFF3CD; color: #856404; }
.status-badge.info { background: #D1ECF1; color: #0C5460; }
.status-badge.primary { background: #CCE5FF; color: #004085; }
.status-badge.success { background: #D4EDDA; color: #155724; }
.status-badge.danger { background: #F8D7DA; color: #721C24; }

/* ===== ALERTE FINALE ===== */
.alert-final {
    border-radius: 16px;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 20px;
}

.alert-final i { font-size: 1.5rem; }

.alert-final.success {
    background: #D4EDDA;
    color: #155724;
    border: 1px solid rgba(39,174,96,0.2);
}

.alert-final.danger {
    background: #FEF3F2;
    color: #721C24;
    border: 1px solid rgba(231,76,60,0.2);
}

/* ===== INFO GRID ===== */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 20px 0;
}

.info-item {
    background: #F8F7F5;
    padding: 14px 18px;
    border-radius: 14px;
}

.info-item .label {
    font-size: 0.65rem;
    color: #8A99AA;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.info-item .label i {
    color: #C8922A;
}

.info-item .value {
    font-weight: 600;
    color: #0D0D0D;
    margin-top: 3px;
    font-size: 0.95rem;
}

/* ===== TOTAL ===== */
.total-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 18px;
    margin-top: 18px;
    border-top: 2px solid #F0EDE8;
}

.total-section .total-label {
    font-size: 0.9rem;
    color: #8A99AA;
}

.total-section .total-amount {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: #C8922A;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .hero-suivi { padding: 60px 0 40px; }
    .hero-suivi h1 { font-size: 2.2rem; }
    .search-card { padding: 25px; margin-top: -30px; }
    .result-card { padding: 25px; }
    .info-grid { grid-template-columns: 1fr; }
    .total-section { flex-direction: column; align-items: flex-start; gap: 8px; }
    .result-header .order-number { font-size: 1.2rem; }
    .timeline { padding-left: 20px; }
    .timeline-item { padding-left: 15px; }
}

@media (max-width: 500px) {
    .hero-suivi h1 { font-size: 1.8rem; }
    .search-card { padding: 20px; }
    .result-card { padding: 20px; }
    .btn-rechercher { font-size: 0.9rem; padding: 14px; }
}
</style>

<!-- ===== HERO SECTION ===== -->
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

<!-- ===== CONTENU PRINCIPAL ===== -->
<div class="container-custom">

    <!-- Carte de recherche -->
    <div class="search-card">
        <div class="search-title">🔍 Suivez votre commande</div>
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
                    <?php if($isAnnulee): ?>
                        <p>❌ Commande annulée</p>
                    <?php elseif($isActive): ?>
                        <p>⏳ En cours de traitement...</p>
                        <span class="status-badge warning">En cours</span>
                    <?php elseif($isCompleted): ?>
                        <p>✅ Terminé</p>
                        <span class="status-badge success">Validé</span>
                    <?php else: ?>
                        <p>⏱ En attente</p>
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
                    <strong>Livrée !</strong><br>
                    <span>Votre commande a été livrée avec succès. Merci pour votre confiance !</span>
                </div>
            </div>
        <?php elseif($statut == 'annulee'): ?>
            <div class="alert-final danger">
                <i class="bi bi-x-circle-fill"></i>
                <div>
                    <strong>Commande annulée</strong><br>
                    <span>Cette commande a été annulée. Contactez-nous pour plus d'informations.</span>
                </div>
            </div>
        <?php endif; ?>

        <hr style="border-color: #F0EDE8; margin: 20px 0;">

        <!-- Informations -->
        <h5 style="font-size:1rem; font-weight:600; margin-bottom:15px; display:flex; align-items:center; gap:10px;">
            <i class="bi bi-info-circle" style="color:#C8922A;"></i> Récapitulatif
        </h5>

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
                        'livraison' => '💵 Paiement à la livraison',
                        'orange_money' => '🟠 Orange Money',
                        'wave' => '🌊 Wave',
                        'moov_money' => '📱 Moov Money',
                        'carte' => '💳 Carte bancaire',
                        'especes' => '💰 Espèces'
                    ];
                    $mode = $commande['mode_paiement'] ?? 'livraison';
                    echo $paiements[$mode] ?? $mode;
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
            <span class="total-label">💰 Montant total</span>
            <span class="total-amount"><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>