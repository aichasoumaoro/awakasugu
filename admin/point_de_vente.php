<?php
// ============================================
// SESSION ADMIN SÉPARÉE
// ============================================
require_once 'session_config.php';

if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

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

// Récupérer tous les produits actifs
$produits = $pdo->query("SELECT * FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

// Traitement de la vente
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
                // Utiliser le prix promo si disponible
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
        // Générer le numéro de vente
        $numero_vente = 'POS-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        // Calculer la monnaie rendue
        $monnaie_rendue = ($mode_paiement == 'especes') ? $montant_recu - $total : 0;
        
        try {
            // Insérer la vente
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
            
            // Insérer les détails et mettre à jour le stock
            foreach ($details as $d) {
                $stmt = $pdo->prepare("
                    INSERT INTO details_ventes (vente_id, produit_id, nom_produit, prix_unitaire, quantite, total_ligne)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$vente_id, $d['id'], $d['nom'], $d['prix'], $d['qte'], $d['total']]);
                
                // Mettre à jour le stock
                $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?")->execute([$d['qte'], $d['id']]);
            }
            
            // Rediriger avec succès
            header('Location: point_de_vente.php?success=1&vente=' . $numero_vente);
            exit;
        } catch(PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

// Récupérer les dernières ventes
$dernieres_ventes = $pdo->query("SELECT * FROM ventes_boutique ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point de Vente - Admin Awa Ka Sugu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Jost', sans-serif; background: #F5F7FA; color: #1A2C3E; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            background: #0D0D0D;
            border-right: 1px solid rgba(200,146,42,0.18);
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(200,146,42,0.12);
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #C8922A;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.2);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }
        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
            padding: 10px 12px;
            background: rgba(200,146,42,0.07);
            border-radius: 8px;
            border: 1px solid rgba(200,146,42,0.12);
        }
        .admin-avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, #C8922A, #E2B96A);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: #fff; font-weight: 600;
            flex-shrink: 0;
        }
        .admin-name { font-size: 0.82rem; color: #fff; font-weight: 500; }
        .admin-role { font-size: 0.62rem; color: rgba(255,255,255,0.3); letter-spacing: 1px; text-transform: uppercase; }
        .nav-section {
            font-size: 0.58rem;
            color: rgba(255,255,255,0.18);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            padding: 18px 24px 6px;
        }
        .sidebar nav { flex: 1; padding: 8px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,0.48);
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 500;
            border-left: 2px solid transparent;
            transition: all 0.22s;
            margin-bottom: 2px;
        }
        .nav-item i { font-size: 1rem; width: 18px; text-align: center; }
        .nav-item:hover { color: #fff; background: rgba(200,146,42,0.08); border-left-color: rgba(200,146,42,0.4); }
        .nav-item.active { color: #fff; background: rgba(200,146,42,0.12); border-left-color: #C8922A; }
        .nav-item.active i { color: #C8922A; }
        .nav-item.logout { color: rgba(231,76,60,0.6); }
        .nav-item.logout:hover { color: #E74C3C; background: rgba(231,76,60,0.08); border-left-color: #E74C3C; }

        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #F5F7FA;
            min-height: 100vh;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #E8ECF0;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0D0D0D;
        }
        .topbar-title span { color: #C8922A; }
        .topbar-breadcrumb { font-size: 0.75rem; color: #999; margin-top: 2px; }
        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'Jost', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 9px 20px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.22s;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }
        .btn-or { background: #C8922A; color: #fff; }
        .btn-or:hover { background: #9A6E1A; color: #fff; transform: translateY(-1px); }
        .btn-outline { border: 1.5px solid #E0E0E0; color: #555; background: #fff; }
        .btn-outline:hover { border-color: #C8922A; color: #C8922A; }
        .btn-success { background: #27AE60; color: #fff; }
        .btn-success:hover { background: #1A7A4A; color: #fff; }
        .btn-danger { background: #E74C3C; color: #fff; }
        .btn-danger:hover { background: #C0392B; color: #fff; }

        .content { padding: 28px 32px; flex: 1; }

        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #F8D7DA;
            border-left: 4px solid #E74C3C;
            color: #721C24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .pos-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .pos-left {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #E8ECF0;
        }
        .pos-left h3 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: #0D0D0D;
        }

        .product-select {
            margin-bottom: 15px;
        }
        .product-select select {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #E0E0E0;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: 'Jost', sans-serif;
        }
        .product-select select:focus { outline: none; border-color: #C8922A; }

        .product-row {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr;
            gap: 10px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F0F2F5;
        }
        .product-row .prod-name { font-weight: 500; font-size: 0.9rem; }
        .product-row .prod-price { color: #C8922A; font-weight: 600; }
        .product-row input[type="number"] {
            width: 60px;
            padding: 6px;
            border: 1.5px solid #E0E0E0;
            border-radius: 6px;
            text-align: center;
        }
        .product-row .remove-btn {
            background: none;
            border: none;
            color: #E74C3C;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .pos-right {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #E8ECF0;
        }
        .pos-right h3 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: #0D0D0D;
        }

        .client-info input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            margin-bottom: 10px;
            font-family: 'Jost', sans-serif;
        }
        .client-info input:focus { outline: none; border-color: #C8922A; }

        .total-box {
            background: #FEFBF5;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            border: 1px solid rgba(200,146,42,0.15);
        }
        .total-box .total-label { font-size: 0.85rem; color: #8A99AA; }
        .total-box .total-amount {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #C8922A;
        }

        .payment-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        .payment-row select, .payment-row input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            font-family: 'Jost', sans-serif;
        }
        .payment-row select:focus, .payment-row input:focus { outline: none; border-color: #C8922A; }

        .btn-submit {
            width: 100%;
            padding: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #C8922A, #E8B55A);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover { background: #9A6E1A; transform: translateY(-2px); box-shadow: 0 5px 20px rgba(200,146,42,0.3); }

        .ventes-recentes {
            margin-top: 30px;
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #E8ECF0;
        }
        .ventes-recentes h3 {
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .vente-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #F0F2F5;
        }
        .vente-item:last-child { border-bottom: none; }
        .vente-info .vente-numero { font-weight: 600; font-size: 0.85rem; }
        .vente-info .vente-client { font-size: 0.75rem; color: #8A99AA; }
        .vente-total { color: #C8922A; font-weight: 700; font-size: 1rem; }

        @media (max-width: 1100px) {
            .pos-container { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .product-row { grid-template-columns: 2fr 1fr 0.5fr; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">AWA KA SUGU</div>
        <div class="brand-sub">Administration</div>
        <div class="admin-user">
            <div class="admin-avatar">A</div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Awa Doumbia') ?></div>
                <div class="admin-role">Administratrice</div>
            </div>
        </div>
    </div>
    <nav>
        <div class="nav-section">Principal</div>
        <a href="dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
        <a href="point_de_vente.php" class="nav-item active"><i class="bi bi-cash-stack"></i> Point de vente</a>
        <a href="produits.php" class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="commandes.php" class="nav-item"><i class="bi bi-receipt"></i> Commandes</a>
        <a href="clients.php" class="nav-item"><i class="bi bi-people"></i> Clients</a>
        <div class="nav-section">Compte</div>
        <a href="../index.php" class="nav-item"><i class="bi bi-house"></i> Voir le site</a>
        <a href="logout.php" class="nav-item logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">💰 Point de <span>Vente</span></div>
            <div class="topbar-breadcrumb">Administration → Point de vente → Enregistrement</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-outline"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">
        <?php if(isset($_GET['success'])): ?>
            <div class="alert-success">
                <i class="bi bi-check-circle-fill"></i> Vente enregistrée avec succès ! 
                <strong>N° <?= htmlspecialchars($_GET['vente'] ?? '') ?></strong>
                <a href="point_de_vente.php" class="btn-admin btn-or" style="float:right;padding:5px 15px;font-size:0.7rem;">Nouvelle vente</a>
            </div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
        <?php endif; ?>

        <div class="pos-container">
            <!-- Partie gauche : Sélection des produits -->
            <div class="pos-left">
                <h3><i class="bi bi-cart-plus"></i> Ajouter des produits</h3>
                
                <div class="product-select">
                    <select id="produitSelect">
                        <option value="">-- Sélectionner un produit --</option>
                        <?php foreach($produits as $p): ?>
                            <option value="<?= $p['id'] ?>" data-prix="<?= $p['prix'] ?>" data-prixpromo="<?= $p['prix_promo'] ?>" data-stock="<?= $p['stock'] ?>">
                                <?= htmlspecialchars($p['nom']) ?> - <?= number_format($p['prix'], 0, ',', ' ') ?> FCFA 
                                <?php if($p['prix_promo']): ?> (Promo: <?= number_format($p['prix_promo'], 0, ',', ' ') ?> FCFA)<?php endif; ?>
                                (Stock: <?= $p['stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="produitsSelectionnes">
                    <!-- Les produits sélectionnés apparaîtront ici -->
                </div>
            </div>

            <!-- Partie droite : Informations client et total -->
            <div class="pos-right">
                <h3><i class="bi bi-person"></i> Informations client</h3>
                
                <form method="POST" id="venteForm">
                    <div class="client-info">
                        <input type="text" name="client_nom" placeholder="Nom du client *" required>
                        <input type="tel" name="client_telephone" placeholder="Téléphone">
                        <input type="email" name="client_email" placeholder="Email">
                    </div>

                    <div id="panierRecap">
                        <div style="font-size:0.85rem;color:#8A99AA;padding:10px 0;border-bottom:1px solid #F0F2F5;">Aucun produit sélectionné</div>
                    </div>

                    <div class="total-box">
                        <div class="total-label">Total à payer</div>
                        <div class="total-amount" id="totalDisplay">0 FCFA</div>
                    </div>

                    <div class="payment-row">
                        <select name="mode_paiement" id="modePaiement">
                            <option value="especes">💰 Espèces</option>
                            <option value="orange_money">📱 Orange Money</option>
                            <option value="wave">🌊 Wave</option>
                            <option value="moov_money">📱 Moov Money</option>
                            <option value="carte">💳 Carte bancaire</option>
                        </select>
                        <input type="number" name="montant_recu" id="montantRecu" placeholder="Montant reçu" step="100" min="0">
                    </div>

                    <div style="margin:10px 0;">
                        <input type="text" name="notes" placeholder="Notes (optionnel)" style="width:100%;padding:10px 14px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:'Jost',sans-serif;">
                    </div>

                    <button type="submit" name="enregistrer_vente" class="btn-submit">
                        <i class="bi bi-check-circle"></i> Enregistrer la vente
                    </button>
                </form>
            </div>
        </div>

        <!-- Dernières ventes -->
        <div class="ventes-recentes">
            <h3><i class="bi bi-clock-history"></i> Dernières ventes en boutique</h3>
            <?php if(empty($dernieres_ventes)): ?>
                <div style="text-align:center;padding:30px;color:#8A99AA;">
                    <i class="bi bi-cart-x" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                    <p>Aucune vente enregistrée en boutique</p>
                </div>
            <?php else: ?>
                <?php foreach($dernieres_ventes as $v): ?>
                <div class="vente-item">
                    <div class="vente-info">
                        <div class="vente-numero"><?= htmlspecialchars($v['numero_vente']) ?></div>
                        <div class="vente-client"><?= htmlspecialchars($v['client_nom']) ?> • <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></div>
                    </div>
                    <div class="vente-total"><?= number_format($v['total'], 0, ',', ' ') ?> FCFA</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Gestion des produits sélectionnés
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
        recap.innerHTML = '<div style="font-size:0.85rem;color:#8A99AA;padding:10px 0;border-bottom:1px solid #F0F2F5;">Aucun produit sélectionné</div>';
        document.getElementById('totalDisplay').innerText = '0 FCFA';
        return;
    }
    
    ids.forEach(id => {
        const p = produitsSelectionnes[id];
        const ligneTotal = p.prix * p.quantite;
        total += ligneTotal;
        
        html += `
            <div class="product-row">
                <span class="prod-name">${p.nom}</span>
                <span class="prod-price">${p.prix.toLocaleString()} FCFA</span>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="number" value="${p.quantite}" min="1" max="${p.stock}" 
                           data-id="${id}" class="qty-input" style="width:50px;">
                    <button type="button" class="remove-btn" data-id="${id}"><i class="bi bi-x-circle"></i></button>
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
            <span style="color:#C8922A;">${ligneTotal.toLocaleString()} FCFA</span>
        </div>`;
    });
    recapHtml += `<div style="display:flex;justify-content:space-between;padding:10px 0;font-weight:700;font-size:1rem;">
        <span>TOTAL</span>
        <span style="color:#C8922A;">${total.toLocaleString()} FCFA</span>
    </div>`;
    recap.innerHTML = recapHtml;
    
    document.getElementById('totalDisplay').innerText = total.toLocaleString() + ' FCFA';
    
    // Ajouter les événements pour les quantités et suppressions
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const id = this.dataset.id;
            const qte = parseInt(this.value) || 1;
            produitsSelectionnes[id].quantite = qte;
            afficherPanier();
        });
    });
    
    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            delete produitsSelectionnes[id];
            afficherPanier();
        });
    });
}

// Soumission du formulaire
document.getElementById('venteForm').addEventListener('submit', function(e) {
    // Ajouter les quantités en champs cachés
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
</body>
</html>