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

    // ── En-tête : bandeau sombre, accent or, monogramme de la marque ──
    function Header() {
        $this->SetFillColor(13, 13, 13);
        $this->Rect(0, 0, 210, 46, 'F');

        // Bande dorée gauche (accent vertical)
        $this->SetFillColor(200, 146, 42);
        $this->Rect(0, 0, 6, 46, 'F');

        // Nom boutique
        $this->SetXY(16, 8);
        $this->SetFont('Times', 'B', 21);
        $this->SetTextColor(200, 146, 42);
        $this->Cell(130, 10, 'AWA KA SUGU', 0, 1, 'L');

        // Sous-titre boutique
        $this->SetX(16);
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(180, 155, 100);
        $this->Cell(130, 6, 'Boutique IBA Design  |  Restaurant Sofia', 0, 1, 'L');

        // Coordonnées
        $this->SetX(16);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(130, 115, 80);
        $this->Cell(130, 5, 'Sebenikoro Koro, Bamako - Mali   |   +223 77 77 43 43   |   contact@awakasugu.com', 0, 1, 'L');

        // Monogramme de la marque — pastille or "ID" en haut à droite du bandeau
        $badgeX = 174; $badgeY = 9; $badgeD = 22;
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.5);
        $this->RoundedRect($badgeX, $badgeY, $badgeD, $badgeD, 4, 'D');
        $this->SetXY($badgeX, $badgeY + 6.3);
        $this->SetFont('Times', 'B', 15);
        $this->SetTextColor(200, 146, 42);
        $this->Cell($badgeD, 8, 'ID', 0, 0, 'C');

        // Séparateur bas du header
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.8);
        $this->Line(0, 46, 210, 46);
        $this->Ln(8);
    }

    function Footer() {
        $this->SetY(-18);
        $this->SetDrawColor(200, 146, 42);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(140, 120, 75);
        $this->Cell(0, 5, 'Awa Ka Sugu  -  Boutique IBA Design & Restaurant Sofia   |   Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }

    // ── Rectangle à coins arrondis (fond premium des blocs d'information) ──
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k; $hp = $this->h;
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF') $op = 'B';
        else $op = 'S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x+$r)*$k, ($hp-$y)*$k));
        $xc = $x+$w-$r; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-$y)*$k));
        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', ($x)*$k, ($hp-$yc)*$k));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }
    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k, $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
}

$pdf = new FacturePDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 28);
$pdf->SetMargins(15, 54, 15);

