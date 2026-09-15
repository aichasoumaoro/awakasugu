<?php
// ============================================
// MES POINTS FIDÉLITÉ - Awa Ka Sugu
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

require_once '../includes/maintenance_check.php';

// Vérifier si le client est connecté
if (!isset($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}

$titre_page = 'Mes points fidélité';
$meta_desc  = 'Consultez vos points fidélité Awa Ka Sugu.';
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
    die("Erreur : " . $e->getMessage());
}

$client_id = $_SESSION['client_id'];

// ============================================
// RÉCUPÉRER LES POINTS DU CLIENT
// ============================================
$stmt = $pdo->prepare("SELECT * FROM points_fidelite WHERE client_id = ?");
$stmt->execute([$client_id]);
$fidelite = $stmt->fetch();

if (!$fidelite) {
    $stmt = $pdo->prepare("INSERT INTO points_fidelite (client_id, points, points_utilises, total_points) VALUES (?, 0, 0, 0)");
    $stmt->execute([$client_id]);
    $fidelite = ['points' => 0, 'points_utilises' => 0, 'total_points' => 0];
}

// ============================================
// PARAMÈTRES DE FIDÉLITÉ
// ============================================
$params_fidelite = [];
try {
    $stmt = $pdo->query("SELECT cle, valeur FROM parametres_fonctionnalites WHERE cle LIKE 'fidelite_%'");
    $params_fidelite = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(PDOException $e) {
    $params_fidelite = [];
}

$seuil_points = $params_fidelite['fidelite_seuil_points'] ?? 50000;
$points_par_seuil = $params_fidelite['fidelite_points_par_seuil'] ?? 1;
$reduction_points = $params_fidelite['fidelite_reduction_points'] ?? 10;
$reduction_montant = $params_fidelite['fidelite_reduction_montant'] ?? 1000;
$fidelite_actif = $params_fidelite['fidelite_actif'] ?? 1;

// ============================================
// STATS COMMANDES
// ============================================
$commandes_terminees = 0;
$points_potentiels = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb 
        FROM commandes 
        WHERE client_id = ? AND statut IN ('livree', 'terminee', 'confirmee')
    ");
    $stmt->execute([$client_id]);
    $result = $stmt->fetch();
    $commandes_terminees += $result['nb'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM commandes 
        WHERE client_id = ? AND statut = 'en_attente'
    ");
    $stmt->execute([$client_id]);
    $result_attente = $stmt->fetch();
    $points_potentiels = floor(($result_attente['total'] ?? 0) / $seuil_points * $points_par_seuil);
} catch(PDOException $e) {
    // Ignorer
}

$points = (int)($fidelite['points'] ?? 0);
$total_gagnes = (int)($fidelite['total_points'] ?? 0);

// Calcul de la réduction possible
$nb_lots = floor($points / $reduction_points);
$reduction_possible = $nb_lots * $reduction_montant;

// Prochain palier
$points_restants = $reduction_points - ($points % $reduction_points);
if ($points_restants == $reduction_points && $points > 0) $points_restants = 0;

// Pourcentage pour la barre de progression
$pourcentage_progression = min((($points % $reduction_points) / $reduction_points) * 100, 100);
?>

<style>
/* ═══════════════════════════════════════════
   MES POINTS FIDÉLITÉ — DESIGN PREMIUM
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
    --ease: cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Jost', 'Inter', sans-serif;
    background: var(--bg);
    color: var(--ink);
}

.points-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 24px 80px;
}

/* ═══════════════════════════════════════════
   HEADER
   ═══════════════════════════════════════════ */
.points-header {
    position: relative;
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1510 60%, #0D0D0D 100%);
    border-radius: 24px;
    padding: 40px 36px;
    margin-bottom: 28px;
    overflow: hidden;
    isolation: isolate;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 24px;
}

.points-header::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(200,146,42,0.15) 0%, transparent 70%);
    z-index: 0;
    pointer-events: none;
}

.points-header > * { position: relative; z-index: 2; }

