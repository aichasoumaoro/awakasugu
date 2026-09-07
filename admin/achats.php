<?php
// ============================================
// ACHATS - ADMIN AWA KA SUGU
// ============================================

require_once '../includes/session_config.php';

// ============================================
// VÉRIFICATION DE CONNEXION
// ============================================
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

// ============================================
// RÉCUPÉRATION DES INFOS ADMIN
// ============================================
$admin_info = getAdminInfo();
$admin_role = $admin_info['role'] ?? 'admin';
$admin_nom = $admin_info['nom'] ?? 'Awa Doumbia';
$admin_id = $admin_info['id'] ?? 0;

// Vérification des permissions (Achats visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Gestion des Achats';

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
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données.");
}

// ============================================
// AJOUTER UN ACHAT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_achat'])) {
    $produit_id = (int)$_POST['produit_id'];
    $quantite = (int)$_POST['quantite'];
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $fournisseur_nom = trim($_POST['fournisseur_nom'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($produit_id > 0 && $quantite > 0 && $prix_unitaire > 0) {
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
        $stmt->execute([$produit_id]);
        $produit = $stmt->fetch();
        
        if ($produit) {
            $numero_achat = 'ACH-' . date('Ymd') . '-' . strtoupper(uniqid());
            $total_ligne = $quantite * $prix_unitaire;
            
            $stmt = $pdo->prepare("
                INSERT INTO achats (
                    numero_achat, produit_id, nom_produit, quantite, 
                    prix_unitaire, total_ligne, nom_fournisseur, notes, date_achat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $numero_achat, $produit_id, $produit['nom'],
                $quantite, $prix_unitaire, $total_ligne,
                $fournisseur_nom, $notes
            ]);
            
            $nouveau_stock = $produit['stock'] + $quantite;
            $pdo->prepare("UPDATE produits SET stock = ? WHERE id = ?")->execute([$nouveau_stock, $produit_id]);
            
            $_SESSION['message_achat'] = 'Achat enregistré avec succès !';
            header('Location: achats.php');
            exit;
        }
    }
}

// ============================================
// SUPPRIMER UN ACHAT
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    $pdo->prepare("DELETE FROM achats WHERE id = ?")->execute([$id]);
    $_SESSION['message_achat'] = 'Achat supprimé avec succès !';
    header('Location: achats.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================

$produits = $pdo->query("SELECT * FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();
$achats = $pdo->query("SELECT * FROM achats ORDER BY date_achat DESC")->fetchAll();

$total_achats = $pdo->query("SELECT COUNT(*) FROM achats")->fetchColumn();
$total_depenses = $pdo->query("SELECT COALESCE(SUM(total_ligne), 0) FROM achats")->fetchColumn();
$total_articles_achetes = $pdo->query("SELECT COALESCE(SUM(quantite), 0) FROM achats")->fetchColumn();
$achats_mois = $pdo->query("
    SELECT COALESCE(SUM(total_ligne), 0) FROM achats 
    WHERE MONTH(date_achat) = MONTH(CURDATE()) AND YEAR(date_achat) = YEAR(CURDATE())
")->fetchColumn();

$message = $_SESSION['message_achat'] ?? '';
unset($_SESSION['message_achat']);

// Maintenance
$maintenance_status = $pdo->query("
    SELECT site_actif, message_maintenance 
    FROM maintenance_globale 
    ORDER BY id DESC LIMIT 1
")->fetch();
$site_en_maintenance = $maintenance_status && $maintenance_status['site_actif'] == 0;

// ============================================
// INCLUSION DU HEADER ET DE LA SIDEBAR
// ============================================
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<!-- ============================================
     MAIN CONTENT
     ============================================ -->
<div class="main">

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div>
            <div class="topbar-title">🛒 Gestion des <span>Achats</span></div>
            <div class="topbar-breadcrumb">Administration → Achats → Approvisionnement</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-site">
                <i class="bi bi-eye"></i> Voir le site
            </a>
        </div>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-cart-check"></i></div>
                <div>
                    <div class="stat-val"><?= $total_achats ?></div>
                    <div class="stat-lbl">Total Achats</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-box"></i></div>
                <div>
                    <div class="stat-val"><?= $total_articles_achetes ?></div>
                    <div class="stat-lbl">Articles achetés</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($total_depenses, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Total dépenses</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-calendar-month"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($achats_mois, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Achats du mois</div>
                </div>
            </div>
        </div>

        <!-- ===== FORMULAIRE D'ACHAT ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-plus-circle"></i> Enregistrer un achat</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:15px;align-items:end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Produit</label>
                            <select name="produit_id" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" required>
                                <option value="">Sélectionner un produit</option>
                                <?php foreach($produits as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['nom']) ?> (Stock: <?= $p['stock'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Quantité</label>
                            <input type="number" name="quantite" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Prix unitaire (FCFA)</label>
                            <input type="number" name="prix_unitaire" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Fournisseur</label>
                            <input type="text" name="fournisseur_nom" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" placeholder="Nom du fournisseur">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" name="ajouter_achat" class="btn-admin btn-success" style="width:100%;padding:10px;">
                                <i class="bi bi-save"></i> Enregistrer
                            </button>
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Notes</label>
                            <input type="text" name="notes" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;font-family:'Jost',sans-serif;" placeholder="Notes sur cet achat...">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== LISTE DES ACHATS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-list"></i> Historique des achats</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($achats) ?> achat(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-achats">
                        <thead>
                            <tr>
                                <th>N° Achat</th>
                                <th>Produit</th>
                                <th>Fournisseur</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($achats)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="bi bi-cart-x"></i>
                                            <p>Aucun achat enregistré</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($achats as $a): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($a['numero_achat']) ?></strong></td>
                                    <td><?= htmlspecialchars($a['nom_produit']) ?></td>
                                    <td><?= htmlspecialchars($a['nom_fournisseur'] ?? '-') ?></td>
                                    <td><strong><?= $a['quantite'] ?></strong></td>
                                    <td><?= number_format($a['prix_unitaire'], 0, ',', ' ') ?> F</td>
                                    <td style="color:#C8922A;font-weight:600;"><?= number_format($a['total_ligne'], 0, ',', ' ') ?> F</td>
                                    <td style="font-size:0.75rem;color:#999;"><?= date('d/m/Y H:i', strtotime($a['date_achat'])) ?></td>
                                    <td style="text-align:center;">
                                        <a href="achats.php?supprimer=<?= $a['id'] ?>" class="btn-small red" onclick="return confirm('Supprimer cet achat ?')">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>