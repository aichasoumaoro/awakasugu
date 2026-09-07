<?php
// ============================================
// PANIER D'ACHAT - Awa Ka Sugu
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

// ============================================
// INCLURE LES FONCTIONS DU PANIER
// ============================================
require_once '../includes/panier_fonctions.php';

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
    die("Erreur BDD: " . $e->getMessage());
}

// Initialiser le panier
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// ============================================
// AJOUTER AU PANIER (depuis produit.php)
// ============================================
if (isset($_POST['action']) && $_POST['action'] == 'ajouter') {
    $produit_id = (int)$_POST['produit_id'];
    $quantite = (int)$_POST['quantite'];
    $couleur_id = isset($_POST['couleur_id']) ? (int)$_POST['couleur_id'] : 0;
    $taille_id = isset($_POST['taille_id']) ? (int)$_POST['taille_id'] : 0;
    
    // Récupérer les infos de couleur et taille
    $couleur_nom = '';
    $couleur_hex = '';
    $taille_nom = '';
    
    if ($couleur_id > 0) {
        $stmt = $pdo->prepare("SELECT nom, code_hex FROM couleurs WHERE id = ?");
        $stmt->execute([$couleur_id]);
        $couleur = $stmt->fetch();
        $couleur_nom = $couleur['nom'] ?? '';
        $couleur_hex = $couleur['code_hex'] ?? '';
    }
    
    if ($taille_id > 0) {
        $stmt = $pdo->prepare("SELECT nom FROM tailles WHERE id = ?");
        $stmt->execute([$taille_id]);
        $taille = $stmt->fetch();
        $taille_nom = $taille['nom'] ?? '';
    }

    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();

    if ($produit) {
        $prix = ($produit['prix_promo'] && $produit['prix_promo'] > 0 && $produit['prix_promo'] < $produit['prix']) 
                ? $produit['prix_promo'] 
                : $produit['prix'];
        
        // Clé unique pour le panier (avec couleur et taille)
        $cle_panier = $produit_id . '_' . $couleur_id . '_' . $taille_id;
        
        if (isset($_SESSION['panier'][$cle_panier])) {
            $_SESSION['panier'][$cle_panier]['quantite'] += $quantite;
        } else {
            $_SESSION['panier'][$cle_panier] = [
                'id' => $produit['id'],
                'nom' => $produit['nom'],
                'prix' => $prix,
                'quantite' => $quantite,
                'couleur_id' => $couleur_id,
                'couleur_nom' => $couleur_nom,
                'couleur_hex' => $couleur_hex,
                'taille_id' => $taille_id,
                'taille_nom' => $taille_nom,
                'image' => $produit['image_principale']
            ];
        }
        
        // Si client connecté, sauvegarder en BDD
        if (isset($_SESSION['client_id'])) {
            sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
        }
    }
    header('Location: panier.php');
    exit;
}

// ============================================
// MODIFIER QUANTITÉ
// ============================================
if (isset($_GET['modifier'])) {
    $cle = $_GET['modifier'];
    $qte = (int)$_GET['qte'];
    if (isset($_SESSION['panier'][$cle])) {
        if ($qte <= 0) {
            unset($_SESSION['panier'][$cle]);
        } else {
            $_SESSION['panier'][$cle]['quantite'] = $qte;
        }
    }
    // Supprimer le code promo si le panier est vide
    if (empty($_SESSION['panier'])) {
        unset($_SESSION['code_promo']);
    }
    if (isset($_SESSION['client_id'])) {
        sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
    }
    header('Location: panier.php');
    exit;
}

// ============================================
// SUPPRIMER PRODUIT
// ============================================
if (isset($_GET['supprimer'])) {
    $cle = $_GET['supprimer'];
    unset($_SESSION['panier'][$cle]);
    // Supprimer le code promo si le panier est vide
    if (empty($_SESSION['panier'])) {
        unset($_SESSION['code_promo']);
    }
    if (isset($_SESSION['client_id'])) {
        sauvegarderPanierClient($_SESSION['client_id'], $_SESSION['panier'], $pdo);
    }
    header('Location: panier.php');
    exit;
}

