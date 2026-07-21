<?php
// ============================================
// GÉNÉRER FACTURE AVEC EMAIL - AWA KA SUGU
// DESIGN PREMIUM POUR EMAIL
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
// EMAIL AVEC NOUVEAU DESIGN ÉLÉGANT
// ============================================

require_once dirname(__DIR__) . '/includes/envoi_email.php';

if (!empty($email_client)) {
    $sujet = "📄 Votre facture Awa Ka Sugu - N° " . $numero_facture;
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facture Awa Ka Sugu</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap");
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                background: #F5F7FA;
                padding: 24px;
                margin: 0;
                -webkit-font-smoothing: antialiased;
            }
            
            .email-wrapper {
                max-width: 640px;
                margin: 0 auto;
                background: #FFFFFF;
                border-radius: 24px;
                overflow: hidden;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08), 0 8px 20px rgba(0, 0, 0, 0.04);
            }
            
            /* ===== HEADER ===== */
            .email-header {
                background: #0C0C14;
                padding: 40px 35px 28px;
                text-align: center;
                border-bottom: 4px solid #C8922A;
                position: relative;
            }
            
            .email-header::before {
                content: "✦";
                position: absolute;
                top: -15px;
                right: -5px;
                font-size: 8rem;
                opacity: 0.04;
                color: #C8922A;
            }
            
            .email-header .brand {
                font-family: "Playfair Display", serif;
                font-size: 2rem;
                font-weight: 700;
                color: #F5F0E8;
                letter-spacing: 3px;
            }
            
            .email-header .brand span {
                color: #C8922A;
            }
            
            .email-header .tagline {
                color: rgba(255, 255, 255, 0.3);
                font-size: 0.7rem;
                letter-spacing: 4px;
                text-transform: uppercase;
                margin-top: 4px;
            }
            
            .email-header .separator {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 12px;
                margin-top: 14px;
            }
            
            .email-header .separator .line {
                width: 35px;
                height: 1px;
                background: linear-gradient(90deg, transparent, #C8922A, transparent);
            }
            
            .email-header .separator .diamond {
                width: 6px;
                height: 6px;
                background: #C8922A;
                transform: rotate(45deg);
            }
            
            /* ===== BODY ===== */
            .email-body {
                padding: 35px 35px 25px;
            }
            
            .greeting {
                font-size: 1.25rem;
                font-weight: 600;
                color: #0C0C14;
                margin-bottom: 4px;
            }
            
            .greeting span {
                color: #C8922A;
            }
            
            .sub-greeting {
                color: #8A99AA;
                font-size: 0.9rem;
                margin-bottom: 22px;
            }
            
            /* ===== CARTE INFO ===== */
            .info-card {
                background: #F8F9FA;
                border-radius: 16px;
                padding: 20px 24px;
                border-left: 4px solid #C8922A;
                margin: 16px 0 20px;
            }
            
            .info-card .row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #EDEDF0;
            }
            
            .info-card .row:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }
            
            .info-card .row .label {
                color: #8A99AA;
                font-size: 0.82rem;
                font-weight: 500;
            }
            
            .info-card .row .label i {
                margin-right: 6px;
                color: #C8922A;
            }
            
            .info-card .row .value {
                font-weight: 600;
                color: #0C0C14;
                font-size: 0.9rem;
            }
            
            .info-card .row .value.total {
                color: #C8922A;
                font-size: 1.25rem;
                font-family: "Playfair Display", serif;
            }
            
            .info-card .row.highlight {
                border-top: 2px solid #C8922A;
                padding-top: 12px;
                margin-top: 4px;
            }
            
            /* ===== TABLEAU PRODUITS (version email) ===== */
            .products-table {
                width: 100%;
                border-collapse: collapse;
                margin: 16px 0 12px;
                font-size: 0.85rem;
            }
            
            .products-table thead th {
                background: #0C0C14;
                color: #C8922A;
                padding: 10px 14px;
                text-align: left;
                font-weight: 600;
                font-size: 0.7rem;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .products-table thead th:last-child {
                text-align: right;
            }
            
            .products-table tbody td {
                padding: 10px 14px;
                border-bottom: 1px solid #EDEDF0;
                color: #1A2C3E;
            }
            
            .products-table tbody td:last-child {
                text-align: right;
                font-weight: 600;
                color: #0C0C14;
            }
            
            .products-table tfoot td {
                padding: 12px 14px;
                font-weight: 700;
                font-size: 1rem;
                border-top: 2px solid #C8922A;
            }
            
            .products-table tfoot td:last-child {
                color: #C8922A;
                font-family: "Playfair Display", serif;
                font-size: 1.2rem;
                text-align: right;
            }
            
            /* ===== BOUTON ===== */
            .btn-download {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                background: linear-gradient(135deg, #C8922A, #E8B55A);
                color: #0C0C14;
                padding: 14px 40px;
                border-radius: 50px;
                text-decoration: none;
                font-weight: 700;
                font-size: 0.95rem;
                transition: all 0.3s ease;
                margin: 12px 0 6px;
                box-shadow: 0 4px 20px rgba(200, 146, 42, 0.25);
                border: none;
            }
            
            .btn-download:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 30px rgba(200, 146, 42, 0.35);
                color: #FFFFFF;
            }
            
            .btn-download i {
                font-size: 1.1rem;
            }
            
            .btn-wrapper {
                text-align: center;
                margin: 10px 0;
            }
            
            /* ===== REMERCIEMENTS ===== */
            .thanks-section {
                text-align: center;
                margin-top: 24px;
                padding-top: 24px;
                border-top: 1px solid #EDEDF0;
            }
            
            .thanks-section .heart {
                color: #E74C3C;
                font-size: 1.1rem;
            }
            
            .thanks-section .message {
                color: #8A99AA;
                font-size: 0.9rem;
                margin: 4px 0;
            }
            
            .thanks-section .message strong {
                color: #0C0C14;
            }
            
            .thanks-section .signature {
                font-family: "Playfair Display", serif;
                font-style: italic;
                color: #C8922A;
                font-size: 1rem;
                margin-top: 6px;
            }
            
            /* ===== FOOTER ===== */
            .email-footer {
                background: #F8F9FA;
                padding: 24px 35px;
                text-align: center;
                border-top: 1px solid #EDEDF0;
            }
            
            .email-footer p {
                color: #8A99AA;
                font-size: 0.75rem;
                margin: 2px 0;
            }
            
            .email-footer a {
                color: #C8922A;
                text-decoration: none;
            }
            
            .email-footer a:hover {
                text-decoration: underline;
            }
            
            .email-footer .social-links {
                display: flex;
                justify-content: center;
                gap: 14px;
                margin: 10px 0 8px;
            }
            
            .email-footer .social-links a {
                color: #8A99AA;
                font-size: 1.1rem;
                transition: color 0.3s;
            }
            
            .email-footer .social-links a:hover {
                color: #C8922A;
                text-decoration: none;
            }
            
            .email-footer .disclaimer {
                font-size: 0.6rem;
                color: #B8BEC6;
                margin-top: 6px;
            }
            
            /* ===== RESPONSIVE ===== */
            @media (max-width: 520px) {
                body {
                    padding: 12px;
                }
                .email-body {
                    padding: 20px;
                }
                .email-header {
                    padding: 28px 20px 20px;
                }
                .email-header .brand {
                    font-size: 1.5rem;
                }
                .info-card {
                    padding: 14px 16px;
                }
                .info-card .row {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 2px;
                    padding: 6px 0;
                }
                .info-card .row .value {
                    width: 100%;
                }
                .products-table {
                    font-size: 0.75rem;
                }
                .products-table thead th,
                .products-table tbody td {
                    padding: 6px 8px;
                }
                .btn-download {
                    padding: 12px 25px;
                    font-size: 0.85rem;
                    width: 100%;
                    justify-content: center;
                }
                .email-footer {
                    padding: 18px 20px;
                }
                .email-footer .social-links {
                    flex-wrap: wrap;
                }
            }
            
            @media (max-width: 380px) {
                .products-table thead th,
                .products-table tbody td {
                    font-size: 0.65rem;
                    padding: 4px 6px;
                }
                .products-table tfoot td {
                    font-size: 0.85rem;
                }
                .products-table tfoot td:last-child {
                    font-size: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <!-- HEADER -->
            <div class="email-header">
                <div class="brand">AWA KA <span>SUGU</span></div>
                <div class="tagline">✦ Artisanat d&rsquo;exception ✦</div>
                <div class="separator">
                    <span class="line"></span>
                    <span class="diamond"></span>
                    <span class="line"></span>
                </div>
            </div>

            <!-- BODY -->
            <div class="email-body">
                <div class="greeting">
                    Bonjour <span>' . htmlspecialchars($commande['nom_client']) . '</span> 👋
                </div>
                <div class="sub-greeting">
                    Nous vous remercions pour votre confiance. Voici le récapitulatif de votre commande.
                </div>

                <!-- CARTE INFO CLIENT -->
                <div class="info-card">
                    <div class="row">
                        <span class="label"><i>📄</i> N° Facture</span>
                        <span class="value">' . $numero_facture . '</span>
                    </div>
                    <div class="row">
                        <span class="label"><i>📦</i> N° Commande</span>
                        <span class="value">' . $commande['numero_commande'] . '</span>
                    </div>
                    <div class="row">
                        <span class="label"><i>📅</i> Date</span>
                        <span class="value">' . date('d/m/Y à H:i', strtotime($commande['created_at'])) . '</span>
                    </div>
                    <div class="row">
                        <span class="label"><i>📍</i> Adresse</span>
                        <span class="value">' . nl2br(htmlspecialchars($commande['adresse_livraison'])) . '</span>
                    </div>
                    <div class="row">
                        <span class="label"><i>💳</i> Paiement</span>
                        <span class="value">' . $mode_label . '</span>
                    </div>
                    <div class="row highlight">
                        <span class="label" style="font-weight:700;color:#0C0C14;">💰 Montant total</span>
                        <span class="value total">' . number_format($commande['total'], 0, ',', ' ') . ' FCFA</span>
                    </div>
                </div>

                <!-- TABLEAU DES PRODUITS -->
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th style="text-align:center;">Qté</th>
                            <th style="text-align:right;">Prix</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
                    foreach($details as $d) {
                        $total_ligne = $d['quantite'] * $d['prix_unitaire'];
                        $message .= '
                        <tr>
                            <td>' . htmlspecialchars($d['nom_produit']) . '</td>
                            <td style="text-align:center;">' . $d['quantite'] . '</td>
                            <td style="text-align:right;">' . number_format($d['prix_unitaire'], 0, ',', ' ') . ' F</td>
                            <td style="text-align:right;">' . number_format($total_ligne, 0, ',', ' ') . ' F</td>
                        </tr>';
                    }
                    
                    $message .= '
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right;">TOTAL</td>
                            <td style="text-align:right;">' . number_format($commande['total'], 0, ',', ' ') . ' FCFA</td>
                        </tr>
                    </tfoot>
                </table>

                <!-- BOUTON TÉLÉCHARGEMENT -->
                <div class="btn-wrapper">
                    <a href="' . SITE_URL . '/uploads/factures/' . basename($pdf_path) . '" class="btn-download" target="_blank">
                        <i>📄</i> Télécharger ma facture
                    </a>
                </div>

                <!-- REMERCIEMENTS -->
                <div class="thanks-section">
                    <p class="message">
                        <span class="heart">❤️</span> 
                        Merci d&rsquo;avoir choisi <strong>Awa Ka Sugu</strong>
                    </p>
                    <p class="message" style="font-size:0.85rem;color:#B8BEC6;">
                        Nous espérons vous revoir bientôt !
                    </p>
                    <div class="signature">— Awa Doumbia</div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="email-footer">
                <p>© ' . date('Y') . ' <strong>Awa Ka Sugu</strong> — Tous droits réservés</p>
                <p style="font-size:0.7rem;color:#B8BEC6;">
                    <a href="' . SITE_URL . '">' . SITE_URL . '</a> &bull; 
                    <a href="mailto:contact@awakasugu.com">contact@awakasugu.com</a>
                </p>
                <div class="social-links">
                    <a href="https://www.instagram.com/awadoumbia223" target="_blank">📸</a>
                    <a href="https://www.tiktok.com/@awadoumbia223" target="_blank">🎵</a>
                    <a href="https://www.facebook.com/awadoumbia223" target="_blank">📘</a>
                    <a href="https://wa.me/22366746985" target="_blank">💬</a>
                </div>
                <p class="disclaimer">
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

header('Location: factures.php?msg=' . $msg);
exit;
?>