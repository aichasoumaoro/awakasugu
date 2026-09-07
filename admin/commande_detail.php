<?php
// ============================================
// COMMANDE DETAIL - ADMIN AWA KA SUGU
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

$page_title = 'Détail de la commande';

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
    die("Erreur de connexion : " . $e->getMessage());
}

$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($commande_id <= 0) {
    header('Location: commandes.php');
    exit;
}

// Récupérer la commande
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: commandes.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DÉTAILS AVEC COULEURS ET TAILLES
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        dc.*,
        p.nom as produit_nom,
        p.image_principale,
        c.nom as couleur_nom_complet,
        c.code_hex as couleur_code,
        t.nom as taille_nom_complet
    FROM details_commande dc
    LEFT JOIN produits p ON p.id = dc.produit_id
    LEFT JOIN couleurs c ON c.id = dc.couleur_id
    LEFT JOIN tailles t ON t.id = dc.taille_id
    WHERE dc.commande_id = ?
");
$stmt->execute([$commande_id]);
$details = $stmt->fetchAll();

// Si aucune couleur/taille trouvée via les ID, essayer avec les noms
if (!empty($details) && empty($details[0]['couleur_nom_complet'])) {
    $stmt = $pdo->prepare("
        SELECT 
            dc.*,
            p.nom as produit_nom,
            p.image_principale,
            dc.couleur_nom as couleur_nom_complet,
            NULL as couleur_code,
            dc.taille_nom as taille_nom_complet
        FROM details_commande dc
        LEFT JOIN produits p ON p.id = dc.produit_id
        WHERE dc.commande_id = ?
    ");
    $stmt->execute([$commande_id]);
    $details = $stmt->fetchAll();
}

// Traitement du changement de statut
if (isset($_GET['changer_statut']) && isset($_GET['statut'])) {
    $new_statut = $_GET['statut'];
    $allowed_statuts = ['en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee'];
    if (in_array($new_statut, $allowed_statuts)) {
        $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?")->execute([$new_statut, $commande_id]);
        $_SESSION['message_commande'] = 'Statut de la commande mis à jour !';
        header('Location: commande_detail.php?id=' . $commande_id);
        exit;
    }
}

$message = $_SESSION['message_commande'] ?? '';
unset($_SESSION['message_commande']);

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
            <div class="topbar-title">📦 Détail de la commande <span>#<?= htmlspecialchars($commande['numero_commande']) ?></span></div>
            <div class="topbar-breadcrumb">
                <a href="dashboard.php" style="color:#8A99AA;text-decoration:none;">Administration</a> &gt; 
                <a href="commandes.php" style="color:#8A99AA;text-decoration:none;">Commandes</a> &gt; 
                Détail
            </div>
        </div>
        <div class="topbar-right">
            <a href="commandes.php" class="btn-admin btn-outline">
                <i class="bi bi-arrow-left"></i> Retour aux commandes
            </a>
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

        <!-- ===== INFOS COMMANDE ===== -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
            
            <!-- Informations client -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-person"></i> Informations client</div>
                </div>
                <div class="card-body">
                    <div style="background:#F8F9FA;padding:15px 20px;border-radius:10px;border-left:3px solid #C8922A;">
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Nom</span>
                            <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($commande['nom_client']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Téléphone</span>
                            <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($commande['telephone']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Email</span>
                            <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($commande['email'] ?? 'Non renseigné') ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Adresse</span>
                            <span style="font-weight:600;color:#0D0D0D;text-align:right;"><?= nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')) ?></span>
                        </div>
                        <?php if(!empty($commande['commune'])): ?>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Commune</span>
                            <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($commande['commune']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Informations commande -->
            <div class="card-white">
                <div class="card-header">
                    <div class="card-title"><i class="bi bi-info-circle"></i> Informations commande</div>
                </div>
                <div class="card-body">
                    <div style="background:#F8F9FA;padding:15px 20px;border-radius:10px;border-left:3px solid #C8922A;">
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">N° commande</span>
                            <span style="font-weight:600;color:#0D0D0D;"><?= htmlspecialchars($commande['numero_commande']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Date</span>
                            <span style="font-weight:600;color:#0D0D0D;"><?= date('d/m/Y à H:i', strtotime($commande['created_at'])) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Paiement</span>
                            <span>
                                <?php 
                                $paiements = [
                                    'livraison' => '💵 À la livraison',
                                    'orange_money' => '🟠 Orange Money',
                                    'wave' => '🌊 Wave'
                                ];
                                $mode = $commande['mode_paiement'] ?? 'livraison';
                                echo '<span style="background:rgba(200,146,42,0.1);padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;color:#C8922A;">' . ($paiements[$mode] ?? $mode) . '</span>';
                                ?>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Livraison</span>
                            <span>
                                <?php 
                                $livraisons = [
                                    'livraison' => '🚚 Domicile',
                                    'retrait_boutique' => '🏪 Retrait boutique'
                                ];
                                $mode_liv = $commande['mode_livraison'] ?? 'livraison';
                                echo '<span style="background:rgba(200,146,42,0.1);padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;color:#C8922A;">' . ($livraisons[$mode_liv] ?? $mode_liv) . '</span>';
                                ?>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F0F2F5;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Statut</span>
                            <span>
                                <?php 
                                $statut_labels = [
                                    'en_attente' => 'En attente',
                                    'confirmee' => 'Confirmée',
                                    'en_preparation' => 'Préparation',
                                    'en_livraison' => 'Livraison',
                                    'livree' => 'Livrée',
                                    'annulee' => 'Annulée'
                                ];
                                $statut_key = $commande['statut'] ?? 'en_attente';
                                ?>
                                <span class="badge-statut statut-<?= $statut_key ?>">
                                    <?= $statut_labels[$statut_key] ?? $statut_key ?>
                                </span>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:4px 0;">
                            <span style="color:#8A99AA;font-size:0.8rem;">Total</span>
                            <span style="font-size:1.1rem;font-weight:700;color:#C8922A;"><?= number_format($commande['total'], 0, ',', ' ') ?> F</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ARTICLES COMMANDÉS ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-bag"></i> Articles commandés</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($details) ?> article(s)</div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="table-commandes">
                        <thead>
                            <tr>
                                <th style="min-width:200px;">Produit</th>
                                <th style="text-align:center;">Qté</th>
                                <th style="text-align:center;min-width:130px;">Couleur</th>
                                <th style="text-align:center;min-width:90px;">Taille</th>
                                <th style="text-align:right;">Prix unitaire</th>
                                <th style="text-align:right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($details)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p>Aucun article dans cette commande</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($details as $d): 
                                    // Récupérer l'image du produit
                                    $image_path = '';
                                    if (!empty($d['image_principale'])) {
                                        $image_name = pathinfo($d['image_principale'], PATHINFO_FILENAME);
                                        $dossiers = [
                                            '../uploads/produits/voile/',
                                            'uploads/produits/voile/',
                                            '../uploads/produits/pret a porter femme/',
                                            'uploads/produits/pret a porter femme/',
                                            '../uploads/produits/les tallons/',
                                            'uploads/produits/les tallons/',
                                            '../uploads/produits/fermés/',
                                            'uploads/produits/fermés/',
                                            '../uploads/produits/les turbants/',
                                            'uploads/produits/les turbants/',
                                            '../uploads/produits/les foulards/',
                                            'uploads/produits/les foulards/',
                                            '../uploads/produits/les foullards/',
                                            'uploads/produits/les foullards/',
                                            '../uploads/produits/port-monaie/',
                                            'uploads/produits/port-monaie/',
                                            '../uploads/produits/sacs a mains/',
                                            'uploads/produits/sacs a mains/',
                                            '../uploads/produits/ensemble tallons sacs/',
                                            'uploads/produits/ensemble tallons sacs/',
                                            '../uploads/produits/abayas/',
                                            'uploads/produits/abayas/',
                                            '../uploads/produits/abayas pour enfants/',
                                            'uploads/produits/abayas pour enfants/',
                                            '../uploads/produits/',
                                            'uploads/produits/',
                                        ];
                                        $extensions = ['', '.jpeg', '.jpg', '.png', '.gif', '.webp'];
                                        foreach ($dossiers as $dossier) {
                                            foreach ($extensions as $ext) {
                                                $test_path = $dossier . $image_name . $ext;
                                                if (file_exists($test_path)) {
                                                    $image_path = $test_path;
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                    if (empty($image_path)) {
                                        $image_path = 'https://placehold.co/50x50/F5F5F5/C8922A?text=' . urlencode(substr($d['produit_nom'] ?? 'P', 0, 1));
                                    }
                                    
                                    $couleur_nom = $d['couleur_nom_complet'] ?? $d['couleur_nom'] ?? $d['couleur'] ?? null;
                                    $taille_nom = $d['taille_nom_complet'] ?? $d['taille_nom'] ?? $d['taille'] ?? null;
                                    $couleur_code = $d['couleur_code'] ?? null;
                                    
                                    $has_couleur = !empty($couleur_nom);
                                    $has_taille = !empty($taille_nom);
                                    $total_ligne = ($d['prix_unitaire'] ?? 0) * ($d['quantite'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:12px;">
                                            <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($d['produit_nom'] ?? '') ?>" style="width:50px;height:50px;border-radius:8px;object-fit:cover;background:#F5F3F0;border:1px solid #EEEAE5;flex-shrink:0;" onerror="this.src='https://placehold.co/50x50/F5F5F5/C8922A?text=<?= urlencode(substr($d['produit_nom'] ?? 'P', 0, 1)) ?>'">
                                            <div>
                                                <div style="font-weight:600;color:#1A1A1A;font-size:0.9rem;"><?= htmlspecialchars($d['produit_nom'] ?? $d['nom_produit'] ?? 'Produit inconnu') ?></div>
                                                <div style="font-size:0.65rem;color:#8A99AA;">Réf: #<?= $d['produit_id'] ?? '' ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align:center;font-weight:600;"><?= $d['quantite'] ?></td>
                                    <td style="text-align:center;">
                                        <?php if($has_couleur): ?>
                                            <span style="display:inline-block;padding:3px 12px 3px 8px;border-radius:20px;font-size:0.7rem;font-weight:500;border:1px solid #E8ECF0;background:#F8F9FA;">
                                                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:6px;vertical-align:middle;border:1px solid rgba(0,0,0,0.1);background-color: <?= htmlspecialchars($couleur_code ?? '#CCCCCC') ?>;"></span>
                                                <?= htmlspecialchars($couleur_nom) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#8A99AA;font-size:0.7rem;">Non spécifiée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if($has_taille): ?>
                                            <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;background:#E8ECF0;color:#0D0D0D;border:1px solid #D5D8DD;">
                                                <i class="bi bi-rulers" style="margin-right:4px;font-size:0.6rem;"></i> <?= htmlspecialchars($taille_nom) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#8A99AA;font-size:0.7rem;">Non spécifiée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;"><?= number_format($d['prix_unitaire'], 0, ',', ' ') ?> F</td>
                                    <td style="text-align:right;font-weight:600;color:#C8922A;">
                                        <?= number_format($total_ligne, 0, ',', ' ') ?> F
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background:#FEFBF5;font-weight:700;">
                                <td colspan="5" style="text-align:right;color:#1A2C3E;">TOTAL</td>
                                <td style="text-align:right;font-size:1.1rem;color:#C8922A;">
                                    <strong><?= number_format($commande['total'], 0, ',', ' ') ?> F</strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== ACTIONS ===== -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:8px;">
            <a href="commandes.php" class="btn-admin btn-outline">
                <i class="bi bi-arrow-left"></i> Retour aux commandes
            </a>
            
            <?php if($commande['statut'] != 'livree' && $commande['statut'] != 'annulee'): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="?id=<?= $commande_id ?>&changer_statut=1&statut=en_attente" class="btn-small gray">📋 En attente</a>
                    <a href="?id=<?= $commande_id ?>&changer_statut=1&statut=confirmee" class="btn-small green">✅ Confirmée</a>
                    <a href="?id=<?= $commande_id ?>&changer_statut=1&statut=en_preparation" class="btn-small blue">🔧 Préparation</a>
                    <a href="?id=<?= $commande_id ?>&changer_statut=1&statut=en_livraison" class="btn-small or">🚚 Livraison</a>
                    <a href="?id=<?= $commande_id ?>&changer_statut=1&statut=livree" class="btn-small green">📦 Livrée</a>
                    <a href="?id=<?= $commande_id ?>&changer_statut=1&statut=annulee" class="btn-small red" onclick="return confirm('Annuler cette commande ?')">❌ Annuler</a>
                </div>
            <?php else: ?>
                <span style="font-weight:600;color:<?= $commande['statut'] == 'livree' ? '#27AE60' : '#E74C3C' ?>;">
                    <i class="bi bi-<?= $commande['statut'] == 'livree' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                    Commande <?= $commande['statut'] == 'livree' ? 'livrée' : 'annulée' ?>
                </span>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>