.points-header .left h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.6rem, 3vw, 2.2rem);
    font-weight: 600;
    color: #FFFFFF;
    margin-bottom: 8px;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.points-header .left h1 i {
    color: var(--gold);
    font-size: 1.6rem;
}
.points-header .left h1 span {
    color: var(--gold);
    font-style: italic;
    font-weight: 700;
}

.points-header .left p {
    color: rgba(255,255,255,0.55);
    font-size: 0.88rem;
    font-weight: 300;
    line-height: 1.5;
}

.points-header .points-big {
    text-align: center;
    padding: 20px 32px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(200,146,42,0.2);
    border-radius: 18px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.points-header .points-big .number {
    font-family: 'Playfair Display', serif;
    font-size: 3rem;
    font-weight: 700;
    color: var(--gold);
    line-height: 1;
    letter-spacing: -1px;
}

.points-header .points-big .label {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.45);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-top: 8px;
    font-weight: 500;
}

.points-header .points-big .reduction {
    margin-top: 10px;
    font-size: 0.78rem;
    color: var(--gold-light);
    padding-top: 10px;
    border-top: 1px solid rgba(200,146,42,0.15);
    font-weight: 500;
}

/* ═══════════════════════════════════════════
   SIDEBAR
   ═══════════════════════════════════════════ */
.compte-sidebar {
    background: #fff;
    border-radius: 18px;
    border: 1px solid var(--line-soft);
    padding: 10px;
    margin-bottom: 28px;
    display: flex;
    overflow-x: auto;
    gap: 6px;
    scrollbar-width: none;
    box-shadow: 0 4px 16px rgba(13,13,13,0.03);
}
.compte-sidebar::-webkit-scrollbar { display: none; }

.compte-sidebar .menu-item {
    flex-shrink: 0;
    border-radius: 12px;
    transition: all 0.25s var(--ease);
}

.compte-sidebar .menu-item a {
    color: var(--ink);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    font-size: 0.82rem;
    font-weight: 500;
    border-radius: 12px;
    white-space: nowrap;
    transition: all 0.25s var(--ease);
}

.compte-sidebar .menu-item i {
    color: var(--gold);
    font-size: 1rem;
    transition: all 0.25s;
}

.compte-sidebar .menu-item:hover a {
    background: var(--line-soft);
}

.compte-sidebar .menu-item.active a {
    background: var(--ink);
    color: #fff;
}
.compte-sidebar .menu-item.active i {
    color: var(--gold);
}

.compte-sidebar .menu-item.logout a {
    color: var(--danger);
}
.compte-sidebar .menu-item.logout i {
    color: var(--danger);
}
.compte-sidebar .menu-item.logout:hover a {
    background: rgba(231,76,60,0.06);
}

/* ═══════════════════════════════════════════
   GRILLE STATS
   ═══════════════════════════════════════════ */
.points-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}

.points-card {
    background: #fff;
    border-radius: 18px;
    padding: 26px 24px;
    border: 1px solid var(--line-soft);
    transition: all 0.3s var(--ease);
    box-shadow: 0 4px 16px rgba(13,13,13,0.02);
    position: relative;
    overflow: hidden;
}

.points-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold));
    opacity: 0;
    transition: opacity 0.3s var(--ease);
}

.points-card:hover {
    transform: translateY(-3px);
    border-color: rgba(200,146,42,0.25);
    box-shadow: 0 16px 34px rgba(200,146,42,0.08);
}
.points-card:hover::before { opacity: 1; }

.points-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.2px;
}
.points-card h3 i {
    color: var(--gold);
    font-size: 1rem;
}

.points-card .value {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--gold);
    line-height: 1;
    letter-spacing: -1px;
    margin-bottom: 6px;
}
.points-card .value.green {
    color: var(--success);
}

.points-card .sub {
    color: var(--muted);
    font-size: 0.78rem;
    line-height: 1.4;
    margin-bottom: 14px;
}

.points-card .info-text {
    margin-top: 14px;
    padding: 12px 14px;
    background: #FEFBF5;
    border-radius: 10px;
    border: 1px solid rgba(200,146,42,0.1);
}

