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

// Vérification des permissions
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
    $produits = $_POST['produits'] ?? [];
    $quantites = $_POST['quantites'] ?? [];
    $prix_unitaires = $_POST['prix_unitaires'] ?? [];
    $fournisseur_nom = trim($_POST['fournisseur_nom'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $date_achat = $_POST['date_achat'] ?? date('Y-m-d');
    
    $has_valid_product = false;
    $total_achat = 0;
    $lignes_achat = [];
    
    $nb_produits = count($produits);
    for ($i = 0; $i < $nb_produits; $i++) {
        $produit_id = (int)($produits[$i] ?? 0);
        $quantite = (int)($quantites[$i] ?? 0);
        $prix_unitaire = (float)($prix_unitaires[$i] ?? 0);
        
        if ($produit_id > 0 && $quantite > 0 && $prix_unitaire > 0) {
            $has_valid_product = true;
            $lignes_achat[] = [
                'produit_id' => $produit_id,
                'quantite' => $quantite,
                'prix_unitaire' => $prix_unitaire,
                'total_ligne' => $quantite * $prix_unitaire
            ];
            $total_achat += $quantite * $prix_unitaire;
        }
    }
    
    if ($has_valid_product && !empty($fournisseur_nom)) {
        $numero_achat = 'ACH-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        // Insérer l'achat
        $stmt = $pdo->prepare("
            INSERT INTO achats (
                numero_achat, nom_fournisseur, 
                total_ligne, notes, date_achat
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $numero_achat, 
            $fournisseur_nom,
            $total_achat,
            $notes,
            $date_achat
        ]);
        
        $achat_id = $pdo->lastInsertId();
        
        // Mettre à jour le prix d'achat et le stock pour chaque produit
        foreach ($lignes_achat as $ligne) {
            // Mettre à jour le stock
            $pdo->prepare("UPDATE produits SET stock = stock + ? WHERE id = ?")
                ->execute([$ligne['quantite'], $ligne['produit_id']]);
            
            // Mettre à jour le prix d'achat (moyenne pondérée)
            $stmt = $pdo->prepare("SELECT stock, prix_achat FROM produits WHERE id = ?");
            $stmt->execute([$ligne['produit_id']]);
            $prod = $stmt->fetch();
            
            if ($prod) {
                $stock_actuel = $prod['stock'];
                $prix_achat_actuel = $prod['prix_achat'] ?? 0;
                
                // Calcul du nouveau prix d'achat moyen
                $nouveau_prix_achat = ($stock_actuel > 0) 
                    ? (($prix_achat_actuel * ($stock_actuel - $ligne['quantite']) + $ligne['prix_unitaire'] * $ligne['quantite']) / $stock_actuel)
                    : $ligne['prix_unitaire'];
                
                $pdo->prepare("UPDATE produits SET prix_achat = ? WHERE id = ?")
                    ->execute([$nouveau_prix_achat, $ligne['produit_id']]);
            }
        }
        
        $_SESSION['message_achat'] = 'Achat enregistré avec succès ! (' . count($lignes_achat) . ' produit(s))';
        header('Location: achats.php');
        exit;
    } else {
        $_SESSION['message_achat'] = 'Veuillez remplir tous les champs et ajouter au moins un produit.';
    }
}

// ============================================
// SUPPRIMER UN ACHAT
// ============================================
if (isset($_GET['supprimer'])) {
    $id = (int)$_GET['supprimer'];
    
    // Récupérer les détails de l'achat
    $stmt = $pdo->prepare("SELECT produit_id, quantite, prix_unitaire FROM achats WHERE id = ?");
    $stmt->execute([$id]);
    $achat = $stmt->fetch();
    
    if ($achat) {
        // Ajuster le stock
        $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?")
            ->execute([$achat['quantite'], $achat['produit_id']]);
    }
    
    $pdo->prepare("DELETE FROM achats WHERE id = ?")->execute([$id]);
    
    $_SESSION['message_achat'] = 'Achat supprimé avec succès !';
    header('Location: achats.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================

$produits = $pdo->query("SELECT * FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

// Statistiques
$total_achats = $pdo->query("SELECT COUNT(*) FROM achats")->fetchColumn();
$total_depenses = $pdo->query("SELECT COALESCE(SUM(total_ligne), 0) FROM achats")->fetchColumn();
$total_articles_achetes = $pdo->query("SELECT COALESCE(SUM(quantite), 0) FROM achats")->fetchColumn();
$achats_mois = $pdo->query("
    SELECT COALESCE(SUM(total_ligne), 0) FROM achats 
    WHERE MONTH(date_achat) = MONTH(CURDATE()) AND YEAR(date_achat) = YEAR(CURDATE())
")->fetchColumn();

// Liste des achats
$achats = $pdo->query("
    SELECT * FROM achats 
    ORDER BY date_achat DESC, id DESC
")->fetchAll();

// Calcul des marges par produit
$produits_avec_marge = [];
try {
    $stmt = $pdo->query("
        SELECT 
            id, nom, 
            prix_achat, 
            prix, 
            (prix - prix_achat) as marge, 
            CASE 
                WHEN prix_achat > 0 THEN ROUND(((prix - prix_achat) / prix_achat) * 100, 2)
                ELSE 0 
            END as marge_pourcentage,
            stock
        FROM produits 
        WHERE est_visible = 1 
        AND prix_achat > 0 
        ORDER BY marge DESC
        LIMIT 10
    ");
    $produits_avec_marge = $stmt->fetchAll();
} catch(PDOException $e) {
    $produits_avec_marge = [];
}

// Statistiques de marge
$total_marge_potentielle = 0;
$total_prix_achat = 0;
$total_prix_vente = 0;
$nb_produits_avec_marge = 0;

try {
    $stmt = $pdo->query("
        SELECT 
            SUM((prix - prix_achat) * stock) as marge_totale,
            SUM(prix_achat * stock) as total_achat,
            SUM(prix * stock) as total_vente,
            COUNT(*) as nb_produits
        FROM produits 
        WHERE est_visible = 1 AND prix_achat > 0
    ");
    $stats_marge = $stmt->fetch();
    if ($stats_marge) {
        $total_marge_potentielle = $stats_marge['marge_totale'] ?? 0;
        $total_prix_achat = $stats_marge['total_achat'] ?? 0;
        $total_prix_vente = $stats_marge['total_vente'] ?? 0;
        $nb_produits_avec_marge = $stats_marge['nb_produits'] ?? 0;
    }
} catch(PDOException $e) {
    $total_marge_potentielle = 0;
}

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

        <!-- ===== STATISTIQUES ACHATS ===== -->
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

        <!-- ===== STATISTIQUES MARGE ===== -->
        <div class="stats-row">
            <div class="stat-box" style="border-left: 4px solid #27AE60;">
                <div class="stat-icon ic-green"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="stat-val" style="color:#27AE60;"><?= number_format($total_marge_potentielle, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Marge brute potentielle</div>
                </div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #2980B9;">
                <div class="stat-icon ic-blue"><i class="bi bi-cart"></i></div>
                <div>
                    <div class="stat-val" style="color:#2980B9;"><?= number_format($total_prix_vente, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Valeur stock (vente)</div>
                </div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #E67E22;">
                <div class="stat-icon ic-or"><i class="bi bi-bag"></i></div>
                <div>
                    <div class="stat-val" style="color:#E67E22;"><?= number_format($total_prix_achat, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">Valeur stock (achat)</div>
                </div>
            </div>
            <div class="stat-box" style="border-left: 4px solid #8E44AD;">
                <div class="stat-icon ic-purple"><i class="bi bi-percent"></i></div>
                <div>
                    <div class="stat-val" style="color:#8E44AD;">
                        <?= $total_prix_achat > 0 ? round(($total_marge_potentielle / $total_prix_achat) * 100, 1) : 0 ?>%
                    </div>
                    <div class="stat-lbl">Marge brute moyenne</div>
                </div>
            </div>
        </div>

        <!-- ===== FORMULAIRE D'ACHAT ===== -->
        <div class="card-white">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-plus-circle"></i> Enregistrer un achat</div>
                <button type="button" class="btn-admin btn-sm" onclick="ajouterLigne()" style="background:#C8922A;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;">
                    <i class="bi bi-plus-lg"></i> Ajouter un produit
                </button>
            </div>
            <div class="card-body">
                <form method="POST" id="achatForm">
                    <!-- Informations générales -->
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:15px;margin-bottom:20px;">
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Nom du fournisseur <span style="color:red;">*</span></label>
                            <input type="text" name="fournisseur_nom" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;" placeholder="Nom du fournisseur" required>
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Date d'achat</label>
                            <input type="date" name="date_achat" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:0.75rem;font-weight:600;color:#666;margin-bottom:5px;">Notes</label>
                            <input type="text" name="notes" class="form-control" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.9rem;" placeholder="Notes...">
                        </div>
                    </div>

                    <!-- Lignes de produits -->
                    <div id="lignesContainer">
                        <div class="ligne-produit" data-index="0">
                            <div style="display:grid;grid-template-columns:3fr 1fr 1fr 0.5fr;gap:12px;align-items:end;padding:12px;background:#F8F9FA;border-radius:10px;margin-bottom:10px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="display:block;font-size:0.7rem;font-weight:600;color:#666;margin-bottom:4px;">Produit</label>
                                    <select name="produits[]" class="form-control produit-select" style="width:100%;padding:8px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.85rem;" required>
                                        <option value="">Sélectionner</option>
                                        <?php foreach($produits as $p): ?>
                                            <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-prix-vente="<?= $p['prix'] ?>">
                                                <?= htmlspecialchars($p['nom']) ?> 
                                                (Stock: <?= $p['stock'] ?> | Vente: <?= number_format($p['prix'], 0, ',', ' ') ?> F)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="display:block;font-size:0.7rem;font-weight:600;color:#666;margin-bottom:4px;">Quantité</label>
                                    <input type="number" name="quantites[]" class="form-control qte-input" style="width:100%;padding:8px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.85rem;" min="1" value="1" required oninput="calculerTotalLigne(this)">
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="display:block;font-size:0.7rem;font-weight:600;color:#666;margin-bottom:4px;">Prix unitaire (FCFA)</label>
                                    <input type="number" name="prix_unitaires[]" class="form-control prix-input" style="width:100%;padding:8px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.85rem;" min="1" value="0" required oninput="calculerTotalLigne(this)">
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;padding-bottom:4px;">
                                    <button type="button" class="btn-remove" onclick="supprimerLigne(this)" style="background:transparent;border:none;color:#E74C3C;font-size:1.2rem;cursor:pointer;" title="Supprimer cette ligne">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                    <span class="ligne-total" style="font-weight:700;color:#C8922A;font-size:0.85rem;min-width:70px;">0 F</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total -->
                    <div style="display:flex;justify-content:flex-end;align-items:center;gap:20px;margin-top:15px;">
                        <div style="font-size:1.1rem;font-weight:600;">
                            Total: <span id="totalAchat" style="color:#C8922A;font-size:1.4rem;">0</span> FCFA
                        </div>
                        <button type="submit" name="ajouter_achat" class="btn-admin btn-success" style="padding:12px 30px;font-size:1rem;">
                            <i class="bi bi-save"></i> Enregistrer l'achat
                        </button>
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
                                <th>Prix achat</th>
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
                                    <td><?= htmlspecialchars($a['nom_produit'] ?? '-') ?></td>
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

        <!-- ===== TOP PRODUITS PAR MARGE ===== -->
        <div class="card-white" style="margin-top:20px;">
            <div class="card-header">
                <div class="card-title"><i class="bi bi-graph-up-arrow"></i> Top produits par marge</div>
                <div class="text-muted" style="font-size:0.8rem;"><?= count($produits_avec_marge) ?> produits avec marge</div>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if(empty($produits_avec_marge)): ?>
                    <div class="empty-state">
                        <i class="bi bi-percent"></i>
                        <p>Aucune donnée de marge disponible. Commencez par enregistrer des achats.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table-achats">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Prix Achat</th>
                                    <th>Prix Vente</th>
                                    <th>Marge (FCFA)</th>
                                    <th>Marge (%)</th>
                                    <th>Stock</th>
                                    <th>Marge potentielle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($produits_avec_marge as $p): 
                                    $marge_potentielle = $p['marge'] * $p['stock'];
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                                    <td><?= number_format($p['prix_achat'], 0, ',', ' ') ?> F</td>
                                    <td><?= number_format($p['prix'], 0, ',', ' ') ?> F</td>
                                    <td style="color:#27AE60;font-weight:600;"><?= number_format($p['marge'], 0, ',', ' ') ?> F</td>
                                    <td>
                                        <span style="background:<?= $p['marge_pourcentage'] > 50 ? '#27AE60' : ($p['marge_pourcentage'] > 30 ? '#F39C12' : '#E74C3C') ?>;color:#fff;padding:2px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;">
                                            <?= $p['marge_pourcentage'] ?>%
                                        </span>
                                    </td>
                                    <td><?= $p['stock'] ?></td>
                                    <td style="color:#2980B9;font-weight:600;"><?= number_format($marge_potentielle, 0, ',', ' ') ?> F</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     SCRIPTS
     ============================================ -->
<script>
let ligneIndex = 1;

function ajouterLigne() {
    const container = document.getElementById('lignesContainer');
    const template = container.querySelector('.ligne-produit');
    const newLigne = template.cloneNode(true);
    
    const selects = newLigne.querySelectorAll('select');
    const inputs = newLigne.querySelectorAll('input');
    selects.forEach(s => s.value = '');
    inputs.forEach(i => i.value = i.type === 'number' ? 1 : '');
    
    container.appendChild(newLigne);
    ligneIndex++;
    
    newLigne.querySelectorAll('.prix-input').forEach(inp => {
        inp.oninput = function() { calculerTotalLigne(this); };
    });
    newLigne.querySelectorAll('.qte-input').forEach(inp => {
        inp.oninput = function() { 
            const parent = this.closest('.ligne-produit');
            const prix = parent.querySelector('.prix-input');
            if (prix) calculerTotalLigne(prix);
        };
    });
    
    calculerTotalGeneral();
}

function supprimerLigne(btn) {
    const ligne = btn.closest('.ligne-produit');
    const container = document.getElementById('lignesContainer');
    if (container.querySelectorAll('.ligne-produit').length > 1) {
        ligne.remove();
        calculerTotalGeneral();
    } else {
        alert('Vous devez conserver au moins une ligne de produit.');
    }
}

function calculerTotalLigne(element) {
    const ligne = element.closest('.ligne-produit');
    const qte = parseInt(ligne.querySelector('.qte-input').value) || 0;
    const prix = parseFloat(ligne.querySelector('.prix-input').value) || 0;
    const total = qte * prix;
    
    const totalSpan = ligne.querySelector('.ligne-total');
    if (totalSpan) {
        totalSpan.textContent = total.toLocaleString('fr-FR') + ' F';
    }
    
    calculerTotalGeneral();
}

function calculerTotalGeneral() {
    const totals = document.querySelectorAll('.ligne-total');
    let totalGeneral = 0;
    totals.forEach(span => {
        const val = parseFloat(span.textContent.replace(/\s/g, '').replace('F', '')) || 0;
        totalGeneral += val;
    });
    
    document.getElementById('totalAchat').textContent = totalGeneral.toLocaleString('fr-FR');
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.prix-input').forEach(inp => {
        calculerTotalLigne(inp);
    });
    calculerTotalGeneral();
});
</script>

<!-- ============================================
     STYLES SUPPLÉMENTAIRES
     ============================================ -->
<style>
.btn-remove:hover {
    transform: scale(1.2);
}
.ligne-produit {
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert-success {
    background: #D4EDDA;
    color: #155724;
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid #28A745;
}
.btn-admin.btn-sm {
    padding: 6px 14px;
    font-size: 0.8rem;
    border-radius: 6px;
    background: #C8922A;
    color: #fff;
    border: none;
    cursor: pointer;
}
.btn-admin.btn-sm:hover {
    background: #9A6E1A;
}
.btn-small.red {
    color: #E74C3C;
    padding: 4px 8px;
    border-radius: 4px;
    text-decoration: none;
}
.btn-small.red:hover {
    background: #FEE;
}
.stat-icon.ic-purple {
    background: rgba(142,68,173,0.12);
    color: #8E44AD;
}
.table-achats td {
    padding: 10px 12px;
    border-bottom: 1px solid #F0F0F0;
    font-size: 0.85rem;
}
.table-achats th {
    padding: 10px 12px;
    background: #F8F9FA;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
    border-bottom: 2px solid #E0E0E0;
}
</style>

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>