<?php
// ============================================
// GÉNÉRER FACTURE - ADMIN AWA KA SUGU
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

// Vérification des permissions (Générer facture visible pour super_admin et directeur uniquement)
if ($admin_role !== 'super_admin' && $admin_role !== 'directeur') {
    header('Location: dashboard.php?error=Accès non autorisé');
    exit;
}

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

// Inclure FPDF
require_once dirname(__DIR__) . '/includes/fpdf.php';

$commande_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($commande_id == 0) {
    header('Location: factures.php');
    exit;
}

// Récupérer la commande
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: factures.php');
    exit;
}

// Récupérer les détails
$details = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
$details->execute([$commande_id]);
$details = $details->fetchAll();

// Récupérer l'email du client
$email_client = $commande['email'] ?? '';

// Numéro de facture
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

// ============================================
// CRÉATION DU PDF
// ============================================
class FacturePDF extends FPDF {
    function Header() {
        $this->SetY(10);
        $this->SetFont('Arial', 'B', 24);
        $this->SetTextColor(200, 146, 42);
        $this->Cell(0, 12, 'AWA KA SUGU', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 11);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 6, 'Boutique IBA Design - Restaurant Sofia', 0, 1, 'C');
        $this->Cell(0, 6, 'Sebenikoro Koro, Bamako - Mali', 0, 1, 'C');
        $this->Cell(0, 6, 'Tel: +223 77 77 43 43 | Email: contact@awakasugu.com', 0, 1, 'C');
        $this->Ln(4);
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.5);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(8);
    }
    
    function Footer() {
        $this->SetY(-30);
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.3);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(4);
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'Merci de votre confiance !', 0, 1, 'C');
        $this->Cell(0, 5, 'Livraison sous 24h-48h a Bamako.', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(180, 180, 180);
        $this->Cell(0, 5, 'AWA KA SUGU - Boutique IBA Design & Restaurant Sofia', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }
}

$pdf = new FacturePDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 40);

// TITRE FACTURE
$pdf->SetFont('Arial', 'B', 26);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 16, 'FACTURE', 0, 1, 'C');

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 7, 'N° ' . $numero_facture, 0, 1, 'C');
$pdf->Ln(6);

// CADRE INFORMATIONS
$pdf->SetFillColor(248, 249, 250);
$pdf->SetDrawColor(200, 146, 42);
$pdf->SetLineWidth(0.3);
$pdf->Rect(20, $pdf->GetY(), 170, 85, 'DF');

$startY = $pdf->GetY() + 6;
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

if(!empty($email_client)){
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->Cell(40, 8, 'EMAIL', 0, 0);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, ': ' . $email_client, 0, 1);
}

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
$modes = ['livraison' => 'Paiement à la livraison', 'orange_money' => 'Orange Money', 'wave' => 'Wave', 'moov_money' => 'Moov Money', 'carte' => 'Carte bancaire', 'especes' => 'Espèces'];
$mode_label = $modes[$commande['mode_paiement']] ?? $commande['mode_paiement'];
$pdf->Cell(0, 8, ': ' . $mode_label, 0, 1);

$pdf->Ln(10);

// TABLEAU DES PRODUITS
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

$total_lignes = 0;
$fill = false;

foreach($details as $d) {
    $total_ligne = $d['quantite'] * $d['prix_unitaire'];
    $total_lignes += $total_ligne;
    
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

// TOTAL
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(200, 146, 42);
$pdf->SetFillColor(255, 248, 240);
$pdf->SetDrawColor(200, 146, 42);
$pdf->SetLineWidth(0.5);
$pdf->Cell(150, 13, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell(35, 13, number_format($commande['total'], 0, ',', ' ') . ' FCFA', 1, 1, 'C', true);

// NOTES
if (!empty($commande['notes'])) {
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, '📝 Notes :', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->MultiCell(0, 6, $commande['notes'], 0, 'L');
}

// MESSAGE DE REMERCIEMENT
$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 11);
$pdf->SetTextColor(200, 146, 42);
$pdf->Cell(0, 8, '✨ Merci de votre confiance ! ✨', 0, 1, 'C');
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, 'Nous espérons vous revoir bientôt chez Awa Ka Sugu.', 0, 1, 'C');

// SAUVEGARDE PDF
$pdf_dir = dirname(__DIR__) . '/uploads/factures/';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0777, true);
}

$pdf_file = 'facture_' . $numero_facture . '.pdf';
$pdf_path = $pdf_dir . $pdf_file;
$pdf->Output($pdf_path, 'F');

// Mettre à jour la base
$pdo->prepare("UPDATE factures SET fichier_pdf = ?, statut_paiement = 'payee' WHERE id = ?")->execute([$pdf_file, $facture_id]);

// ============================================
// ENVOI DE L'EMAIL
// ============================================

