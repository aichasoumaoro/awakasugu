<?php
// ============================================
// GÉNÉRER FACTURE AVEC EMAIL - AWA KA SUGU
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
$email_client = $commande['email_client'] ?? '';

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
// CRÉATION DU PDF AVEC DESIGN ÉLÉGANT
// ============================================
class FacturePDF extends FPDF {
    function Header() {
        // Logo ou en-tête
        $this->SetY(10);
        
        // Titre principal
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(200, 146, 42);
        $this->Cell(0, 12, 'AWA KA SUGU', 0, 1, 'C');
        
        // Sous-titre
        $this->SetFont('Arial', 'I', 11);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 6, 'Boutique IBA Design - Restaurant Sofia', 0, 1, 'C');
        $this->Cell(0, 6, 'Sebenikoro Koro, Bamako - Mali', 0, 1, 'C');
        $this->Cell(0, 6, 'Tel: +223 77 77 43 43 | Email: contact@awakasugu.com', 0, 1, 'C');
        
        // Ligne décorative
        $this->Ln(4);
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.5);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(8);
    }
    
    function Footer() {
        $this->SetY(-30);
        
        // Ligne décorative
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.3);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(4);
        
        // Pied de page
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

// ============================================
// TITRE FACTURE
// ============================================
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 14, 'FACTURE', 0, 1, 'C');

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 7, 'N° ' . $numero_facture, 0, 1, 'C');
$pdf->Ln(6);

// ============================================
// CADRE DES INFORMATIONS (design amélioré)
// ============================================
$pdf->SetFillColor(248, 249, 250);
$pdf->SetDrawColor(200, 146, 42);
$pdf->SetLineWidth(0.3);
$pdf->Rect(20, $pdf->GetY(), 170, 80, 'DF');

$startY = $pdf->GetY() + 6;

// Colonne de gauche
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

// Colonne de droite (date et paiement)
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

// ============================================
// TABLEAU DES PRODUITS (design amélioré)
// ============================================
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

// Total (design amélioré)
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(200, 146, 42);
$pdf->SetFillColor(255, 248, 240);
$pdf->SetDrawColor(200, 146, 42);
$pdf->SetLineWidth(0.5);
$pdf->Cell(150, 13, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell(35, 13, number_format($commande['total'], 0, ',', ' ') . ' FCFA', 1, 1, 'C', true);

// ============================================
// NOTES
// ============================================
if (!empty($commande['notes'])) {
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, '📝 Notes :', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->MultiCell(0, 6, $commande['notes'], 0, 'L');
}

// ============================================
// MESSAGE DE REMERCIEMENT (design amélioré)
// ============================================
$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 11);
$pdf->SetTextColor(200, 146, 42);
$pdf->Cell(0, 8, '✨ Merci de votre confiance ! ✨', 0, 1, 'C');
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, 'Nous espérons vous revoir bientôt chez Awa Ka Sugu.', 0, 1, 'C');

// ============================================
// SAUVEGARDE DU PDF
// ============================================
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
// ENVOI DE L'EMAIL AVEC FACTURE (utilisation de la fonction existante)
// ============================================

// Inclure la fonction d'envoi d'email
require_once dirname(__DIR__) . '/includes/envoi_email.php';

