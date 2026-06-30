<?php
// ============================================
// FORMULAIRE DE COMMANDE - Awa Ka Sugu
// Version avec paiements Orange Money et Wave
// ============================================

// ============================================
// SESSION PUBLIQUE SÉPARÉE
// ============================================
session_name('PUBLIC_SESSION');
session_start();

// ============================================
// ✅ INCLURE LA CONFIGURATION (SITE_URL, etc.)
// ============================================
require_once '../includes/config.php';

// ============================================
// VÉRIFICATION MAINTENANCE
// ============================================
require_once '../includes/maintenance_check.php';

// ============================================
// INCLURE LA FONCTION D'ENVOI D'EMAIL (BREVO)
// ============================================
require_once '../includes/envoi_email.php';

// Vérifier que le panier n'est pas vide
if (empty($_SESSION['panier'])) {
    header('Location: catalogue.php');
    exit;
}

// Connexion à la base de données
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

// Calcul du total
$total = 0;
foreach ($_SESSION['panier'] as $item) {
    $total += $item['prix'] * $item['quantite'];
}

$error = '';
$success = false;
$numero_commande = '';
$commande_id = 0;

// ============================================
// FONCTION POUR GÉNÉRER LA FACTURE PDF
// ============================================
function genererFacturePDF($commande_id, $commande, $details, $pdo) {
    require_once dirname(__DIR__) . '/includes/fpdf.php';
    
    $numero_facture = 'FACT-' . date('Ymd') . '-' . str_pad($commande_id, 4, '0', STR_PAD_LEFT);
    
    // Vérifier si la facture existe déjà
    $stmt = $pdo->prepare("SELECT * FROM factures WHERE commande_id = ?");
    $stmt->execute([$commande_id]);
    $facture = $stmt->fetch();
    
    if (!$facture) {
        $stmt = $pdo->prepare("
            INSERT INTO factures (numero_facture, type, commande_id, client_nom, client_telephone, montant_total, statut_paiement, created_at)
            VALUES (?, 'boutique', ?, ?, ?, ?, 'payee', NOW())
        ");
        $stmt->execute([$numero_facture, $commande_id, $commande['nom_client'], $commande['telephone'], $commande['total']]);
        $facture_id = $pdo->lastInsertId();
    } else {
        $numero_facture = $facture['numero_facture'];
        $facture_id = $facture['id'];
    }
    
    // Créer le PDF
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 25);
    
    // ===== EN-TÊTE =====
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(0, 10, 'AWA KA SUGU', 0, 1, 'C');
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, 'Boutique IBA Design - Restaurant Sofia', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Sebenikoro Koro, Bamako - Mali', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Tel: +223 77 77 43 43', 0, 1, 'C');
    $pdf->Ln(6);
    
    $pdf->SetDrawColor(200, 146, 42);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(8);
    
    // ===== TITRE =====
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 12, 'FACTURE', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, 'N° ' . $numero_facture, 0, 1, 'C');
    $pdf->Ln(6);
    
    // ===== INFORMATIONS CLIENT =====
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor(200, 146, 42);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect(20, $pdf->GetY(), 170, 75, 'DF');
    
    $startY = $pdf->GetY() + 5;
    
    $pdf->SetY($startY);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->SetX(30);
    $pdf->Cell(40, 8, 'CLIENT', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ': ' . $commande['nom_client'], 0, 1);
    
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(40, 8, 'TELEPHONE', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ': ' . $commande['telephone'], 0, 1);
    
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(40, 8, 'ADRESSE', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 8, ': ' . $commande['adresse_livraison'], 0, 1);
    
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(40, 8, 'DATE', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ': ' . date('d/m/Y à H:i', strtotime($commande['created_at'])), 0, 1);
    
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(40, 8, 'PAIEMENT', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $modes = ['livraison' => 'Paiement à la livraison', 'orange_money' => 'Orange Money', 'wave' => 'Wave', 'moov_money' => 'Moov Money'];
    $mode_label = $modes[$commande['mode_paiement']] ?? $commande['mode_paiement'];
    $pdf->Cell(0, 8, ': ' . $mode_label, 0, 1);
    
    $pdf->Ln(10);
    
    // ===== TABLEAU DES PRODUITS =====
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(13, 13, 13);
    
    $pdf->Cell(85, 12, 'PRODUIT', 1, 0, 'C', true);
    $pdf->Cell(30, 12, 'QUANTITE', 1, 0, 'C', true);
    $pdf->Cell(35, 12, 'PRIX UNITAIRE', 1, 0, 'C', true);
    $pdf->Cell(35, 12, 'TOTAL', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('Arial', '', 10);
    $fill = false;
    
    foreach($details as $d) {
        $total_ligne = $d['quantite'] * $d['prix_unitaire'];
        $nom_produit = $d['nom_produit'];
        if(strlen($nom_produit) > 40) {
            $nom_produit = substr($nom_produit, 0, 38) . '...';
        }
        $pdf->SetFillColor($fill ? 248 : 255);
        $pdf->Cell(85, 9, $nom_produit, 1, 0, 'L', $fill);
        $pdf->Cell(30, 9, $d['quantite'], 1, 0, 'C', $fill);
        $pdf->Cell(35, 9, number_format($d['prix_unitaire'], 0, ',', ' ') . ' F', 1, 0, 'R', $fill);
        $pdf->Cell(35, 9, number_format($total_ligne, 0, ',', ' ') . ' F', 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    
    // Total
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->SetFillColor(255, 248, 240);
    $pdf->SetDrawColor(200, 146, 42);
    $pdf->SetLineWidth(0.5);
    $pdf->Cell(150, 13, 'TOTAL', 1, 0, 'R', true);
    $pdf->Cell(35, 13, number_format($commande['total'], 0, ',', ' ') . ' FCFA', 1, 1, 'C', true);
    
    // Notes
    if (!empty($commande['notes'])) {
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, '📝 Notes :', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell(0, 6, $commande['notes'], 0, 'L');
    }
    
    // Message de remerciement
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(0, 8, '✨ Merci de votre confiance ! ✨', 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, 'Nous espérons vous revoir bientôt chez Awa Ka Sugu.', 0, 1, 'C');
    
    // Pied de page
    $pdf->SetY(-35);
    $pdf->SetDrawColor(200, 146, 42);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Merci de votre confiance !', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Livraison sous 24h-48h a Bamako.', 0, 1, 'C');
    
    // Sauvegarde du PDF
    $pdf_dir = dirname(__DIR__) . '/uploads/factures/';
    if (!is_dir($pdf_dir)) {
        mkdir($pdf_dir, 0777, true);
    }
    
    $pdf_file = 'facture_' . $numero_facture . '.pdf';
    $pdf_path = $pdf_dir . $pdf_file;
    $pdf->Output($pdf_path, 'F');
    
    // Mettre à jour la base
    $pdo->prepare("UPDATE factures SET fichier_pdf = ? WHERE id = ?")->execute([$pdf_file, $facture_id]);
    
    return ['pdf_path' => $pdf_path, 'pdf_file' => $pdf_file, 'numero_facture' => $numero_facture];
}

// ============================================
// TRAITEMENT DU FORMULAIRE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $mode_paiement = $_POST['mode_paiement'] ?? 'livraison';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($nom) || empty($email) || empty($telephone) || empty($adresse)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer un email valide.';
    } else {
        // Générer un numéro de commande unique
        $numero_commande = 'AWA-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        // Récupérer le client_id si le client est connecté
        $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
        
        // Insérer la commande
        $stmt = $pdo->prepare("
            INSERT INTO commandes (
                numero_commande, 
                client_id,
                nom_client, 
                telephone, 
                adresse_livraison, 
                commune, 
                mode_paiement, 
                total, 
                notes, 
                statut, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
        ");
        $stmt->execute([
            $numero_commande, 
            $client_id,
            $nom, 
            $telephone, 
            $adresse, 
            $commune, 
            $mode_paiement, 
            $total, 
            $notes
        ]);
        $commande_id = $pdo->lastInsertId();
        
        // Insérer les détails de la commande
        foreach ($_SESSION['panier'] as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO details_commande (commande_id, produit_id, nom_produit, quantite, prix_unitaire, sous_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $sous_total = $item['prix'] * $item['quantite'];
            $stmt->execute([$commande_id, $item['id'], $item['nom'], $item['quantite'], $item['prix'], $sous_total]);
        }
        
        // Récupérer la commande complète pour la facture
        $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
        $stmt->execute([$commande_id]);
        $commande_complete = $stmt->fetch();
        
        // Récupérer les détails pour la facture
        $stmt = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
        $stmt->execute([$commande_id]);
        $details_commande = $stmt->fetchAll();
        
        // ============================================
        // GÉNÉRER LA FACTURE PDF
        // ============================================
        $facture_info = genererFacturePDF($commande_id, $commande_complete, $details_commande, $pdo);
        
        // ============================================
        // ENVOI DE L'EMAIL DE CONFIRMATION AVEC FACTURE
        // ============================================
        $sujet = "✅ Confirmation de votre commande Awa Ka Sugu - N° $numero_commande";
        
        // Construire le message HTML
        $message_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmation de commande</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #0D0D0D, #1A1A1A); padding: 30px; text-align: center; border-bottom: 3px solid #C8922A; }
                .header h1 { font-family: "Georgia", serif; color: #C8922A; margin: 0; font-size: 1.8rem; letter-spacing: 3px; }
                .header p { color: rgba(255,255,255,0.4); margin: 5px 0 0; font-size: 0.8rem; }
                .content { padding: 30px; }
                .content h2 { font-size: 1.2rem; color: #0D0D0D; margin-bottom: 10px; }
                .content .sub { color: #666; font-size: 0.9rem; margin-bottom: 20px; }
                .info-box { background: #F8F9FA; padding: 15px 20px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #C8922A; }
                .info-box p { margin: 5px 0; font-size: 0.9rem; color: #333; }
                .info-box strong { color: #0D0D0D; }
                .info-box .total { font-size: 1.3rem; font-weight: 700; color: #C8922A; text-align: right; margin-top: 10px; padding-top: 10px; border-top: 2px solid #C8922A; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background: #F8F9FA; padding: 10px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #8A99AA; border-bottom: 2px solid #C8922A; }
                td { padding: 10px; border-bottom: 1px solid #F0F2F5; }
                .footer { background: #F8F9FA; padding: 20px; text-align: center; color: #8A99AA; font-size: 0.8rem; border-top: 1px solid #E8ECF0; }
                .badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: #FFF3CD; color: #856404; }
                .btn { display: inline-block; background: #C8922A; color: white; padding: 10px 25px; border-radius: 30px; text-decoration: none; margin-top: 15px; }
                .btn:hover { background: #9A6E1A; }
                .facture-info { background: #E8F5E9; padding: 12px 18px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #27AE60; }
                .facture-info i { color: #27AE60; margin-right: 8px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✦ AWA KA SUGU ✦</h1>
                    <p>Boutique IBA Design & Restaurant Sofia</p>
                </div>
                <div class="content">
                    <h2>Merci pour votre commande, ' . htmlspecialchars($nom) . ' !</h2>
                    <p class="sub">Votre commande a bien été enregistrée.</p>
                    
                    <div class="info-box">
                        <p><strong>📦 Numéro :</strong> <span style="color:#C8922A;font-weight:700;">' . $numero_commande . '</span></p>
                        <p><strong>📅 Date :</strong> ' . date('d/m/Y à H:i') . '</p>
                        <p><strong>💰 Total :</strong> <span style="color:#C8922A;font-weight:700;">' . number_format($total, 0, ',', ' ') . ' FCFA</span></p>
                        <p><strong>💳 Paiement :</strong> ' . ucfirst(str_replace('_', ' ', $mode_paiement)) . '</p>
                        <p><strong>📍 Adresse :</strong> ' . nl2br($adresse) . '</p>
                        <div class="total">💰 ' . number_format($total, 0, ',', ' ') . ' FCFA</div>
                    </div>
                    
                    <div class="facture-info">
                        <i class="bi bi-file-pdf"></i>
                        <strong>📄 Votre facture est jointe à cet email.</strong>
                        <br><small style="color:#666;">Facture N° ' . $facture_info['numero_facture'] . '</small>
                    </div>
                    
                    <h3 style="margin-top:20px;">🛍️ Détails de la commande</h3>
                    <table>
                        <thead><tr><th>Produit</th><th style="text-align:center;">Qté</th><th style="text-align:right;">Prix</th><th style="text-align:right;">Total</th></tr></thead>
                        <tbody>';
        
        foreach ($_SESSION['panier'] as $item) {
            $message_html .= '<tr><td>' . htmlspecialchars($item['nom']) . '</td><td style="text-align:center;">' . $item['quantite'] . '</td><td style="text-align:right;">' . number_format($item['prix'], 0, ',', ' ') . ' F</td><td style="text-align:right;">' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' F</td></tr>';
        }
        
        $message_html .= '
                        </tbody>
                    </table>
                    
                    <p style="margin-top:20px;"><span class="badge">📦 En attente de validation</span></p>
                    <p style="color:#666;font-size:0.85rem;">Livraison sous 24h-48h à Bamako.</p>
                    
                    <p style="text-align:center;">
                        <a href="' . SITE_URL . '/boutique/suivi.php" class="btn">📦 Suivre ma commande</a>
                    </p>
                </div>
                <div class="footer">
                    <p>Awa Ka Sugu &copy; ' . date('Y') . ' - Tous droits réservés</p>
                    <p style="font-size:0.7rem;">Cet email est généré automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // ✅ Envoyer l'email avec Brevo et la facture PDF en pièce jointe
        $email_envoye = envoyerEmail($email, $sujet, $message_html, $facture_info['pdf_path']);
        
        // Vider le panier
        $_SESSION['panier'] = [];
        
        // ============================================
        // REDIRECTION AVEC MESSAGE DE SUCCÈS
        // ============================================
        $_SESSION['commande_success'] = [
            'numero' => $numero_commande,
            'total' => $total,
            'nom' => $nom,
            'email_envoye' => $email_envoye
        ];
        
        if ($mode_paiement == 'orange_money') {
            header("Location: ../paiement/orange_money.php?id=$commande_id");
            exit;
        } elseif ($mode_paiement == 'wave') {
            header("Location: ../paiement/wave.php?id=$commande_id");
            exit;
        } else {
            header("Location: confirmation.php?numero=$numero_commande");
            exit;
        }
    }
}

// ============================================
// INCLUSION DU HEADER
// ============================================
$titre_page = 'Finaliser ma commande - IBA Design';
$meta_desc = 'Finalisez votre commande et choisissez votre mode de paiement.';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Récupérer les infos client si connecté
$client_nom = '';
$client_email = '';
$client_telephone = '';
$client_adresse = '';
if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    $client = $stmt->fetch();
    if ($client) {
        $client_nom = ($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? '');
        $client_email = $client['email'] ?? '';
        $client_telephone = $client['telephone'] ?? '';
        $client_adresse = $client['adresse_complete'] ?? '';
    }
}
?>

<style>
/* ========== PAGE COMMANDE ========== */
.commande-header {
    background: linear-gradient(135deg, #0D0D0D 0%, #1A1A1A 50%, #0D0D0D 100%);
    padding: 40px 0 30px;
    text-align: center;
    margin-bottom: 40px;
}
.commande-header h1 {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    color: #C8922A;
    margin-bottom: 8px;
}
.commande-header p {
    color: rgba(255,255,255,0.5);
    font-size: 0.9rem;
}
.container-custom {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 20px 60px;
}
.commande-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 30px;
}
.formulaire-card, .resume-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.06);
    border: 1px solid rgba(200,146,42,0.08);
}
.formulaire-card .section-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    color: #0D0D0D;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 0.8rem;
    color: #0D0D0D;
}
.form-group label .required {
    color: #E74C3C;
}
.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #E0E6ED;
    border-radius: 10px;
    font-family: 'Jost', sans-serif;
    font-size: 0.9rem;
    transition: all 0.3s;
}
.form-control:focus {
    outline: none;
    border-color: #C8922A;
    box-shadow: 0 0 0 3px rgba(200,146,42,0.08);
}
.form-control::placeholder {
    color: #B0B0B0;
}
.form-check {
    padding: 8px 12px;
    border-radius: 10px;
    transition: background 0.3s;
}
.form-check:hover {
    background: #FEFBF5;
}
.form-check-input:checked {
    background-color: #C8922A;
    border-color: #C8922A;
}
.btn-valider {
    width: 100%;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    color: white;
    padding: 15px;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-valider:hover {
    background: linear-gradient(135deg, #9A6E1A, #C8922A);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(200,146,42,0.3);
}
.alert-error {
    background: #FEF3F2;
    border-left: 4px solid #E74C3C;
    color: #721C24;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.resume-card h4 {
    font-family: 'Playfair Display', serif;
    color: #0D0D0D;
    margin-bottom: 20px;
}
.resume-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #F0F2F5;
    font-size: 0.9rem;
}
.resume-item .item-name {
    color: #0D0D0D;
}
.resume-item .item-price {
    font-weight: 600;
    color: #C8922A;
}
.resume-total {
    display: flex;
    justify-content: space-between;
    padding-top: 15px;
    margin-top: 15px;
    border-top: 2px solid #C8922A;
    font-size: 1.2rem;
    font-weight: 700;
}
.resume-total .total-label {
    color: #0D0D0D;
}
.resume-total .total-amount {
    color: #C8922A;
}
.paiement-info {
    background: #FEFBF5;
    padding: 15px 18px;
    border-radius: 12px;
    margin-top: 20px;
    border: 1px solid rgba(200,146,42,0.08);
}
.paiement-info i {
    color: #C8922A;
    margin-right: 8px;
}
.paiement-info small {
    color: #8A99AA;
    font-size: 0.8rem;
}
@media (max-width: 850px) {
    .commande-grid { grid-template-columns: 1fr; }
    .formulaire-card, .resume-card { padding: 20px; }
}
@media (max-width: 600px) {
    .commande-header h1 { font-size: 1.8rem; }
    .commande-header { padding: 30px 0 20px; }
}
</style>

<!-- Header -->
<div class="commande-header">
    <div class="container-custom" style="padding-bottom:0;">
        <h1>📦 Finaliser ma commande</h1>
        <p>Remplissez vos informations pour valider votre commande</p>
    </div>
</div>

<div class="container-custom">
    <div class="commande-grid">
        <!-- Formulaire -->
        <div class="formulaire-card">
            <div class="section-title">📍 Informations de livraison</div>
            
            <?php if($error): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Nom complet <span class="required">*</span></label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($client_nom) ?>" placeholder="Votre nom complet" required>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client_email) ?>" placeholder="votre@email.com" required>
                </div>
                
                <div class="form-group">
                    <label>Téléphone <span class="required">*</span></label>
                    <input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($client_telephone) ?>" placeholder="77 00 00 00" required>
                </div>
                
                <div class="form-group">
                    <label>Commune</label>
                    <input type="text" name="commune" class="form-control" placeholder="Ex: Commune I, II, III, IV, V, VI">
                </div>
                
                <div class="form-group">
                    <label>Adresse de livraison <span class="required">*</span></label>
                    <textarea name="adresse" class="form-control" rows="3" placeholder="Rue, quartier, porte..." required><?= htmlspecialchars($client_adresse) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Notes (optionnel)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Instructions particulières..."></textarea>
                </div>
                
                <div class="form-group" style="margin-top:25px;">
                    <label><strong>Mode de paiement</strong></label>
                    <div class="mt-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="livraison" id="livraison" checked>
                            <label class="form-check-label" for="livraison">💵 Paiement à la livraison</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="orange_money" id="orange">
                            <label class="form-check-label" for="orange">🟠 Orange Money</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="wave" id="wave">
                            <label class="form-check-label" for="wave">🌊 Wave</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="moov_money" id="moov">
                            <label class="form-check-label" for="moov">📱 Moov Money</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-valider">
                    <i class="bi bi-check-circle"></i> Confirmer ma commande
                </button>
            </form>
        </div>
        
        <!-- Résumé -->
        <div class="resume-card">
            <h4>🛒 Récapitulatif</h4>
            <?php foreach($_SESSION['panier'] as $item): ?>
            <div class="resume-item">
                <span class="item-name"><?= htmlspecialchars($item['nom']) ?> <span style="color:#8A99AA;font-size:0.8rem;">x<?= $item['quantite'] ?></span></span>
                <span class="item-price"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> F</span>
            </div>
            <?php endforeach; ?>
            <div class="resume-total">
                <span class="total-label">Total</span>
                <span class="total-amount"><?= number_format($total, 0, ',', ' ') ?> FCFA</span>
            </div>
            <div class="paiement-info">
                <i class="bi bi-info-circle"></i>
                <small>Pour Orange Money/Wave, vous serez redirigé vers la page de paiement sécurisé.</small>
            </div>
            <div style="margin-top:15px;padding-top:15px;border-top:1px solid #F0F2F5;">
                <small style="color:#8A99AA;display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-shield-check" style="color:#27AE60;"></i>
                    Paiement sécurisé
                </small>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>