// Vérifier si la fonction envoyerEmail existe
if (function_exists('envoyerEmail')) {
    if (!empty($email_client)) {
        $sujet = "📄 Votre facture Awa Ka Sugu - N° " . $numero_facture;
        
        $message_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture Awa Ka Sugu</title>
            <style>
                body { font-family: Arial, sans-serif; background: #F5F7FA; padding: 20px; margin: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
                .header { background: #0C0C14; padding: 30px 30px 20px; text-align: center; border-bottom: 3px solid #C8922A; }
                .header h1 { color: #C8922A; font-size: 1.8rem; margin: 0; font-family: "Georgia", serif; }
                .header p { color: rgba(255,255,255,0.4); font-size: 0.7rem; letter-spacing: 3px; text-transform: uppercase; margin: 4px 0 0; }
                .body { padding: 30px; }
                .greeting { font-size: 1.1rem; font-weight: 600; color: #0C0C14; margin-bottom: 4px; }
                .greeting span { color: #C8922A; }
                .sub-greeting { color: #8A99AA; font-size: 0.9rem; margin-bottom: 20px; }
                .info-card { background: #F8F9FA; border-radius: 12px; padding: 16px 20px; border-left: 4px solid #C8922A; margin-bottom: 20px; }
                .info-card .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #EDEDF0; }
                .info-card .row:last-child { border-bottom: none; }
                .info-card .row .label { color: #8A99AA; font-size: 0.8rem; }
                .info-card .row .value { font-weight: 600; color: #0C0C14; font-size: 0.9rem; }
                .info-card .row .value.total { color: #C8922A; font-size: 1.2rem; font-family: "Georgia", serif; }
                .info-card .row.highlight { border-top: 2px solid #C8922A; padding-top: 10px; margin-top: 4px; }
                .btn-download { display: inline-block; background: #C8922A; color: #fff; padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: 600; margin: 10px 0; }
                .btn-download:hover { background: #9A6E1A; }
                .footer { background: #F8F9FA; padding: 20px 30px; text-align: center; border-top: 1px solid #EDEDF0; font-size: 0.75rem; color: #8A99AA; }
                .footer a { color: #C8922A; text-decoration: none; }
                .thanks { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #EDEDF0; }
                .thanks .heart { color: #E74C3C; }
                .thanks .signature { font-family: "Georgia", serif; font-style: italic; color: #C8922A; margin-top: 4px; }
                @media (max-width: 500px) {
                    .info-card .row { flex-direction: column; align-items: flex-start; gap: 2px; }
                    .body { padding: 20px; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>AWA KA SUGU</h1>
                    <p>✦ Artisanat d&rsquo;exception ✦</p>
                </div>
                <div class="body">
                    <div class="greeting">Bonjour <span>' . htmlspecialchars($commande['nom_client']) . '</span> 👋</div>
                    <div class="sub-greeting">Voici le récapitulatif de votre commande.</div>
                    
                    <div class="info-card">
                        <div class="row">
                            <span class="label">📄 N° Facture</span>
                            <span class="value">' . $numero_facture . '</span>
                        </div>
                        <div class="row">
                            <span class="label">📦 N° Commande</span>
                            <span class="value">' . $commande['numero_commande'] . '</span>
                        </div>
                        <div class="row">
                            <span class="label">📅 Date</span>
                            <span class="value">' . date('d/m/Y à H:i', strtotime($commande['created_at'])) . '</span>
                        </div>
                        <div class="row">
                            <span class="label">📍 Adresse</span>
                            <span class="value">' . nl2br(htmlspecialchars($commande['adresse_livraison'])) . '</span>
                        </div>
                        <div class="row highlight">
                            <span class="label" style="font-weight:700;color:#0C0C14;">💰 Montant total</span>
                            <span class="value total">' . number_format($commande['total'], 0, ',', ' ') . ' FCFA</span>
                        </div>
                    </div>
                    
                    <div style="text-align:center;">
                        <a href="' . SITE_URL . '/uploads/factures/' . basename($pdf_path) . '" class="btn-download" target="_blank">📄 Télécharger ma facture</a>
                    </div>
                    
                    <div class="thanks">
                        <p><span class="heart">❤️</span> Merci d&rsquo;avoir choisi <strong>Awa Ka Sugu</strong></p>
                        <div class="signature">— Awa Doumbia</div>
                    </div>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' <strong>Awa Ka Sugu</strong> — Tous droits réservés</p>
                    <p><a href="mailto:contact@awakasugu.com">contact@awakasugu.com</a></p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $email_envoye = envoyerEmail($email_client, $sujet, $message_html, $pdf_path);
        
        if ($email_envoye) {
            $_SESSION['message_facture'] = 'Facture générée et envoyée par email !';
        } else {
            $_SESSION['message_facture'] = 'Facture générée mais email non envoyé.';
        }
    } else {
        $_SESSION['message_facture'] = 'Facture générée (aucun email client).';
    }
} else {
    $_SESSION['message_facture'] = 'Facture générée avec succès !';
}

header('Location: factures.php');
exit;
?>