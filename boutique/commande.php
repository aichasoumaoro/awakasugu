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

// ============================================
// INCLURE LES FONCTIONS DU PANIER
// ============================================
require_once '../includes/panier_fonctions.php';

// ============================================
// ✅ PRISE EN CHARGE DU BOUTON "COMMANDER" DIRECT
// ============================================
// Si un produit_id est passé en GET (commande directe)
$produit_direct = null;
$quantite_directe = 1;
$couleur_id_direct = null;
$taille_id_direct = null;

if (isset($_GET['produit_id']) && !empty($_GET['produit_id'])) {
    $produit_id = (int)$_GET['produit_id'];
    $quantite_directe = isset($_GET['quantite']) ? (int)$_GET['quantite'] : 1;
    $couleur_id_direct = isset($_GET['couleur_id']) ? (int)$_GET['couleur_id'] : null;
    $taille_id_direct = isset($_GET['taille_id']) ? (int)$_GET['taille_id'] : null;
    
    // Connexion pour récupérer le produit
    $host = 'localhost';
    $dbname = 'awakasugu_db';
    $user = 'root';
    $pass = '';
    
    try {
        $pdo_temp = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo_temp->prepare("SELECT * FROM produits WHERE id = ? AND est_visible = 1");
        $stmt->execute([$produit_id]);
        $produit_direct = $stmt->fetch();
        
        if ($produit_direct) {
            // Vider le panier actuel
            $_SESSION['panier'] = [];
            
            $prix = ($produit_direct['prix_promo'] && $produit_direct['prix_promo'] > 0 && $produit_direct['prix_promo'] < $produit_direct['prix']) 
                    ? $produit_direct['prix_promo'] 
                    : $produit_direct['prix'];
            
            // Récupérer les noms de couleur et taille
            $couleur_nom = '';
            $taille_nom = '';
            $couleur_hex = '';
            
            if ($couleur_id_direct) {
                $stmt = $pdo_temp->prepare("SELECT nom, code_hex FROM couleurs WHERE id = ?");
                $stmt->execute([$couleur_id_direct]);
                $c = $stmt->fetch();
                $couleur_nom = $c['nom'] ?? '';
                $couleur_hex = $c['code_hex'] ?? '';
            }
            
            if ($taille_id_direct) {
                $stmt = $pdo_temp->prepare("SELECT nom FROM tailles WHERE id = ?");
                $stmt->execute([$taille_id_direct]);
                $t = $stmt->fetch();
                $taille_nom = $t['nom'] ?? '';
            }
            
            // Ajouter le produit directement dans le panier
            $_SESSION['panier'][] = [
                'id' => $produit_direct['id'],
                'nom' => $produit_direct['nom'],
                'prix' => $prix,
                'quantite' => $quantite_directe,
                'couleur_id' => $couleur_id_direct,
                'couleur_nom' => $couleur_nom,
                'couleur_hex' => $couleur_hex,
                'taille_id' => $taille_id_direct,
                'taille_nom' => $taille_nom,
                'image' => $produit_direct['image_principale']
            ];
        }
    } catch(PDOException $e) {
        // Ignorer l'erreur
    }
}

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

// ============================================
// RÉCUPÉRER LES PARAMÈTRES DE FIDÉLITÉ
// ============================================
$stmt = $pdo->query("SELECT cle, valeur FROM parametres_fonctionnalites WHERE cle LIKE 'fidelite_%'");
$params_fidelite = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$seuil_points = $params_fidelite['fidelite_seuil_points'] ?? 50000;
$points_par_seuil = $params_fidelite['fidelite_points_par_seuil'] ?? 1;
$fidelite_actif = $params_fidelite['fidelite_actif'] ?? 1;

// Calcul du total
$total = 0;
foreach ($_SESSION['panier'] as $item) {
    $total += $item['prix'] * $item['quantite'];
}

// ============================================
// APPLIQUER LES RÉDUCTIONS (CODE PROMO + POINTS)
// ============================================
$reduction_appliquee = 0;
$code_promo_info = $_SESSION['code_promo'] ?? null;
if ($code_promo_info) {
    $reduction_appliquee = $code_promo_info['reduction'] ?? 0;
}

$reduction_points_montant = 0;
$points_utilises = 0;
if (isset($_SESSION['reduction_points'])) {
    $points_utilises = $_SESSION['reduction_points']['points_utilises'] ?? 0;
    $reduction_points_montant = $_SESSION['reduction_points']['montant'] ?? 0;
}