.points-card .info-text p {
    margin: 6px 0;
    font-size: 0.8rem;
    color: #5A6B7A;
    line-height: 1.5;
    display: flex;
    align-items: center;
    gap: 8px;
}

.points-card .info-text .highlight {
    color: var(--gold);
    font-weight: 700;
}

/* Barre de progression */
.points-card .progress {
    height: 8px;
    background: var(--line-soft);
    border-radius: 10px;
    margin-top: 14px;
    overflow: hidden;
    position: relative;
}

.points-card .progress-bar {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    transition: width 1s var(--ease);
    position: relative;
}

.points-card .progress-bar::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    background-size: 200% 100%;
    animation: shimmerBar 2.5s linear infinite;
}

@keyframes shimmerBar {
    0% { background-position: -100% 0; }
    100% { background-position: 200% 0; }
}

/* ═══════════════════════════════════════════
   RÈGLES
   ═══════════════════════════════════════════ */
.regles-box {
    background: linear-gradient(135deg, #FEFBF5 0%, #FDF9F2 100%);
    border-radius: 18px;
    padding: 28px 30px;
    border: 1px solid rgba(200,146,42,0.12);
    position: relative;
    overflow: hidden;
}

.regles-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold));
}

.regles-box h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.2px;
}
.regles-box h3 i {
    color: var(--gold);
    font-size: 1rem;
}

.regles-box ul {
    list-style: none;
    padding: 0;
}