// ============================================
// VIDER PANIER
// ============================================
if (isset($_GET['vider'])) {
    $_SESSION['panier'] = [];
    unset($_SESSION['code_promo']);
    if (isset($_SESSION['client_id'])) {
        viderPanierBDD($_SESSION['client_id'], $pdo);
    }
    header('Location: panier.php');
    exit;
}

// ============================================
// APPLIQUER UN CODE PROMO
// ============================================
if (isset($_POST['appliquer_promo'])) {
    $code = strtoupper(trim($_POST['code_promo']));
    $message_promo = '';
    
    if (empty($code)) {
        $_SESSION['message_promo'] = '⚠️ Veuillez saisir un code promo.';
    } else {
        // Vérifier le code dans la BDD
        $stmt = $pdo->prepare("
            SELECT * FROM codes_promo 
            WHERE code = ? AND est_actif = 1
            AND (date_expiration IS NULL OR date_expiration > NOW())
            AND (nb_utilisations_max IS NULL OR nb_utilisations < nb_utilisations_max)
        ");
        $stmt->execute([$code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($promo) {
            // Calculer le total du panier
            $total = 0;
            foreach ($_SESSION['panier'] as $item) {
                $total += $item['prix'] * $item['quantite'];
            }
            
            if ($total >= $promo['min_achat']) {
                // Calculer la réduction
                if ($promo['type'] == 'pourcentage') {
                    $reduction = $total * ($promo['valeur'] / 100);
                } else {
                    $reduction = $promo['valeur'];
                }
                // Limiter la réduction au total du panier
                if ($reduction > $total) {
                    $reduction = $total;
                }
                
                $_SESSION['code_promo'] = [
                    'code' => $promo['code'],
                    'id' => $promo['id'],
                    'type' => $promo['type'],
                    'valeur' => $promo['valeur'],
                    'reduction' => $reduction
                ];
                $_SESSION['message_promo'] = '✅ Code promo "' . $promo['code'] . '" appliqué ! Réduction de ' . number_format($reduction, 0, ',', ' ') . ' FCFA.';
            } else {
                $_SESSION['message_promo'] = '⚠️ Ce code promo nécessite un achat minimum de ' . number_format($promo['min_achat'], 0, ',', ' ') . ' FCFA.';
            }
        } else {
            $_SESSION['message_promo'] = '❌ Code promo invalide ou expiré.';
        }
    }
    header('Location: panier.php');
    exit;
}

// ============================================
// SUPPRIMER LE CODE PROMO
// ============================================
if (isset($_GET['supprimer_promo'])) {
    unset($_SESSION['code_promo']);
    $_SESSION['message_promo'] = '✅ Code promo retiré.';
    header('Location: panier.php');
    exit;
}

// ============================================
// CALCUL DU TOTAL
// ============================================
$total = 0;
foreach ($_SESSION['panier'] as $item) {
    $total += $item['prix'] * $item['quantite'];
}

// ============================================
// APPLIQUER LA RÉDUCTION DU CODE PROMO
// ============================================
$reduction_appliquee = 0;
$code_promo_info = $_SESSION['code_promo'] ?? null;

if ($code_promo_info) {
    $reduction_appliquee = $code_promo_info['reduction'] ?? 0;
    // Si le total est inférieur à la réduction
    if ($reduction_appliquee > $total) {
        $reduction_appliquee = $total;
        $_SESSION['code_promo']['reduction'] = $reduction_appliquee;
    }
    // Si le total est 0, supprimer le code promo
    if ($total == 0) {
        unset($_SESSION['code_promo']);
        $reduction_appliquee = 0;
    }
}

$total_apres_reduction = $total - $reduction_appliquee;

// ============================================
// RÉCUPÉRER LES MESSAGES
// ============================================
$message_promo = $_SESSION['message_promo'] ?? '';
unset($_SESSION['message_promo']);

// ============================================
// AFFICHAGE
// ============================================
$titre_page = 'Mon panier - IBA Design';
$meta_desc = 'Consultez et gérez votre panier d\'achat.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<style>
/* ========== PAGE PANIER ========== */
.panier-header {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
    padding: 40px 0 30px;
    text-align: center;
    margin-bottom: 40px;
}
.panier-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    color: #C8922A;
    margin-bottom: 8px;
}
.panier-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.container-custom {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 20px 60px;
}
.panier-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.06);
    border: 1px solid rgba(200,146,42,0.08);
}
.panier-empty {
    text-align: center;
    padding: 60px 20px;
}
.panier-empty i {
    font-size: 4rem;
    color: #E8E0D8;
    display: block;
    margin-bottom: 20px;
}
.panier-empty h3 {
    font-family: 'Playfair Display', serif;
    color: #0D0D0D;
    margin-bottom: 10px;
}
.panier-empty p {
    color: #8A99AA;
    font-size: 0.95rem;
    margin-bottom: 25px;
}
.table-panier {
    width: 100%;
    border-collapse: collapse;
}
.table-panier thead th {
    color: #C8922A;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 12px 15px;
    border-bottom: 2px solid rgba(200,146,42,0.15);
}
.table-panier tbody td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #F0F2F5;
}
.table-panier tbody tr:hover td {
    background: #FEFBF5;
}
.table-panier .product-name {
    display: flex;
    align-items: center;
    gap: 12px;
}
.table-panier .product-name i {
    font-size: 1.5rem;
    color: #C8922A;
}
.table-panier .product-name strong {
    font-size: 0.95rem;
    color: #0D0D0D;
}
.table-panier .product-options {
    font-size: 0.75rem;
    color: #8A99AA;
    margin-top: 2px;
}
.table-panier .product-options .color-dot {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 1px solid #ddd;
    vertical-align: middle;
    margin-right: 4px;
}
.qte-input {
    width: 60px;
    padding: 8px 10px;
    text-align: center;
    border: 1.5px solid #E0E6ED;
    border-radius: 8px;
    font-size: 0.9rem;
    font-family: 'Jost', sans-serif;
    transition: border-color 0.3s;
}
.qte-input:focus {
    outline: none;
    border-color: #C8922A;
}
.price-item {
    font-weight: 600;
    color: #0D0D0D;
}
.price-total {
    font-weight: 700;
    color: #C8922A;
}
.btn-delete {
    color: #E74C3C;
    text-decoration: none;
    transition: color 0.3s;
    font-size: 1.1rem;
}
.btn-delete:hover {
    color: #C0392B;
}
.panier-total {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px solid rgba(200,146,42,0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}
.panier-total .total-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0D0D0D;
}
.panier-total .total-amount {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #C8922A;
}
.panier-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}
.btn-continuer {
    background: #6C757D;
    color: white;
    padding: 12px 25px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-continuer:hover {
    background: #5A6268;
    color: white;
}
.btn-vider {
    background: transparent;
    color: #E74C3C;
    padding: 12px 25px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    border: 1.5px solid #E74C3C;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-vider:hover {
    background: #E74C3C;
    color: white;
}
.btn-commander {
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}
.btn-commander:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
    color: white;
}
.btn-primary-custom {
    display: inline-block;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 12px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-primary-custom:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
    color: white;
}

