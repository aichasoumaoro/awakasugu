<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
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
        // Récupérer le produit
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
        $stmt->execute([$produit_id]);
        $produit = $stmt->fetch();
        
        if ($produit) {
            // Générer le numéro d'achat
            $numero_achat = 'ACH-' . date('Ymd') . '-' . strtoupper(uniqid());
            $total_ligne = $quantite * $prix_unitaire;
            
            // Insérer l'achat
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
            
            // Mettre à jour le stock du produit
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
    header('Location: achats.php');
    exit;
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================

// Tous les produits
$produits = $pdo->query("SELECT * FROM produits WHERE est_visible = 1 ORDER BY nom")->fetchAll();

// Tous les achats
$achats = $pdo->query("SELECT * FROM achats ORDER BY date_achat DESC")->fetchAll();

// Statistiques des achats
$total_achats = $pdo->query("SELECT COUNT(*) FROM achats")->fetchColumn();
$total_depenses = $pdo->query("SELECT COALESCE(SUM(total_ligne), 0) FROM achats")->fetchColumn();
$total_articles_achetes = $pdo->query("SELECT COALESCE(SUM(quantite), 0) FROM achats")->fetchColumn();

// Achats du mois
$achats_mois = $pdo->query("
    SELECT COALESCE(SUM(total_ligne), 0) FROM achats 
    WHERE MONTH(date_achat) = MONTH(CURDATE()) AND YEAR(date_achat) = YEAR(CURDATE())
")->fetchColumn();

$message = $_SESSION['message_achat'] ?? '';
unset($_SESSION['message_achat']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Achats - Admin Awa Ka Sugu</title>
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

        .content { padding: 28px 32px; flex: 1; }

        .alert-success {
            background: #D4EDDA;
            border-left: 4px solid #27AE60;
            color: #0A3622;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-box {
            background: #fff;
            border-radius: 16px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #E8ECF0;
            transition: all 0.22s;
        }
        .stat-box:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .ic-or { background: rgba(200,146,42,0.1); color: #C8922A; }
        .ic-green { background: rgba(27,122,74,0.1); color: #1A7A4A; }
        .stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem; font-weight: 700;
            color: #1A2C3E; line-height: 1;
        }
        .stat-lbl { font-size: 0.73rem; color: #8A99AA; margin-top: 3px; }

        .form-achat {
            background: #fff;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            border: 1px solid #E8ECF0;
        }
        .form-achat h3 { font-size: 1.1rem; margin-bottom: 20px; color: #0D0D0D; }
        .form-achat .row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E0E0E0;
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: 'Jost', sans-serif;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #C8922A;
        }
        .form-control select { cursor: pointer; }

        .table-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #E8ECF0;
        }
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #F0F2F5;
        }
        .table-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 600;
            color: #0D0D0D;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0D0D0D;
            color: #C8922A;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 14px 20px;
            text-align: left;
        }
        tbody td {
            padding: 14px 20px;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #F0F2F5;
            vertical-align: middle;
        }
        tbody tr:hover td { background: #FEFBF5; }
        .btn-act {
            width: 32px; height: 32px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-delete { background: rgba(231,76,60,0.1); color: #E74C3C; }
        .btn-delete:hover { background: #E74C3C; color: #fff; }
        .empty-state { text-align: center; padding: 60px; color: #999; }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 15px; }

        @media (max-width: 1100px) { 
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .form-achat .row { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .content { padding: 20px 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .form-achat .row { grid-template-columns: 1fr; }
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
        <a href="produits.php" class="nav-item"><i class="bi bi-box-seam"></i> Produits</a>
        <a href="achats.php" class="nav-item active"><i class="bi bi-cart-check"></i> Achats</a>
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
            <div class="topbar-title">🛒 Gestion des <span>Achats</span></div>
            <div class="topbar-breadcrumb">Administration → Achats → Approvisionnement</div>
        </div>
        <div class="topbar-right">
            <a href="../index.php" class="btn-admin btn-outline"><i class="bi bi-eye"></i> Voir le site</a>
        </div>
    </div>

    <div class="content">
        <?php if($message): ?>
            <div class="alert-success"><i class="bi bi-check-circle-fill"></i> <?= $message ?></div>
        <?php endif; ?>

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

        <!-- Formulaire d'achat -->
        <div class="form-achat">
            <h3><i class="bi bi-plus-circle"></i> Enregistrer un achat</h3>
            <form method="POST">
                <div class="row">
                    <div class="form-group">
                        <label>Produit</label>
                        <select name="produit_id" class="form-control" required>
                            <option value="">Sélectionner un produit</option>
                            <?php foreach($produits as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['nom']) ?> (Stock: <?= $p['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantité</label>
                        <input type="number" name="quantite" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Prix unitaire (FCFA)</label>
                        <input type="number" name="prix_unitaire" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Fournisseur</label>
                        <input type="text" name="fournisseur_nom" class="form-control" placeholder="Nom du fournisseur">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="ajouter_achat" class="btn-admin btn-success" style="width:100%;">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </div>
                <div style="margin-top:10px;">
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Notes sur cet achat...">
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des achats -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-title">📋 Historique des achats</div>
                <div class="table-count"><?= count($achats) ?> achat(s)</div>
            </div>
            <table>
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
                        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-cart-x"></i><p>Aucun achat enregistré</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach($achats as $a): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($a['numero_achat']) ?></strong></td>
                            <td><?= htmlspecialchars($a['nom_produit']) ?></td>
                            <td><?= htmlspecialchars($a['nom_fournisseur'] ?? '-') ?></td>
                            <td><strong><?= $a['quantite'] ?></strong></td>
                            <td><?= number_format($a['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                            <td style="color:#C8922A;font-weight:600;"><?= number_format($a['total_ligne'], 0, ',', ' ') ?> FCFA</td>
                            <td style="font-size:0.75rem;color:#999;"><?= date('d/m/Y H:i', strtotime($a['date_achat'])) ?></td>
                            <td style="text-align:center;">
                                <a href="achats.php?supprimer=<?= $a['id'] ?>" class="btn-act btn-delete" onclick="return confirm('Supprimer cet achat ?')">
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
</body>
</html>