// ── TITRE + NUMÉRO ──
$pdf->SetFont('Times', 'B', 30);
$pdf->SetTextColor(13, 13, 13);
$pdf->Cell(0, 14, 'FACTURE', 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(160, 140, 90);
$pdf->Cell(0, 6, 'N' . chr(176) . ' ' . $numero_facture, 0, 1, 'C');
$pdf->Ln(8);

// ── BLOC CLIENT : layout 2 colonnes, cartes arrondies ──
$colLeft  = 15;
$colRight = 110;
$colW1    = 88;
$colW2    = 85;
$blockY   = $pdf->GetY();
$rowH     = 8;
$cardR    = 3; // rayon des coins arrondis

// Carte gauche (client)
$pdf->SetFillColor(249, 249, 251);
$pdf->SetDrawColor(200, 146, 42);
$pdf->SetLineWidth(0.3);
$pdf->RoundedRect($colLeft, $blockY, $colW1, 58, $cardR, 'DF');

// Carte droite (détails commande) — même style, pour une paire cohérente
$pdf->RoundedRect($colRight, $blockY, $colW2, 58, $cardR, 'DF');

// Filet or sous chaque titre de carte
$pdf->SetDrawColor(200, 146, 42);
$pdf->SetLineWidth(0.25);
$pdf->Line($colLeft + 4, $blockY + 9.5, $colLeft + colW1 - 4, $blockY + 9.5);
$pdf->Line($colRight + 4, $blockY + 9.5, $colRight + colW2 - 4, $blockY + 9.5);

// Titre colonne gauche
$pdf->SetXY($colLeft + 4, $blockY + 3.5);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(200, 146, 42);
$pdf->Cell($colW1 - 8, 6, 'INFORMATIONS CLIENT', 0, 1, 'L');

// Titre colonne droite
$pdf->SetXY($colRight + 4, $blockY + 3.5);
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetTextColor(200, 146, 42);
$pdf->Cell($colW2 - 8, 6, 'DETAILS DE LA COMMANDE', 0, 1, 'L');

// Données gauche
$modes = [
    'livraison'    => 'Paiement a la livraison',
    'orange_money' => 'Orange Money',
    'wave'         => 'Wave',
    'moov_money'   => 'Moov Money',
    'carte'        => 'Carte bancaire',
    'especes'      => 'Especes'
];

$info_client = [
    'Nom'      => $commande['nom_client'],
    'Tel'      => $commande['telephone'],
    'Adresse'  => $commande['adresse_livraison'],
];
if (!empty($email_client)) {
    $info_client['Email'] = $email_client;
}

$yy = $blockY + 14;
foreach ($info_client as $lbl => $val) {
    $pdf->SetXY($colLeft + 4, $yy);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(140, 120, 80);
    $pdf->Cell(22, $rowH, $lbl . ' :', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->MultiCell($colW1 - 30, $rowH, $val, 0, 'L');
    $yy = $pdf->GetY();
    if ($yy - $blockY > 52) break; // sécurité anti-débordement de la carte
}

// Données droite
$info_cmd = [
    'Date'      => date('d/m/Y a H:i', strtotime($commande['created_at'])),
    'Paiement'  => ($modes[$commande['mode_paiement']] ?? $commande['mode_paiement']),
    'N. Cmd'    => '#' . ($commande['numero_commande'] ?? $commande['id']),
];

$yy2 = $blockY + 14;
foreach ($info_cmd as $lbl => $val) {
    $pdf->SetXY($colRight + 4, $yy2);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(140, 120, 80);
    $pdf->Cell(22, $rowH, $lbl . ' :', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell($colW2 - 30, $rowH, $val, 0, 1, 'L');
    $yy2 += $rowH;
}

$pdf->SetY($blockY + 66);

// ── TABLEAU DES PRODUITS ──
$pdf->Ln(2);

// En-tête tableau (fond plein, sans grille — plus épuré)
$pdf->SetFillColor(13, 13, 13);
$pdf->SetTextColor(200, 146, 42);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 11, '  PRODUIT', 0, 0, 'L', true);
$pdf->Cell(22, 11, 'QTE', 0, 0, 'C', true);
$pdf->Cell(36, 11, 'PRIX UNIT.', 0, 0, 'R', true);
$pdf->Cell(32, 11, 'TOTAL  ', 0, 1, 'R', true);

// Lignes produits — séparateur discret sous chaque ligne, pas de grille verticale
$pdf->SetDrawColor(232, 233, 237);
$pdf->SetLineWidth(0.15);
$pdf->SetFont('Arial', '', 9);
$total_lignes = 0;
$fill = false;

foreach ($details as $d) {
    $total_ligne = $d['quantite'] * $d['prix_unitaire'];
    $total_lignes += $total_ligne;

    $nom_produit = $d['nom_produit'];
    if (strlen($nom_produit) > 44) $nom_produit = substr($nom_produit, 0, 42) . '..';

    $bg = $fill ? 248 : 255;
    $pdf->SetFillColor($bg, $bg, $fill ? 252 : 255);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell(90, 9.5, '  ' . $nom_produit, 'B', 0, 'L', $fill);
    $pdf->Cell(22, 9.5, $d['quantite'], 'B', 0, 'C', $fill);
    $pdf->SetTextColor(120, 108, 80);
    $pdf->Cell(36, 9.5, number_format($d['prix_unitaire'], 0, ',', ' ') . ' FCFA', 'B', 0, 'R', $fill);
    $pdf->SetTextColor(200, 146, 42);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(32, 9.5, number_format($total_ligne, 0, ',', ' ') . ' FCFA  ', 'B', 1, 'R', $fill);
    $pdf->SetFont('Arial', '', 9);
    $fill = !$fill;
}

// ── TOTAL ── (bandeau or, coins arrondis)
$pdf->Ln(5);
$totalY = $pdf->GetY();
$pdf->SetFillColor(200, 146, 42);
$pdf->RoundedRect(15, $totalY, 180, 15, 3, 'F');
$pdf->SetXY(15, $totalY + 3.2);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(42, 31, 12);
$pdf->Cell(120, 8, '   MONTANT TOTAL', 0, 0, 'L');
$pdf->SetFont('Times', 'B', 15);
$pdf->SetTextColor(26, 18, 0);
$pdf->Cell(60, 8, number_format($commande['total'], 0, ',', ' ') . ' FCFA   ', 0, 1, 'R');
$pdf->SetY($totalY + 15);

// ── NOTES ──
if (!empty($commande['notes'])) {
    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(80, 70, 50);
    $pdf->Cell(0, 7, 'Notes :', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(100, 90, 70);
    $pdf->MultiCell(0, 5, $commande['notes'], 0, 'L');
}

// ── MESSAGE FINAL ──
$pdf->Ln(10);
$pdf->SetFont('Times', 'B', 13);
$pdf->SetTextColor(200, 146, 42);
$pdf->Cell(0, 8, 'Merci pour votre confiance !', 0, 1, 'C');
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(130, 110, 70);
$pdf->Cell(0, 6, 'Nous serons heureux de vous accueillir a nouveau chez Awa Ka Sugu.', 0, 1, 'C');

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
        $sujet = "Votre facture Awa Ka Sugu — N° " . $numero_facture;

        // ── Lignes d'articles pour le récapitulatif dans l'email ──
        $lignes_articles = '';
        foreach ($details as $d) {
            $total_ligne_email = $d['quantite'] * $d['prix_unitaire'];
            $lignes_articles .= '
                <tr>
                    <td style="padding:12px 0;border-bottom:1px solid #F5F6F8;font-family:Arial,sans-serif;font-size:12.5px;color:#1A1A2E;">
                        ' . htmlspecialchars($d['nom_produit']) . '<br>
                        <span style="font-size:10.5px;color:#9099A8;">' . (int)$d['quantite'] . ' &times; ' . number_format($d['prix_unitaire'], 0, ',', ' ') . ' FCFA</span>
                    </td>
                    <td align="right" valign="top" style="padding:12px 0;border-bottom:1px solid #F5F6F8;font-family:Arial,sans-serif;font-size:12.5px;font-weight:bold;color:#C8922A;white-space:nowrap;">' . number_format($total_ligne_email, 0, ',', ' ') . ' FCFA</td>
                </tr>';
        }

        $message_html = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture Awa Ka Sugu</title>
        </head>
        <body style="margin:0;padding:0;background-color:#EFEFF2;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EFEFF2;">
        <tr><td align="center" style="padding:32px 12px;">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

          <tr><td style="background-color:#C8922A;font-size:0;line-height:4px;height:4px;">&nbsp;</td></tr>

          <tr><td style="background-color:#0D0D0D;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td align="center" style="padding:36px 30px 28px;">

                <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
                  <td style="width:58px;height:58px;border-radius:50%;border:1.5px solid #C8922A;background-color:#161310;text-align:center;vertical-align:middle;font-family:Georgia,\'Times New Roman\',serif;font-weight:bold;font-size:21px;color:#C8922A;">ID</td>
                </tr></table>

                <div style="height:16px;line-height:16px;font-size:1px;">&nbsp;</div>
                <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:25px;font-weight:bold;letter-spacing:4px;color:#C8922A;text-transform:uppercase;">Awa Ka Sugu</div>
                <div style="height:9px;line-height:9px;font-size:1px;">&nbsp;</div>
                <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr><td style="width:44px;height:1px;background-color:#4a3a20;font-size:0;line-height:0;">&nbsp;</td></tr></table>
                <div style="height:11px;line-height:11px;font-size:1px;">&nbsp;</div>
                <div style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:2px;color:#8a8378;text-transform:uppercase;">Boutique IBA Design &nbsp;&middot;&nbsp; Restaurant Sofia</div>
                <div style="height:20px;line-height:20px;font-size:1px;">&nbsp;</div>

                <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
                  <td style="background-color:#231A0E;border:1px solid #4a3a20;border-radius:20px;padding:8px 22px;font-family:Arial,sans-serif;font-size:11px;color:#E8C070;letter-spacing:1.5px;text-transform:uppercase;font-weight:bold;">Votre facture</td>
                </tr></table>

              </td>
            </tr></table>
          </td></tr>

          <tr><td style="padding:36px 34px 8px;">
            <p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:19px;font-weight:bold;color:#0D0D0D;">Bonjour ' . htmlspecialchars($commande['nom_client']) . ',</p>
            <p style="margin:0 0 26px;font-family:Arial,sans-serif;font-size:13.5px;color:#6A7585;line-height:1.7;">Merci pour votre commande chez Awa Ka Sugu. Voici le récapitulatif de votre facture — le document PDF complet est joint à cet email.</p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F9F9FB;border:1px solid #ECEDF1;border-radius:12px;margin-bottom:22px;">
              <tr><td style="background-color:#0D0D0D;padding:13px 20px;border-radius:11px 11px 0 0;border-bottom:2px solid #C8922A;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8a8378;">Récapitulatif</td>
                  <td align="right" style="font-family:Georgia,serif;font-size:13px;font-weight:bold;color:#C8922A;">' . $numero_facture . '</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:13px 20px;border-bottom:1px solid #F0F1F4;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Arial,sans-serif;font-size:12.5px;color:#8A92A3;">N&deg; Commande</td>
                  <td align="right" style="font-family:Arial,sans-serif;font-size:12.5px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars($commande['numero_commande'] ?? ('#' . $commande['id'])) . '</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:13px 20px;border-bottom:1px solid #F0F1F4;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Arial,sans-serif;font-size:12.5px;color:#8A92A3;">Date</td>
                  <td align="right" style="font-family:Arial,sans-serif;font-size:12.5px;font-weight:bold;color:#1A1A2E;">' . date('d/m/Y à H:i', strtotime($commande['created_at'])) . '</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:13px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td valign="top" style="font-family:Arial,sans-serif;font-size:12.5px;color:#8A92A3;width:42%;">Adresse de livraison</td>
                  <td align="right" style="font-family:Arial,sans-serif;font-size:12.5px;font-weight:bold;color:#1A1A2E;">' . nl2br(htmlspecialchars($commande['adresse_livraison'])) . '</td>
                </tr></table>
              </td></tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
              <tr><td colspan="2" style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8A92A3;padding-bottom:8px;border-bottom:1px solid #E8E9ED;">Articles commandés</td></tr>
              ' . $lignes_articles . '
            </table>

            <div style="height:14px;line-height:14px;font-size:1px;">&nbsp;</div>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#C8922A;border-radius:10px;margin-bottom:26px;">
              <tr><td style="padding:17px 22px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#2A1F0C;">Montant total</td>
                  <td align="right" style="font-family:Georgia,serif;font-size:23px;font-weight:bold;color:#1A1200;">' . number_format($commande['total'], 0, ',', ' ') . ' FCFA</td>
                </tr></table>
              </td></tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;"><tr><td align="center">
              <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background-color:#0D0D0D;border:1.5px solid #C8922A;border-radius:26px;">
                <a href="' . SITE_URL . '/uploads/factures/' . basename($pdf_path) . '" style="display:inline-block;padding:14px 34px;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;color:#C8922A;text-decoration:none;letter-spacing:0.5px;" target="_blank">Télécharger ma facture &rarr;</a>
              </td></tr></table>
              <p style="margin:10px 0 0;font-family:Arial,sans-serif;font-size:11px;color:#A0A8B4;">Document PDF également joint à cet email</p>
            </td></tr></table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #F0F1F4;margin-top:14px;"><tr><td align="center" style="padding:22px 0 6px;">
              <p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:13px;color:#4A5568;">Merci pour votre confiance en <strong style="color:#C8922A;">Awa Ka Sugu</strong>.</p>
              <p style="margin:0;font-family:Georgia,serif;font-style:italic;font-size:14px;color:#9A6E1A;">&mdash; Awa Doumbia</p>
            </td></tr></table>

          </td></tr>

          <tr><td style="background-color:#0A0A0A;padding:24px 30px;text-align:center;">
            <div style="font-family:Georgia,serif;font-size:13px;font-weight:bold;color:#C8922A;margin-bottom:6px;">Awa Ka Sugu</div>
            <div style="font-family:Arial,sans-serif;font-size:10px;color:#5c5c5c;">Boutique IBA Design &nbsp;&middot;&nbsp; Restaurant Sofia &nbsp;&middot;&nbsp; contact@awakasugu.com</div>
            <div style="font-family:Arial,sans-serif;font-size:9px;color:#3a3a3a;margin-top:10px;">&copy; ' . date('Y') . ' Awa Ka Sugu &mdash; Tous droits réservés. Email automatique, merci de ne pas y répondre.</div>
          </td></tr>

        </table>

        </td></tr>
        </table>
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