/* ========================================== */
/* STYLES POUR LE CODE PROMO */
/* ========================================== */
.promo-section {
    margin: 20px 0 15px;
    padding: 20px;
    background: #F8F9FA;
    border-radius: 12px;
    border: 1px dashed #E0E6ED;
}
.promo-section .promo-title {
    font-weight: 600;
    color: #0D0D0D;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.promo-section .promo-title i {
    color: #C8922A;
}
.promo-section .input-group {
    display: flex;
    gap: 10px;
}
.promo-section input {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid #E0E6ED;
    border-radius: 8px;
    font-family: 'Jost', sans-serif;
    font-size: 0.95rem;
    text-transform: uppercase;
    transition: border-color 0.3s;
    background: #fff;
}
.promo-section input:focus {
    outline: none;
    border-color: #C8922A;
}
.promo-section input:disabled {
    background: #F0F2F5;
    cursor: not-allowed;
}
.promo-section .btn-promo {
    background: #C8922A;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
    font-family: 'Jost', sans-serif;
    white-space: nowrap;
}
.promo-section .btn-promo:hover {
    background: #9A6E1A;
}
.promo-section .btn-promo:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.promo-message {
    margin-top: 10px;
    font-size: 0.85rem;
    padding: 8px 12px;
    border-radius: 8px;
}
.promo-message.success {
    background: #D4EDDA;
    color: #0A3622;
}
.promo-message.error {
    background: #F8D7DA;
    color: #721C24;
}
.promo-message.warning {
    background: #FFF3CD;
    color: #856404;
}
.promo-applique {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #D4EDDA;
    padding: 12px 18px;
    border-radius: 8px;
    color: #0A3622;
    margin: 10px 0 15px;
}
.promo-applique .code {
    font-weight: 700;
    color: #0D0D0D;
}
.promo-applique .reduction {
    font-weight: 700;
    color: #27AE60;
}
.promo-applique .btn-retirer {
    background: rgba(231,76,60,0.15);
    color: #E74C3C;
    border: none;
    padding: 4px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}