if (!empty($email_client)) {
    $sujet = "📄 Votre facture Awa Ka Sugu - N° " . $numero_facture;
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facture</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f5f5f5;
                padding: 20px;
                margin: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #0D0D0D, #1A1A1A);
                padding: 30px;
                text-align: center;
                border-bottom: 3px solid #C8922A;
            }
            .header h1 {
                font-family: "Georgia", serif;
                color: #C8922A;
                margin: 0;
                font-size: 1.8rem;
                letter-spacing: 3px;
            }
            .header p {
                color: rgba(255,255,255,0.4);
                margin: 5px 0 0;
                font-size: 0.8rem;
            }
            .content {
                padding: 30px;
            }
            .content h2 {
                font-size: 1.2rem;
                color: #0D0D0D;
                margin-bottom: 10px;
            }
            .content .sub {
                color: #666;
                font-size: 0.9rem;
                margin-bottom: 20px;
            }
            .info-box {
                background: #F8F9FA;
                padding: 15px 20px;
                border-radius: 10px;
                margin: 15px 0;
                border-left: 4px solid #C8922A;
            }
            .info-box p {
                margin: 5px 0;
                font-size: 0.9rem;
                color: #333;
            }
            .info-box strong {
                color: #0D0D0D;
            }
            .info-box .total {
                font-size: 1.3rem;
                font-weight: 700;
                color: #C8922A;
                text-align: right;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 2px solid #C8922A;
            }
            .btn-download {
                display: inline-block;
                background: #C8922A;
                color: white;
                padding: 12px 30px;
                border-radius: 30px;
                text-decoration: none;
                font-weight: 600;
                margin: 15px 0;
                transition: all 0.3s;
            }
            .btn-download:hover {
                background: #9A6E1A;
                transform: translateY(-2px);
            }
            .footer {
                background: #F8F9FA;
                padding: 20px;
                text-align: center;
                color: #8A99AA;
                font-size: 0.8rem;
                border-top: 1px solid #E8ECF0;
            }
            .footer a {
                color: #C8922A;
                text-decoration: none;
            }
            .badge {
                display: inline-block;
                padding: 4px 14px;
                border-radius: 20px;
                font-size: 0.7rem;
                font-weight: 600;
                background: #FFF3CD;
                color: #856404;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✦ AWA KA SUGU ✦</h1>
                <p>Boutique IBA Design & Restaurant Sofia</p>
            </div>
            <div class="content">
                <h2>Bonjour ' . htmlspecialchars($commande['nom_client']) . ' !</h2>
                <p class="sub">Votre facture est disponible en pièce jointe.</p>
                
                <div class="info-box">
                    <p><strong>📄 N° Facture :</strong> ' . $numero_facture . '</p>
                    <p><strong>📦 N° Commande :</strong> ' . $commande['numero_commande'] . '</p>
                    <p><strong>📅 Date :</strong> ' . date('d/m/Y à H:i', strtotime($commande['created_at'])) . '</p>
                    <p><strong>📍 Adresse :</strong> ' . nl2br($commande['adresse_livraison']) . '</p>
                    <p><strong>💳 Paiement :</strong> ' . $mode_label . '</p>
                    <div class="total">
                        💰 ' . number_format($commande['total'], 0, ',', ' ') . ' FCFA
                    </div>
                </div>
                
                <div style="text-align:center;">
                    <a href="' . SITE_URL . '/uploads/factures/' . basename($pdf_path) . '" class="btn-download" target="_blank">
                        📄 Télécharger la facture
                    </a>
                </div>
                
                <p style="text-align:center;font-size:0.85rem;color:#8A99AA;">
                    <small>Vous pouvez aussi retrouver toutes vos factures dans votre compte client.</small>
                </p>
            </div>
            <div class="footer">
                <p>Awa Ka Sugu &copy; ' . date('Y') . ' - Tous droits réservés</p>
                <p style="font-size:0.7rem;">
                    <a href="' . SITE_URL . '">' . SITE_URL . '</a> | 
                    <a href="mailto:contact@awakasugu.com">contact@awakasugu.com</a>
                </p>
                <p style="font-size:0.6rem;color:#bbb;margin-top:5px;">
                    Cet email est généré automatiquement, merci de ne pas y répondre.
                </p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Envoyer l'email avec la facture en pièce jointe
    $email_envoye = envoyerEmail($email_client, $sujet, $message, $pdf_path);
    
    if ($email_envoye) {
        $msg = 'generee_email';
    } else {
        $msg = 'generee_noemail';
    }
} else {
    $msg = 'generee';
}

// Rediriger
header('Location: factures.php?msg=' . $msg);
exit;
?>