$total_apres_reductions = $total - $reduction_appliquee - $reduction_points_montant;
if ($total_apres_reductions < 0) $total_apres_reductions = 0;

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

    // Helper UTF-8 → ISO-8859-1 (FPDF natif)
    $u = function($txt) { return utf8_decode($txt); };

    // Classe locale avec Header/Footer premium
    $pdf = new class('P', 'mm', 'A4') extends FPDF {
        function Header() {
            // Bandeau noir
            $this->SetFillColor(15, 15, 15);
            $this->Rect(0, 0, 210, 45, 'F');

            // Barre dorée verticale gauche
            $this->SetFillColor(198, 146, 42);
            $this->Rect(0, 0, 5, 45, 'F');

            // Nom boutique
            $this->SetXY(15, 8);
            $this->SetFont('Times', 'B', 26);
            $this->SetTextColor(198, 146, 42);
            $this->Cell(140, 12, 'AWA KA SUGU', 0, 1, 'L');

            // Sous-titre
            $this->SetX(15);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(220, 200, 150);
            $this->Cell(140, 5, 'Boutique IBA Design  |  Restaurant Sofia', 0, 1, 'L');

            // Coordonnées
            $this->SetX(15);
            $this->SetFont('Arial', '', 7.5);
            $this->SetTextColor(170, 160, 140);
            $this->Cell(140, 5, 'Sebenikoro Koro, Bamako - Mali  |  +223 77 77 43 43  |  contact@awakasugu.com', 0, 1, 'L');

            // Badge ID carré doré
            $this->SetFillColor(198, 146, 42);
            $this->Rect(178, 11, 20, 20, 'F');
            $this->SetXY(178, 15);
            $this->SetFont('Times', 'B', 14);
            $this->SetTextColor(15, 15, 15);
            $this->Cell(20, 12, 'ID', 0, 0, 'C');

            // Ligne dorée sous bandeau
            $this->SetDrawColor(198, 146, 42);
            $this->SetLineWidth(0.8);
            $this->Line(0, 45, 210, 45);

            $this->SetY(52);
        }

        function Footer() {
            $this->SetY(-18);
            $this->SetDrawColor(198, 146, 42);
            $this->SetLineWidth(0.3);
            $this->Line(15, $this->GetY(), 195, $this->GetY());
            $this->Ln(3);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(140, 120, 75);
            $this->Cell(0, 5, 'Awa Ka Sugu  -  Boutique IBA Design & Restaurant Sofia  |  Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
        }
    };

    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 28);
    $pdf->SetMargins(15, 52, 15);

    // ── TITRE ──
    $pdf->SetFont('Times', 'B', 30);
    $pdf->SetTextColor(15, 15, 15);
    $pdf->Cell(0, 14, $u('FACTURE'), 0, 1, 'C');

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(160, 140, 90);
    $pdf->Cell(0, 6, $u('N° ') . $numero_facture, 0, 1, 'C');
    $pdf->Ln(8);

    // ── BLOCS CLIENT / COMMANDE ──
    $colLeft  = 15;
    $colRight = 110;
    $colW1    = 88;
    $colW2    = 85;
    $blockY   = $pdf->GetY();
    $rowH     = 8;

    // Cadre gauche
    $pdf->SetFillColor(249, 249, 251);
    $pdf->SetDrawColor(198, 146, 42);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect($colLeft, $blockY, $colW1, 58, 'DF');

    // Cadre droit
    $pdf->Rect($colRight, $blockY, $colW2, 58, 'DF');

    // Titres
    $pdf->SetXY($colLeft + 4, $blockY + 3);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(198, 146, 42);
    $pdf->Cell($colW1 - 8, 6, $u('INFORMATIONS CLIENT'), 0, 1, 'L');

    $pdf->SetXY($colRight + 4, $blockY + 3);
    $pdf->Cell($colW2 - 8, 6, $u('DETAILS DE LA COMMANDE'), 0, 1, 'L');

    // Lignes dorées sous titres
    $pdf->SetDrawColor(198, 146, 42);
    $pdf->SetLineWidth(0.25);
    $pdf->Line($colLeft + 4, $blockY + 10, $colLeft + $colW1 - 4, $blockY + 10);
    $pdf->Line($colRight + 4, $blockY + 10, $colRight + $colW2 - 4, $blockY + 10);

    // Données client
    $modes = [
        'livraison'    => 'Paiement a la livraison',
        'orange_money' => 'Orange Money',
        'wave'         => 'Wave',
        'moov_money'   => 'Moov Money',
        'carte'        => 'Carte bancaire',
        'especes'      => 'Especes'
    ];

    $info_client = [
        'Nom'     => $commande['nom_client'],
        'Tel'     => $commande['telephone'],
        'Adresse' => $commande['adresse_livraison'],
    ];
    if (!empty($commande['email'])) {
        $info_client['Email'] = $commande['email'];
    }

    $yy = $blockY + 14;
    foreach ($info_client as $lbl => $val) {
        $pdf->SetXY($colLeft + 4, $yy);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(140, 120, 80);
        $pdf->Cell(22, $rowH, $u($lbl . ' :'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell($colW1 - 30, $rowH, $u($val), 0, 'L');
        $yy = $pdf->GetY();
        if ($yy - $blockY > 52) break;
    }

    // Données commande
    $info_cmd = [
        'Date'     => date('d/m/Y a H:i', strtotime($commande['created_at'])),
        'Paiement' => ($modes[$commande['mode_paiement']] ?? $commande['mode_paiement']),
        'N. Cmd'   => '#' . ($commande['numero_commande'] ?? $commande['id']),
    ];

    $yy2 = $blockY + 14;
    foreach ($info_cmd as $lbl => $val) {
        $pdf->SetXY($colRight + 4, $yy2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(140, 120, 80);
        $pdf->Cell(22, $rowH, $u($lbl . ' :'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell($colW2 - 30, $rowH, $u($val), 0, 1, 'L');
        $yy2 += $rowH;
    }

    $pdf->SetY($blockY + 66);

    // ── TABLEAU PRODUITS ──
    $pdf->Ln(2);

    $pdf->SetFillColor(15, 15, 15);
    $pdf->SetTextColor(198, 146, 42);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(90, 11, $u('  PRODUIT'), 0, 0, 'L', true);
    $pdf->Cell(22, 11, $u('QTE'), 0, 0, 'C', true);
    $pdf->Cell(36, 11, $u('PRIX UNIT.'), 0, 0, 'R', true);
    $pdf->Cell(32, 11, $u('TOTAL  '), 0, 1, 'R', true);

    $pdf->SetDrawColor(232, 233, 237);
    $pdf->SetLineWidth(0.15);
    $pdf->SetFont('Arial', '', 9);
    $fill = false;

    foreach ($details as $d) {
        $total_ligne = $d['quantite'] * $d['prix_unitaire'];

        $nom_produit = $d['nom_produit'];
        if (strlen($nom_produit) > 44) $nom_produit = substr($nom_produit, 0, 42) . '..';

        $bg = $fill ? 248 : 255;
        $pdf->SetFillColor($bg, $bg, $bg);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(90, 9.5, $u('  ' . $nom_produit), 'B', 0, 'L', true);
        $pdf->Cell(22, 9.5, $d['quantite'], 'B', 0, 'C', true);
        $pdf->SetTextColor(120, 108, 80);
        $pdf->Cell(36, 9.5, number_format($d['prix_unitaire'], 0, ',', ' ') . $u(' FCFA'), 'B', 0, 'R', true);
        $pdf->SetTextColor(198, 146, 42);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(32, 9.5, number_format($total_ligne, 0, ',', ' ') . $u(' FCFA  '), 'B', 1, 'R', true);
        $pdf->SetFont('Arial', '', 9);
        $fill = !$fill;
    }

    // ── TOTAL ──
    $pdf->Ln(5);
    $totalY = $pdf->GetY();
    $pdf->SetFillColor(198, 146, 42);
    $pdf->Rect(15, $totalY, 180, 15, 'F');

    $pdf->SetXY(15, $totalY + 3);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(42, 31, 12);
    $pdf->Cell(120, 8, $u('   MONTANT TOTAL'), 0, 0, 'L');
    $pdf->SetFont('Times', 'B', 15);
    $pdf->SetTextColor(26, 18, 0);
    $pdf->Cell(60, 8, number_format($commande['total'], 0, ',', ' ') . $u(' FCFA   '), 0, 1, 'R');
    $pdf->SetY($totalY + 15);

    // ── NOTES ──
    if (!empty($commande['notes'])) {
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(80, 70, 50);
        $pdf->Cell(0, 7, $u('Notes :'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 90, 70);
        $pdf->MultiCell(0, 5, $u($commande['notes']), 0, 'L');
    }

    // ── MESSAGE FINAL ──
    $pdf->Ln(10);
    $pdf->SetFont('Times', 'B', 13);
    $pdf->SetTextColor(198, 146, 42);
    $pdf->Cell(0, 8, $u('Merci pour votre confiance !'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(130, 110, 70);
    $pdf->Cell(0, 6, $u('Nous esperons vous revoir bientot chez Awa Ka Sugu.'), 0, 1, 'C');

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
            $total_apres_reductions, 
            $notes
        ]);
        $commande_id = $pdo->lastInsertId();
        
        // ============================================
        // INSÉRER LES DÉTAILS DE LA COMMANDE AVEC COULEURS ET TAILLES
        // ============================================
        foreach ($_SESSION['panier'] as $item) {
            $sous_total = $item['prix'] * $item['quantite'];
            
            $couleur_id = $item['couleur_id'] ?? null;
            $taille_id = $item['taille_id'] ?? null;
            $couleur_nom = $item['couleur_nom'] ?? null;
            $taille_nom = $item['taille_nom'] ?? null;
            
            $stmt = $pdo->prepare("
                INSERT INTO details_commande (
                    commande_id, 
                    produit_id, 
                    nom_produit, 
                    quantite, 
                    prix_unitaire, 
                    sous_total,
                    couleur_id,
                    taille_id,
                    couleur_nom,
                    taille_nom,
                    couleur,
                    taille
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $commande_id, 
                $item['id'], 
                $item['nom'], 
                $item['quantite'], 
                $item['prix'], 
                $sous_total,
                $couleur_id,
                $taille_id,
                $couleur_nom,
                $taille_nom,
                $couleur_nom,
                $taille_nom
            ]);
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
        // AJOUTER LES POINTS DE FIDÉLITÉ
        // ============================================
        $points_gagnes = 0;
        if ($client_id && $fidelite_actif == 1) {
            $points_gagnes = floor($total_apres_reductions / $seuil_points) * $points_par_seuil;
            
            if ($points_gagnes > 0) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM points_fidelite WHERE client_id = ?");
                    $stmt->execute([$client_id]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $pdo->prepare("UPDATE points_fidelite SET points = points + ?, total_points = total_points + ? WHERE client_id = ?")
                            ->execute([$points_gagnes, $points_gagnes, $client_id]);
                    } else {
                        $pdo->prepare("INSERT INTO points_fidelite (client_id, points, total_points) VALUES (?, ?, ?)")
                            ->execute([$client_id, $points_gagnes, $points_gagnes]);
                    }
                    
                    $description = "Commande #" . $numero_commande . " - " . $points_gagnes . " points gagnés";
                    $pdo->prepare("INSERT INTO historique_points (client_id, points, type, reference_id, description) VALUES (?, ?, 'gain', ?, ?)")
                        ->execute([$client_id, $points_gagnes, $commande_id, $description]);
                    
                } catch(PDOException $e) {
                    error_log("Erreur ajout points: " . $e->getMessage());
                }
            }
        }
        
        // ============================================
        // GÉNÉRER LA FACTURE PDF
        // ============================================
        $facture_info = genererFacturePDF($commande_id, $commande_complete, $details_commande, $pdo);
        
        // ============================================
        // VIDER LE PANIER (APRÈS COMMANDE)
        // ============================================
        $panier_pour_email = $_SESSION['panier'];

        unset($_SESSION['panier']);
        unset($_SESSION['code_promo']);
        unset($_SESSION['reduction_points']);
        
        if (isset($_SESSION['client_id'])) {
            try {
                viderPanierBDD($_SESSION['client_id'], $pdo);
            } catch(PDOException $e) {
                // Ignorer
            }
        }
        
        // ============================================
        // ENVOI DE L'EMAIL DE CONFIRMATION AU CLIENT AVEC FACTURE
        // ============================================
        $sujet = "Confirmation de votre commande Awa Ka Sugu — N° $numero_commande";

        $bloc_points_html = '';
        if ($client_id && $fidelite_actif == 1 && $points_gagnes > 0) {
            $bloc_points_html = '
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF8EC;border:1px solid #E9D4A6;border-radius:12px;margin-bottom:18px;">
              <tr><td style="padding:13px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                  <td valign="top" style="font-size:20px;padding-right:12px;">&#11088;</td>
                  <td style="font-family:Arial,sans-serif;">
                    <div style="font-size:13px;font-weight:bold;color:#C8922A;">' . $points_gagnes . ' points de fid&eacute;lit&eacute; gagn&eacute;s !</div>
                    <div style="font-size:11px;color:#9A8060;margin-top:2px;">Continuez &agrave; cumuler des points pour des r&eacute;ductions exclusives.</div>
                  </td>
                </tr></table>
              </td></tr>
            </table>';
        }

        $lignes_articles_client = '';
        foreach ($panier_pour_email as $item) {
            $options = [];
            if (!empty($item['couleur_nom'])) $options[] = 'Couleur: ' . htmlspecialchars($item['couleur_nom']);
            if (!empty($item['taille_nom'])) $options[] = 'Taille: ' . htmlspecialchars($item['taille_nom']);
            $ligne_options = !empty($options) ? implode(' &middot; ', $options) . ' &nbsp;&middot;&nbsp; ' : '';

            $lignes_articles_client .= '
              <tr>
                <td style="padding:12px 0;border-bottom:1px solid #F5F6F8;font-family:Arial,sans-serif;font-size:12.5px;color:#1A1A2E;">
                    ' . htmlspecialchars($item['nom']) . '<br>
                    <span style="font-size:10.5px;color:#9099A8;">' . $ligne_options . (int)$item['quantite'] . ' &times; ' . number_format($item['prix'], 0, ',', ' ') . ' FCFA</span>
                </td>
                <td align="right" valign="top" style="padding:12px 0;border-bottom:1px solid #F5F6F8;font-family:Arial,sans-serif;font-size:12.5px;font-weight:bold;color:#C8922A;white-space:nowrap;">' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' FCFA</td>
              </tr>';
        }

        $message_html = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmation de commande</title>
        </head>
        <body style="margin:0;padding:0;background-color:#EFEFF2;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EFEFF2;">
        <tr><td align="center" style="padding:32px 12px;">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

          <tr><td style="background-color:#C8922A;font-size:0;line-height:4px;height:4px;">&nbsp;</td></tr>

          <tr><td style="background-color:#0D0D0D;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
              <td align="center" style="padding:34px 30px 26px;">
                <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
                  <td style="width:52px;height:52px;border-radius:50%;border:1.5px solid #C8922A;background-color:#161310;text-align:center;vertical-align:middle;font-family:Georgia,\'Times New Roman\',serif;font-weight:bold;font-size:19px;color:#C8922A;">ID</td>
                </tr></table>
                <div style="height:14px;line-height:14px;font-size:1px;">&nbsp;</div>
                <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:23px;font-weight:bold;letter-spacing:4px;color:#C8922A;text-transform:uppercase;">Awa Ka Sugu</div>
                <div style="height:8px;line-height:8px;font-size:1px;">&nbsp;</div>
                <div style="font-family:Arial,sans-serif;font-size:9.5px;letter-spacing:2px;color:#8a8378;text-transform:uppercase;">Boutique IBA Design &nbsp;&middot;&nbsp; Restaurant Sofia</div>
                <div style="height:16px;line-height:16px;font-size:1px;">&nbsp;</div>
                <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
                  <td style="background-color:#123420;border:1px solid #2ECC71;border-radius:20px;padding:8px 20px;font-family:Arial,sans-serif;font-size:11px;color:#4FE08A;letter-spacing:1px;text-transform:uppercase;font-weight:bold;">&#10003; Commande confirm&eacute;e</td>
                </tr></table>
              </td>
            </tr></table>
          </td></tr>

          <tr><td style="padding:34px 32px 8px;">
            <p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:18px;font-weight:bold;color:#0D0D0D;">Merci, <span style="color:#C8922A;">' . htmlspecialchars($nom) . '</span> !</p>
            <p style="margin:0 0 22px;font-family:Arial,sans-serif;font-size:13px;color:#6A7585;line-height:1.6;">Votre commande a bien &eacute;t&eacute; enregistr&eacute;e et est en cours de traitement.</p>

            ' . $bloc_points_html . '

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F9F9FB;border:1px solid #ECEDF1;border-radius:12px;margin-bottom:18px;">
              <tr><td style="background-color:#0D0D0D;padding:13px 20px;border-radius:11px 11px 0 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Georgia,serif;font-size:13px;font-weight:bold;color:#C8922A;">Commande #' . $numero_commande . '</td>
                  <td align="right" style="font-family:Arial,sans-serif;font-size:10.5px;color:#8a8378;">' . date('d/m/Y à H:i') . '</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:12px 20px;border-bottom:1px solid #F0F1F4;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">Mode de paiement</td>
                  <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $mode_paiement))) . '</td>
                </tr></table>
              </td></tr>
              <tr><td style="padding:12px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td valign="top" style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;width:38%;">Adresse de livraison</td>
                  <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . nl2br(htmlspecialchars($adresse)) . '</td>
                </tr></table>
              </td></tr>
              <tr><td style="background-color:#C8922A;padding:14px 20px;border-radius:0 0 11px 11px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td style="font-family:Arial,sans-serif;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#2A1F0C;">Total &agrave; payer</td>
                  <td align="right" style="font-family:Georgia,serif;font-size:19px;font-weight:bold;color:#1A1200;">' . number_format($total_apres_reductions, 0, ',', ' ') . ' FCFA</td>
                </tr></table>
              </td></tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F0FBF4;border:1px solid #BFE8CC;border-radius:12px;margin-bottom:22px;">
              <tr><td style="padding:13px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                  <td valign="top" style="font-size:18px;padding-right:12px;">&#128206;</td>
                  <td style="font-family:Arial,sans-serif;">
                    <div style="font-size:13px;font-weight:bold;color:#1A7A45;">Votre facture est jointe &agrave; cet email</div>
                    <div style="font-size:11px;color:#5A8A6A;margin-top:2px;">Facture N&deg; ' . $facture_info['numero_facture'] . ' &mdash; Conservez-la pr&eacute;cieusement.</div>
                  </td>
                </tr></table>
              </td></tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
              <tr><td colspan="2" style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8A92A3;padding-bottom:8px;border-bottom:1px solid #E8E9ED;">Articles commandés</td></tr>
              ' . $lignes_articles_client . '
            </table>

            <div style="height:18px;line-height:18px;font-size:1px;">&nbsp;</div>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;"><tr>
              <td>
                <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background-color:#FFFBF0;border:1px solid #E9D4A6;border-radius:20px;padding:7px 14px;font-family:Arial,sans-serif;font-size:11.5px;font-weight:bold;color:#A07020;">&#9679;&nbsp; En attente de validation</td></tr></table>
              </td>
              <td align="right" style="font-family:Arial,sans-serif;font-size:11.5px;color:#7A8694;">Livraison sous 24h&ndash;48h &agrave; Bamako</td>
            </tr></table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;"><tr><td align="center">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="background-color:#C8922A;border-radius:26px;">
                <a href="' . SITE_URL . '/boutique/suivi.php" style="display:block;padding:14px 10px;font-family:Arial,sans-serif;font-size:13.5px;font-weight:bold;color:#1A1200;text-decoration:none;letter-spacing:0.5px;">Suivre ma commande &rarr;</a>
              </td></tr></table>
            </td></tr></table>

          </td></tr>

          <tr><td style="background-color:#0A0A0A;padding:22px 30px;text-align:center;">
            <div style="font-family:Georgia,serif;font-size:13px;font-weight:bold;color:#C8922A;margin-bottom:8px;">Awa Ka Sugu</div>
            <div style="font-family:Arial,sans-serif;font-size:10.5px;">
              <a href="' . SITE_URL . '" style="color:#8a8378;text-decoration:none;">Boutique</a>
              <span style="color:#3a3a3a;">&nbsp;&middot;&nbsp;</span>
              <a href="' . SITE_URL . '/contact.php" style="color:#8a8378;text-decoration:none;">Contact</a>
            </div>
            <div style="font-family:Arial,sans-serif;font-size:9px;color:#3a3a3a;margin-top:10px;">&copy; ' . date('Y') . ' Awa Ka Sugu &mdash; Cet email est généré automatiquement, merci de ne pas y répondre.</div>
          </td></tr>

        </table>

        </td></tr>
        </table>
        </body>
        </html>';
        
        $email_client_envoye = envoyerEmail($email, $sujet, $message_html, $facture_info['pdf_path']);
        
        // ============================================
        // ENVOI DE NOTIFICATION AUX ADMINISTRATEURS ET VENDEURS
        // ============================================
        try {
            $stmt = $pdo->query("
                SELECT email FROM admin 
                WHERE role IN ('super_admin', 'directeur', 'admin', 'admin2') 
                AND email IS NOT NULL AND email != ''
                AND is_active = 1
            ");
            $destinataires = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(PDOException $e) {
            $destinataires = [];
        }
        
        if (empty($destinataires)) {
            $destinataires = ['awakasugu@gmail.com'];
            error_log("Aucun admin trouvé en BDD, envoi à awakasugu@gmail.com (fallback)");
        }
        
        error_log("Destinataires pour la notification: " . implode(', ', $destinataires));
        
        if (!empty($destinataires)) {
            $sujet_notification = "Nouvelle commande #" . $numero_commande . " sur Awa Ka Sugu";

            $lignes_articles_admin = '';
            foreach ($panier_pour_email as $item) {
                $lignes_articles_admin .= '
                  <tr>
                    <td style="padding:11px 0;border-bottom:1px solid #F5F6F8;font-family:Arial,sans-serif;font-size:12.5px;color:#1A1A2E;">' . htmlspecialchars($item['nom']) . ' <span style="color:#9099A8;">&times;' . (int)$item['quantite'] . '</span></td>
                    <td align="right" style="padding:11px 0;border-bottom:1px solid #F5F6F8;font-family:Arial,sans-serif;font-size:12.5px;font-weight:bold;color:#C8922A;white-space:nowrap;">' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' FCFA</td>
                  </tr>';
            }
            
            $message_admin = '
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Nouvelle commande</title>
            </head>
            <body style="margin:0;padding:0;background-color:#EFEFF2;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#EFEFF2;">
            <tr><td align="center" style="padding:32px 12px;">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

              <tr><td style="background-color:#C8922A;font-size:0;line-height:4px;height:4px;">&nbsp;</td></tr>

              <tr><td style="background-color:#0D0D0D;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                  <td align="center" style="padding:28px 30px 24px;">
                    <div style="font-family:Georgia,\'Times New Roman\',serif;font-size:20px;font-weight:bold;letter-spacing:4px;color:#C8922A;text-transform:uppercase;">Awa Ka Sugu</div>
                    <div style="height:6px;line-height:6px;font-size:1px;">&nbsp;</div>
                    <div style="font-family:Arial,sans-serif;font-size:9.5px;letter-spacing:2px;color:#8a8378;text-transform:uppercase;">Espace Administration</div>
                    <div style="height:14px;line-height:14px;font-size:1px;">&nbsp;</div>
                    <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
                      <td style="background-color:#231A0E;border:1px solid #4a3a20;border-radius:20px;padding:7px 18px;font-family:Arial,sans-serif;font-size:10.5px;color:#E8C070;letter-spacing:1px;text-transform:uppercase;font-weight:bold;">Nouvelle commande re&ccedil;ue</td>
                    </tr></table>
                  </td>
                </tr></table>
              </td></tr>

              <tr><td style="padding:30px 30px 8px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF8EC;border:1px solid #E9D4A6;border-radius:14px;margin-bottom:22px;">
                  <tr><td style="padding:16px 18px;">
                    <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                      <td valign="top" style="font-size:26px;padding-right:14px;">&#128230;</td>
                      <td style="font-family:Arial,sans-serif;">
                        <div style="font-size:15px;font-weight:bold;color:#0D0D0D;">Commande <span style="color:#C8922A;">#' . $numero_commande . '</span> &mdash; Action requise</div>
                        <div style="font-size:11.5px;color:#8A7A60;margin-top:4px;">Une nouvelle commande vient d&rsquo;&ecirc;tre enregistr&eacute;e &middot; ' . date('d/m/Y à H:i') . '</div>
                      </td>
                    </tr></table>
                  </td></tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F9F9FB;border:1px solid #ECEDF1;border-radius:12px;margin-bottom:20px;">
                  <tr><td style="background-color:#0D0D0D;padding:11px 18px;border-radius:11px 11px 0 0;">
                    <span style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8a8378;">Informations client</span>
                  </td></tr>
                  <tr><td style="padding:11px 18px;border-bottom:1px solid #F0F1F4;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                      <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">Client</td>
                      <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars($nom) . '</td>
                    </tr></table>
                  </td></tr>
                  <tr><td style="padding:11px 18px;border-bottom:1px solid #F0F1F4;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                      <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">T&eacute;l&eacute;phone</td>
                      <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars($telephone) . '</td>
                    </tr></table>
                  </td></tr>
                  <tr><td style="padding:11px 18px;border-bottom:1px solid #F0F1F4;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                      <td valign="top" style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;width:40%;">Adresse livraison</td>
                      <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . nl2br(htmlspecialchars($adresse)) . '</td>
                    </tr></table>
                  </td></tr>
                  <tr><td style="padding:11px 18px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                      <td style="font-family:Arial,sans-serif;font-size:12px;color:#8A92A3;">Mode de paiement</td>
                      <td align="right" style="font-family:Arial,sans-serif;font-size:12px;font-weight:bold;color:#1A1A2E;">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $mode_paiement))) . '</td>
                    </tr></table>
                  </td></tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#C8922A;border-radius:10px;margin-bottom:22px;">
                  <tr><td style="padding:16px 20px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                      <td style="font-family:Arial,sans-serif;font-size:11.5px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#2A1F0C;">Montant total</td>
                      <td align="right" style="font-family:Georgia,serif;font-size:21px;font-weight:bold;color:#1A1200;">' . number_format($total_apres_reductions, 0, ',', ' ') . ' FCFA</td>
                    </tr></table>
                  </td></tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px;">
                  <tr><td colspan="2" style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#8A92A3;padding-bottom:8px;border-bottom:1px solid #E8E9ED;">Articles commandés</td></tr>
                  ' . $lignes_articles_admin . '
                </table>

                <div style="height:20px;line-height:20px;font-size:1px;">&nbsp;</div>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="background-color:#0D0D0D;border:1.5px solid #C8922A;border-radius:26px;">
                    <a href="' . SITE_URL . '/admin/commande_detail.php?id=' . $commande_id . '" style="display:block;padding:14px 10px;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;color:#C8922A;text-decoration:none;letter-spacing:0.5px;">Traiter cette commande &rarr;</a>
                  </td></tr></table>
                  <p style="margin:10px 0 4px;font-family:Arial,sans-serif;font-size:11px;color:#A0A8B4;">Notification automatique &mdash; connectez-vous &agrave; l&rsquo;administration pour g&eacute;rer les commandes.</p>
                </td></tr></table>

              </td></tr>

              <tr><td style="background-color:#0A0A0A;padding:20px 30px;text-align:center;">
                <div style="font-family:Georgia,serif;font-size:12px;font-weight:bold;color:#C8922A;margin-bottom:6px;">Awa Ka Sugu &mdash; Administration</div>
                <div style="font-family:Arial,sans-serif;font-size:9px;color:#3a3a3a;">&copy; ' . date('Y') . ' Awa Ka Sugu &mdash; Email automatique, merci de ne pas y répondre.</div>
              </td></tr>

            </table>

            </td></tr>
            </table>
            </body>
            </html>';
            
            $notification_envoyee = envoyerEmailMultiples($destinataires, $sujet_notification, $message_admin);
            
            if ($notification_envoyee) {
                error_log("Notification email envoyée pour la commande #" . $numero_commande);
            } else {
                error_log("Erreur lors de l'envoi de la notification");
            }
        } else {
            error_log("Aucun destinataire configuré pour les notifications de commande");
        }
        
        // ============================================
        // REDIRECTION AVEC MESSAGE DE SUCCÈS
        // ============================================
        $_SESSION['commande_success'] = [
            'numero' => $numero_commande,
            'total' => $total_apres_reductions,
            'nom' => $nom,
            'email_envoye' => $email_client_envoye
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

// Afficher un message si la commande vient du bouton "Commander"
$commande_directe_message = '';
if (isset($_GET['produit_id'])) {
    $commande_directe_message = 'Vous commandez directement : ' . htmlspecialchars($produit_direct['nom'] ?? '') . ' (x' . $quantite_directe . ')';
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
    display: flex;
    align-items: center;
    gap: 10px;
}
.formulaire-card .section-title i {
    color: #C8922A;
    font-size: 1.3rem;
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
    padding: 10px 14px;
    border-radius: 10px;
    transition: background 0.3s;
    border: 1.5px solid #F0F2F5;
    margin-bottom: 8px;
}
.form-check:hover {
    background: #FEFBF5;
    border-color: rgba(200,146,42,0.2);
}
.form-check-input:checked {
    background-color: #C8922A;
    border-color: #C8922A;
}
.form-check-label {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #0D0D0D;
}
.form-check-label i {
    color: #C8922A;
    font-size: 1rem;
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
.btn-valider i {
    font-size: 1.1rem;
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
.alert-info-commande {
    background: #FFF8E1;
    border-left: 4px solid #C8922A;
    color: #5D4E37;
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-info-commande i {
    color: #C8922A;
    font-size: 1.2rem;
}
.resume-card h4 {
    font-family: 'Playfair Display', serif;
    color: #0D0D0D;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.resume-card h4 i {
    color: #C8922A;
    font-size: 1.2rem;
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
.btn-retour {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #8A99AA;
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 15px;
    transition: color 0.3s;
}
.btn-retour:hover {
    color: #C8922A;
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
        <h1>Finaliser ma commande</h1>
        <p>Remplissez vos informations pour valider votre commande</p>
    </div>
</div>

<div class="container-custom">
    <div class="commande-grid">
        <!-- Formulaire -->
        <div class="formulaire-card">
            <a href="javascript:history.back()" class="btn-retour">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            
            <div class="section-title">
                <i class="bi bi-geo-alt-fill"></i>
                Informations de livraison
            </div>
            
            <?php if($commande_directe_message): ?>
                <div class="alert-info-commande">
                    <i class="bi bi-bag-check-fill"></i>
                    <span><?= $commande_directe_message ?></span>
                </div>
            <?php endif; ?>
            
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
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="livraison" id="livraison" checked>
                            <label class="form-check-label" for="livraison">
                                <i class="bi bi-cash-coin"></i>
                                Paiement à la livraison
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="orange_money" id="orange">
                            <label class="form-check-label" for="orange">
                                <i class="bi bi-phone"></i>
                                Orange Money
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="wave" id="wave">
                            <label class="form-check-label" for="wave">
                                <i class="bi bi-water"></i>
                                Wave
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode_paiement" value="moov_money" id="moov">
                            <label class="form-check-label" for="moov">
                                <i class="bi bi-phone-vibrate"></i>
                                Moov Money
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-valider">
                    <i class="bi bi-check-circle-fill"></i>
                    Confirmer ma commande
                </button>
            </form>
        </div>
        
        <!-- Résumé -->
        <div class="resume-card">
            <h4>
                <i class="bi bi-bag-fill"></i>
                Récapitulatif
            </h4>
            <?php foreach($_SESSION['panier'] as $item): ?>
            <div class="resume-item">
                <span class="item-name">
                    <?= htmlspecialchars($item['nom']) ?>
                    <?php if(!empty($item['couleur_nom']) || !empty($item['taille_nom'])): ?>
                        <br>
                        <small style="color:#8A99AA;font-size:0.7rem;">
                            <?php if(!empty($item['couleur_nom'])): ?>
                                <span class="badge bg-light text-dark" style="font-weight:400;padding:1px 8px;border:1px solid #ddd;">
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background-color:<?= $item['couleur_hex'] ?? '#ccc' ?>;vertical-align:middle;margin-right:3px;"></span>
                                    <?= htmlspecialchars($item['couleur_nom']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if(!empty($item['taille_nom'])): ?>
                                <span class="badge bg-light text-dark" style="font-weight:400;padding:1px 8px;border:1px solid #ddd;">
                                    <?= htmlspecialchars($item['taille_nom']) ?>
                                </span>
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                    <span style="color:#8A99AA;font-size:0.8rem;">x<?= $item['quantite'] ?></span>
                </span>
                <span class="item-price"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> F</span>
            </div>
            <?php endforeach; ?>
            
            <?php if($reduction_appliquee > 0): ?>
            <div class="resume-item" style="color:#E74C3C;">
                <span class="item-name">Code promo</span>
                <span class="item-price">- <?= number_format($reduction_appliquee, 0, ',', ' ') ?> F</span>
            </div>
            <?php endif; ?>
            
            <?php if($reduction_points_montant > 0): ?>
            <div class="resume-item" style="color:#C8922A;">
                <span class="item-name">Points fidélité (<?= $points_utilises ?> pts)</span>
                <span class="item-price">- <?= number_format($reduction_points_montant, 0, ',', ' ') ?> F</span>
            </div>
            <?php endif; ?>
            
            <div class="resume-total">
                <span class="total-label">Total</span>
                <span class="total-amount"><?= number_format($total_apres_reductions, 0, ',', ' ') ?> FCFA</span>
            </div>
            <div class="paiement-info">
                <i class="bi bi-info-circle-fill"></i>
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