.promo-applique .btn-retirer:hover {
    background: #E74C3C;
    color: #fff;
}
/* ========================================== */

.option-badge {
    display: inline-block;
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 500;
    background: #F0F2F5;
    color: #555;
    margin-right: 4px;
}
.option-badge.color {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.option-badge.color .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    border: 1px solid #ddd;
}

@media (max-width: 768px) {
    .panier-header h1 { font-size: 1.8rem; }
    .panier-card { padding: 20px; }
    .table-panier thead { display: none; }
    .table-panier tbody td {
        display: block;
        text-align: right;
        padding: 10px 15px;
        border-bottom: 1px solid #F0F2F5;
    }
    .table-panier tbody td:before {
        content: attr(data-label);
        float: left;
        font-weight: 600;
        color: #8A99AA;
    }
    .table-panier tbody tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid #F0F2F5;
        border-radius: 12px;
        overflow: hidden;
    }
    .table-panier tbody tr:hover td { background: transparent; }
    .table-panier .product-name { justify-content: flex-end; }
    .panier-total { flex-direction: column; text-align: center; }
    .panier-actions { justify-content: center; }
    .promo-section .input-group { flex-direction: column; }
}
</style>

<!-- Header -->
<div class="panier-header">
    <div class="container-custom" style="padding-bottom:0;">
        <h1>🛒 Mon panier</h1>
        <p>Consultez et gérez les produits sélectionnés</p>
    </div>
</div>