.regles-box ul li {
    padding: 8px 0;
    font-size: 0.85rem;
    color: #5A6B7A;
    line-height: 1.5;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.regles-box ul li i {
    color: var(--success);
    margin-top: 3px;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.regles-box ul li strong {
    color: var(--gold-deep);
    font-weight: 600;
}

/* ═══════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════ */
@media (max-width: 900px) {
    .points-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 600px) {
    .points-container { padding: 20px 14px 60px; }
    
    .points-header {
        padding: 30px 24px;
        flex-direction: column;
        text-align: center;
        border-radius: 20px;
    }
    .points-header .left h1 { justify-content: center; font-size: 1.5rem; }
    .points-header .points-big { padding: 16px 28px; }
    .points-header .points-big .number { font-size: 2.4rem; }
    
    .points-grid { grid-template-columns: 1fr; gap: 14px; }
    .points-card { padding: 22px 18px; }
    .points-card .value { font-size: 1.9rem; }
    
    .regles-box { padding: 22px 20px; }
    .regles-box ul li { font-size: 0.8rem; }
}
</style>

<div class="points-container">

    <!-- ═══════ HEADER ═══════ -->
    <div class="points-header">
        <div class="left">
            <h1><i class="bi bi-star-fill"></i> Mes <span>points fidélité</span></h1>
            <p>Cumulez des points à chaque commande terminée et profitez de réductions exclusives.</p>
        </div>
        <div class="points-big">
            <div class="number"><?= number_format($points) ?></div>
            <div class="label">Points disponibles</div>
            <?php if ($reduction_possible > 0): ?>
                <div class="reduction">
                    = <?= number_format($reduction_possible, 0, ',', ' ') ?> F de réduction
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════ SIDEBAR ═══════ -->
    <div class="compte-sidebar">
        <div class="menu-item">
            <a href="mon_compte.php"><i class="bi bi-grid"></i> Tableau de bord</a>
        </div>
        <div class="menu-item">
            <a href="mes_commandes.php"><i class="bi bi-receipt"></i> Mes commandes</a>
        </div>
        <div class="menu-item">
            <a href="ma_wishlist.php"><i class="bi bi-heart"></i> Ma wishlist</a>
        </div>
        <div class="menu-item">
            <a href="mes_factures.php"><i class="bi bi-file-pdf"></i> Mes factures</a>
        </div>
        <div class="menu-item active">
            <a href="mes_points.php"><i class="bi bi-star"></i> Points fidélité</a>
        </div>
        <div class="menu-item">
            <a href="mon_profil.php"><i class="bi bi-person"></i> Mon profil</a>
        </div>
        <div class="menu-item logout">
            <a href="deconnexion.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <!-- ═══════ GRILLE STATS ═══════ -->
    <div class="points-grid">
        
        <div class="points-card">
            <h3><i class="bi bi-star-fill"></i> Points disponibles</h3>
            <div class="value"><?= number_format($points) ?></div>
            <div class="sub">Points à utiliser sur vos prochains achats</div>
            <?php if ($reduction_possible > 0): ?>
                <div class="info-text">
                    <p>
                        <i class="bi bi-gift-fill" style="color:var(--gold);"></i>
                        <span class="highlight"><?= number_format($reduction_possible, 0, ',', ' ') ?> FCFA</span> de réduction possible
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="points-card">
            <h3><i class="bi bi-graph-up-arrow"></i> Statistiques</h3>
            <div class="value green"><?= number_format($total_gagnes) ?></div>
            <div class="sub">Points gagnés au total</div>
            <div style="margin-top:10px;">
                <p style="font-size:0.8rem;color:#5A6B7A;margin:6px 0;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-check-circle-fill" style="color:var(--success);"></i> 
                    <?= $commandes_terminees ?> commande(s) terminée(s)
                </p>
                <?php if ($points_potentiels > 0): ?>
                <p style="font-size:0.8rem;color:#5A6B7A;margin:6px 0;display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-clock-history" style="color:var(--gold);"></i> 
                    <?= $points_potentiels ?> point(s) en attente
                </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="points-card">
            <h3><i class="bi bi-info-circle"></i> Comment ça marche ?</h3>
            <div style="margin-top:5px;">
                <p style="font-size:0.8rem;color:#5A6B7A;margin:5px 0;">
                    <strong style="color:var(--gold);">1 point</strong> = <?= number_format($seuil_points, 0, ',', ' ') ?> FCFA dépensés
                </p>
                <p style="font-size:0.8rem;color:#5A6B7A;margin:5px 0;">
                    <strong style="color:var(--gold);"><?= $reduction_points ?> points</strong> = <?= number_format($reduction_montant, 0, ',', ' ') ?> FCFA de réduction
                </p>
                <div class="progress" style="margin-top:12px;">
                    <div class="progress-bar" style="width: <?= $pourcentage_progression ?>%"></div>
                </div>
                <?php if ($points_restants > 0): ?>
                    <p style="font-size:0.72rem;color:var(--muted);margin-top:8px;">
                        Plus que <strong style="color:var(--gold);"><?= $points_restants ?> points</strong> pour le prochain palier
                    </p>
                <?php else: ?>
                    <p style="font-size:0.72rem;color:var(--success);margin-top:8px;font-weight:500;">
                        <i class="bi bi-check-circle-fill"></i> Palier atteint !
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════ RÈGLES ═══════ -->
    <div class="regles-box">
        <h3><i class="bi bi-book"></i> Règles du programme fidélité</h3>
        <ul>
            <li>
                <i class="bi bi-check-circle-fill"></i>
                <span><strong>1 point</strong> gagné pour <?= number_format($seuil_points, 0, ',', ' ') ?> FCFA dépensés</span>
            </li>
            <li>
                <i class="bi bi-check-circle-fill"></i>
                <span>Les points sont crédités lorsque la commande est <strong>livrée</strong> ou <strong>terminée</strong></span>
            </li>
            <li>
                <i class="bi bi-check-circle-fill"></i>
                <span><strong><?= $reduction_points ?> points</strong> = <?= number_format($reduction_montant, 0, ',', ' ') ?> FCFA de réduction sur votre prochaine commande</span>
            </li>
            <li>
                <i class="bi bi-check-circle-fill"></i>
                <span>Les points sont valables 1 an à partir de leur date d'obtention</span>
            </li>
            <li>
                <i class="bi bi-check-circle-fill"></i>
                <span>En cas d'annulation, les points sont retirés</span>
            </li>
        </ul>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>