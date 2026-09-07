<?php
// ============================================
// POINT DE VENTE - ADMIN AWA KA SUGU
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

// Vérification des permissions (Point de vente visible pour super_admin, directeur et admin)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur' && $admin_role !== 'admin') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

$page_title = 'Point de Vente';

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

// ============================================
// RÉCUPÉRER LES PRODUITS
// ============================================
$produits = $pdo->query("SELECT * FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

// ============================================
// TRAITEMENT DE LA VENTE
// ============================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_vente'])) {
    $client_nom = trim($_POST['client_nom'] ?? '');
    $client_telephone = trim($_POST['client_telephone'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $mode_paiement = $_POST['mode_paiement'] ?? 'especes';
    $montant_recu = isset($_POST['montant_recu']) ? (float)$_POST['montant_recu'] : 0;
    $notes = trim($_POST['notes'] ?? '');
    $produits_quantites = $_POST['quantites'] ?? [];
    $total = 0;
    $details = [];
    
    foreach ($produits_quantites as $id => $qte) {
        $qte = (int)$qte;
        if ($qte > 0) {
            $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if ($p) {
                $prix = $p['prix_promo'] ?: $p['prix'];
                $total += $prix * $qte;
                $details[] = [
                    'id' => $p['id'],
                    'nom' => $p['nom'],
                    'prix' => $prix,
                    'qte' => $qte,
                    'total' => $prix * $qte
                ];
            }
        }
    }
    
    if (empty($client_nom)) {
        $error = 'Veuillez entrer le nom du client.';
    } elseif (empty($details)) {
        $error = 'Veuillez sélectionner au moins un produit.';
    } elseif ($mode_paiement == 'especes' && $montant_recu < $total) {
        $error = 'Le montant reçu est inférieur au total de la vente.';
    } else {
        $numero_vente = 'POS-' . date('Ymd') . '-' . strtoupper(uniqid());
        $monnaie_rendue = ($mode_paiement == 'especes') ? $montant_recu - $total : 0;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ventes_boutique (
                    numero_vente, client_nom, client_telephone, client_email,
                    total, montant_recu, monnaie_rendue, mode_paiement, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $numero_vente, $client_nom, $client_telephone, $client_email,
                $total, $montant_recu, $monnaie_rendue, $mode_paiement, $notes
            ]);
            
            $vente_id = $pdo->lastInsertId();
            
            foreach ($details as $d) {
                $stmt = $pdo->prepare("
                    INSERT INTO details_ventes (vente_id, produit_id, nom_produit, prix_unitaire, quantite, total_ligne)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$vente_id, $d['id'], $d['nom'], $d['prix'], $d['qte'], $d['total']]);
                
                $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?")->execute([$d['qte'], $d['id']]);
            }
            
            $_SESSION['message_vente'] = 'Vente enregistrée avec succès ! N° ' . $numero_vente;
            header('Location: point_de_vente.php');
            exit;
        } catch(PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

// ============================================
// RÉCUPÉRER LES DERNIÈRES VENTES
// ============================================
$dernieres_ventes = $pdo->query("SELECT * FROM ventes_boutique ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Statistiques du jour
$ventes_aujourdhui = $pdo->query("
    SELECT COUNT(*) as nb, COALESCE(SUM(total), 0) as total 
    FROM ventes_boutique 
    WHERE DATE(created_at) = CURDATE()
")->fetch();

$message = $_SESSION['message_vente'] ?? '';
unset($_SESSION['message_vente']);

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
            <div class="topbar-title">💰 Point de <span>Vente</span></div>
            <div class="topbar-breadcrumb">Administration → Point de vente → Enregistrement</div>
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
            <div class="alert-success">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?>
                <a href="point_de_vente.php" class="btn-admin btn-primary" style="float:right;padding:5px 15px;font-size:0.7rem;background:#C8922A;color:#fff;border-color:#C8922A;border-radius:6px;text-decoration:none;">Nouvelle vente</a>
            </div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES DU JOUR ===== -->
        <div class="stats-row" style="grid-template-columns: repeat(2, 1fr);">
            <div class="stat-box">
                <div class="stat-icon ic-or"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="stat-val"><?= $ventes_aujourdhui['nb'] ?? 0 ?></div>
                    <div class="stat-lbl">Ventes aujourd'hui</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon ic-green"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($ventes_aujourdhui['total'] ?? 0, 0, ',', ' ') ?> F</div>
                    <div class="stat-lbl">CA du jour</div>
                </div>
            </div>
        </div>

        <!-- ===== POINT DE VENTE ===== -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;">
            
            <!-- PARTIE GAUCHE : SÉLECTION DES PRODUITS -->
            <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E8ECF0;">
                <h3 style="font-size:1.1rem;margin-bottom:16px;color:#0D0D0D;">
                    <i class="bi bi-cart-plus" style="color:#C8922A;"></i> Ajouter des produits
                </h3>
                
                <div style="margin-bottom:15px;">
                    <select id="produitSelect" style="width:100%;padding:12px 15px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:0.95rem;font-family:'Jost',sans-serif;">
                        <option value="">-- Sélectionner un produit --</option>
                        <?php foreach($produits as $p): ?>
                            <option value="<?= $p['id'] ?>" data-prix="<?= $p['prix'] ?>" data-prixpromo="<?= $p['prix_promo'] ?>" data-stock="<?= $p['stock'] ?>">
                                <?= htmlspecialchars($p['nom']) ?> - <?= number_format($p['prix'], 0, ',', ' ') ?> F
                                <?php if($p['prix_promo']): ?> (Promo: <?= number_format($p['prix_promo'], 0, ',', ' ') ?> F)<?php endif; ?>
                                (Stock: <?= $p['stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="produitsSelectionnes" style="text-align:center;padding:20px;color:#8A99AA;">
                    Aucun produit sélectionné
                </div>
            </div>

            <!-- PARTIE DROITE : INFORMATIONS CLIENT ET TOTAL -->
            <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E8ECF0;">
                <h3 style="font-size:1.1rem;margin-bottom:16px;color:#0D0D0D;">
                    <i class="bi bi-person" style="color:#C8922A;"></i> Informations client
                </h3>
                
                <form method="POST" id="venteForm">
                    <div style="margin-bottom:15px;">
                        <input type="text" name="client_nom" placeholder="Nom du client *" required style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;margin-bottom:10px;font-family:'Jost',sans-serif;">
                        <input type="tel" name="client_telephone" placeholder="Téléphone" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;margin-bottom:10px;font-family:'Jost',sans-serif;">
                        <input type="email" name="client_email" placeholder="Email" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;">
                    </div>

                    <div id="panierRecap" style="background:#F8F9FA;border-radius:8px;padding:12px;margin-bottom:15px;">
                        <div style="font-size:0.85rem;color:#8A99AA;text-align:center;">Aucun produit sélectionné</div>
                    </div>

                    <div style="background:#FEFBF5;border-radius:10px;padding:15px;margin:15px 0;border:1px solid rgba(200,146,42,0.15);">
                        <div style="font-size:0.85rem;color:#8A99AA;">Total à payer</div>
                        <div style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:#C8922A;" id="totalDisplay">0 F</div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:15px 0;">
                        <select name="mode_paiement" id="modePaiement" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;">
                            <option value="especes">💰 Espèces</option>
                            <option value="orange_money">📱 Orange Money</option>
                            <option value="wave">🌊 Wave</option>
                            <option value="moov_money">📱 Moov Money</option>
                            <option value="carte">💳 Carte bancaire</option>
                        </select>
                        <input type="number" name="montant_recu" id="montantRecu" placeholder="Montant reçu" step="100" min="0" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;">
                    </div>

                    <div style="margin:10px 0;">
                        <input type="text" name="notes" placeholder="Notes (optionnel)" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;">
                    </div>

                    <button type="submit" name="enregistrer_vente" class="btn-admin btn-primary" style="width:100%;padding:14px;font-size:1rem;font-weight:700;border-radius:8px;display:flex;align-items:center;justify-content:center;gap:10px;">
                        <i class="bi bi-check-circle"></i> Enregistrer la vente
                    </button>
                </form>
            </div>
        </div>

        <!-- ===== DERNIÈRES VENTES ===== -->
        <div style="margin-top:30px;background:#fff;border-radius:12px;padding:20px;border:1px solid #E8ECF0;">
            <h3 style="font-size:1.1rem;margin-bottom:15px;color:#0D0D0D;">
                <i class="bi bi-clock-history" style="color:#C8922A;"></i> Dernières ventes en boutique
            </h3>
            <?php if(empty($dernieres_ventes)): ?>
                <div style="text-align:center;padding:30px;color:#8A99AA;">
                    <i class="bi bi-cart-x" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                    <p>Aucune vente enregistrée en boutique</p>
                </div>
            <?php else: ?>
                <?php foreach($dernieres_ventes as $v): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #F0F2F5;">
                    <div>
                        <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($v['numero_vente']) ?></div>
                        <div style="font-size:0.75rem;color:#8A99AA;"><?= htmlspecialchars($v['client_nom']) ?> • <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></div>
                    </div>
                    <div style="color:#C8922A;font-weight:700;font-size:1rem;"><?= number_format($v['total'], 0, ',', ' ') ?> F</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================
     FOOTER
     ============================================ -->
<?php include 'includes/footer.php'; ?>

<script>
// ============================================
// GESTION DU PANIER
// ============================================
let produitsSelectionnes = {};

document.getElementById('produitSelect').addEventListener('change', function() {
    const select = this;
    const id = select.value;
    if (!id) return;
    
    const option = select.options[select.selectedIndex];
    const nom = option.text.split(' - ')[0];
    const prix = parseFloat(option.dataset.prixpromo) || parseFloat(option.dataset.prix);
    const stock = parseInt(option.dataset.stock);
    
    if (produitsSelectionnes[id]) {
        produitsSelectionnes[id].quantite++;
    } else {
        produitsSelectionnes[id] = {
            id: id,
            nom: nom,
            prix: prix,
            quantite: 1,
            stock: stock
        };
    }
    
    afficherPanier();
    select.value = '';
});

function afficherPanier() {
    const container = document.getElementById('produitsSelectionnes');
    const recap = document.getElementById('panierRecap');
    let html = '';
    let total = 0;
    
    const ids = Object.keys(produitsSelectionnes);
    if (ids.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:20px;color:#8A99AA;">Aucun produit sélectionné</div>';
        recap.innerHTML = '<div style="font-size:0.85rem;color:#8A99AA;text-align:center;">Aucun produit sélectionné</div>';
        document.getElementById('totalDisplay').innerText = '0 F';
        return;
    }
    
    ids.forEach(id => {
        const p = produitsSelectionnes[id];
        const ligneTotal = p.prix * p.quantite;
        total += ligneTotal;
        
        html += `
            <div style="display:grid;grid-template-columns:3fr 1fr 1fr;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid #F0F2F5;">
                <span style="font-weight:500;font-size:0.9rem;">${p.nom}</span>
                <span style="color:#C8922A;font-weight:600;">${p.prix.toLocaleString()} F</span>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" value="${p.quantite}" min="1" max="${p.stock}" 
                           data-id="${id}" class="qty-input" style="width:50px;padding:6px;border:1.5px solid #E0E0E0;border-radius:6px;text-align:center;">
                    <button type="button" class="remove-btn" data-id="${id}" style="background:none;border:none;color:#E74C3C;cursor:pointer;font-size:1.2rem;">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Recap pour le panier
    let recapHtml = '';
    ids.forEach(id => {
        const p = produitsSelectionnes[id];
        const ligneTotal = p.prix * p.quantite;
        recapHtml += `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F0F2F5;font-size:0.85rem;">
            <span>${p.nom} × ${p.quantite}</span>
            <span style="color:#C8922A;">${ligneTotal.toLocaleString()} F</span>
        </div>`;
    });
    recapHtml += `<div style="display:flex;justify-content:space-between;padding:10px 0;font-weight:700;font-size:1rem;">
        <span>TOTAL</span>
        <span style="color:#C8922A;">${total.toLocaleString()} F</span>
    </div>`;
    recap.innerHTML = recapHtml;
    
    document.getElementById('totalDisplay').innerText = total.toLocaleString() + ' F';
    
    // Événements pour les quantités
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const id = this.dataset.id;
            const qte = parseInt(this.value) || 1;
            produitsSelectionnes[id].quantite = qte;
            afficherPanier();
        });
    });
    
    // Événements pour la suppression
    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            delete produitsSelectionnes[id];
            afficherPanier();
        });
    });
}

// ============================================
// SOUMISSION DU FORMULAIRE
// ============================================
document.getElementById('venteForm').addEventListener('submit', function(e) {
    const ids = Object.keys(produitsSelectionnes);
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `quantites[${id}]`;
        input.value = produitsSelectionnes[id].quantite;
        this.appendChild(input);
    });
    
    if (ids.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un produit.');
    }
});
</script>