<div class="container-custom">
    <?php if(empty($_SESSION['panier'])): ?>
        <div class="panier-card panier-empty">
            <i class="bi bi-cart-x"></i>
            <h3>Votre panier est vide</h3>
            <p>Découvrez nos produits et faites votre sélection</p>
            <a href="catalogue.php" class="btn-primary-custom">
                <i class="bi bi-bag"></i> Découvrir la boutique
            </a>
        </div>
    <?php else: ?>
        <div class="panier-card">
            <table class="table-panier">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th style="text-align:center;">Options</th>
                        <th style="text-align:center;">Prix unitaire</th>
                        <th style="text-align:center;">Quantité</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($_SESSION['panier'] as $cle => $item): ?>
                    <tr>
                        <td data-label="Produit">
                            <div class="product-name">
                                <i class="bi bi-bag"></i>
                                <div>
                                    <strong><?= htmlspecialchars($item['nom']) ?></strong>
                                </div>
                            </div>
                        </td>
                        <td data-label="Options" style="text-align:center;">
                            <?php if(!empty($item['couleur_nom']) || !empty($item['taille_nom'])): ?>
                                <?php if(!empty($item['couleur_nom'])): ?>
                                    <span class="option-badge color">
                                        <span class="dot" style="background-color: <?= $item['couleur_hex'] ?? '#ccc' ?>;"></span>
                                        <?= htmlspecialchars($item['couleur_nom']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if(!empty($item['taille_nom'])): ?>
                                    <span class="option-badge"><?= htmlspecialchars($item['taille_nom']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#bbb;font-size:0.7rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Prix unitaire" style="text-align:center;">
                            <span class="price-item"><?= number_format($item['prix'], 0, ',', ' ') ?> FCFA</span>
                        </td>
                        <td data-label="Quantité" style="text-align:center;">
                            <input type="number" class="qte-input" value="<?= $item['quantite'] ?>"
                                   onchange="window.location.href='panier.php?modifier=<?= urlencode($cle) ?>&qte='+this.value" min="1">
                        </td>
                        <td data-label="Total" style="text-align:center;">
                            <span class="price-total"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FCFA</span>
                        </td>
                        <td data-label="Action" style="text-align:center;">
                            <a href="panier.php?supprimer=<?= urlencode($cle) ?>" class="btn-delete" onclick="return confirm('Supprimer ce produit du panier ?')">
                                <i class="bi bi-trash3"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- ========================================== -->
            <!-- SECTION CODE PROMO (NOUVEAU)              -->
            <!-- ========================================== -->
            <div class="promo-section">
                <div class="promo-title">
                    <i class="bi bi-ticket-perforated"></i> Code promo
                </div>

                <?php if(isset($_SESSION['code_promo'])): ?>
                    <!-- Code promo déjà appliqué -->
                    <div class="promo-applique">
                        <div>
                            🏷️ Code <span class="code"><?= htmlspecialchars($_SESSION['code_promo']['code']) ?></span>
                            <span style="color:#8A99AA;font-size:0.8rem;margin-left:10px;">
                                (<?= $_SESSION['code_promo']['type'] == 'pourcentage' ? $_SESSION['code_promo']['valeur'] . '%' : number_format($_SESSION['code_promo']['valeur'], 0, ',', ' ') . ' FCFA' ?>)
                            </span>
                        </div>
                        <div>
                            <span class="reduction">- <?= number_format($reduction_appliquee, 0, ',', ' ') ?> FCFA</span>
                            <a href="panier.php?supprimer_promo=1" class="btn-retirer" onclick="return confirm('Retirer ce code promo ?')">
                                <i class="bi bi-x"></i> Retirer
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Formulaire code promo -->
                    <form method="POST" class="input-group">
                        <input type="text" name="code_promo" placeholder="Entrez votre code promo (ex: BIENVENUE10)" id="code_promo_input">
                        <button type="submit" name="appliquer_promo" class="btn-promo">
                            <i class="bi bi-check2"></i> Appliquer
                        </button>
                    </form>
                <?php endif; ?>

                <?php if($message_promo): ?>
                    <div class="promo-message <?= 
                        strpos($message_promo, '✅') !== false ? 'success' : 
                        (strpos($message_promo, '⚠️') !== false ? 'warning' : 'error') 
                    ?>">
                        <?= $message_promo ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- ========================================== -->

            <div class="panier-total">
                <div>
                    <span class="total-label">Total de la commande</span>
                    <?php if($reduction_appliquee > 0): ?>
                        <div style="font-size:0.8rem;color:#8A99AA;text-decoration:line-through;">
                            <?= number_format($total, 0, ',', ' ') ?> FCFA
                        </div>
                        <div class="total-amount"><?= number_format($total_apres_reduction, 0, ',', ' ') ?> FCFA</div>
                    <?php else: ?>
                        <div class="total-amount"><?= number_format($total, 0, ',', ' ') ?> FCFA</div>
                    <?php endif; ?>
                </div>
                <div class="panier-actions">
                    <a href="catalogue.php" class="btn-continuer">
                        <i class="bi bi-arrow-left"></i> Continuer
                    </a>
                    <a href="panier.php?vider=1" class="btn-vider" onclick="return confirm('Vider tout le panier ?')">
                        <i class="bi bi-trash"></i> Vider
                    </a>
                    <a href="commande.php" class="btn-commander">
                        <i class="bi bi-check-circle"></i> Passer la commande
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// ============================================
// RECHERCHE AUTOMATIQUE DE CODE PROMO (optionnel)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const promoInput = document.getElementById('code_promo_input');
    if (promoInput) {
        // Convertir en majuscules automatiquement
        promoInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>