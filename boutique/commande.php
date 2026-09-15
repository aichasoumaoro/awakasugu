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
    
    // Créer le PDF
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 25);
    
    // En-tête
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
    
    // Titre
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 12, 'FACTURE', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, 'N° ' . $numero_facture, 0, 1, 'C');
    $pdf->Ln(6);
    
    // Informations client
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
    
    // Tableau des produits
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
        if ($client_id && $fidelite_actif == 1) {
            $points_gagnes = floor($total_apres_reductions / $seuil_points) * $points_par_seuil;
            
            if ($points_gagnes > 0) {
                try {
                    // Vérifier si le client a déjà un compte de points
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
                    
                    // Enregistrer dans l'historique
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
        // Vider le panier de la session
        unset($_SESSION['panier']);
        unset($_SESSION['code_promo']);
        unset($_SESSION['reduction_points']);
        
        // Vider le panier en BDD si client connecté
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
        $sujet = "✅ Confirmation de votre commande Awa Ka Sugu - N° $numero_commande";
        
        // Construire le message HTML pour le client
        $message_html = '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmation de commande</title>
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600&display=swap");
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: "Inter", Arial, sans-serif; background: #0E0E0E; padding: 30px 15px; }
                .wrapper { max-width: 620px; margin: 0 auto; }
                /* ── Header ── */
                .header { background: linear-gradient(160deg, #0A0A0A 0%, #1C1308 60%, #0A0A0A 100%); padding: 40px 30px 30px; text-align: center; border-radius: 20px 20px 0 0; position: relative; overflow: hidden; }
                .header::before { content: ""; position: absolute; top: -60px; left: 50%; transform: translateX(-50%); width: 300px; height: 300px; background: radial-gradient(circle, rgba(200,146,42,0.12) 0%, transparent 70%); }
                .header .brand { font-family: "Playfair Display", Georgia, serif; font-size: 2rem; font-weight: 700; color: #C8922A; letter-spacing: 4px; text-transform: uppercase; line-height: 1; }
                .header .divider { width: 60px; height: 2px; background: linear-gradient(90deg, transparent, #C8922A, transparent); margin: 12px auto; }
                .header .tagline { color: rgba(255,255,255,0.35); font-size: 0.7rem; letter-spacing: 3px; text-transform: uppercase; font-weight: 300; }
                .header .badge-confirmed { display: inline-block; background: rgba(39,174,96,0.15); border: 1px solid rgba(39,174,96,0.4); color: #2ECC71; font-size: 0.72rem; font-weight: 600; padding: 5px 16px; border-radius: 20px; margin-top: 16px; letter-spacing: 1px; }
                /* ── Body ── */
                .body { background: #ffffff; padding: 36px 32px; }
                .greeting { font-size: 1.25rem; font-weight: 600; color: #0D0D0D; margin-bottom: 6px; }
                .greeting span { color: #C8922A; }
                .subtitle { color: #7A8694; font-size: 0.88rem; font-weight: 400; margin-bottom: 28px; }
                /* ── Points Banner ── */
                .points-banner { background: linear-gradient(135deg, #FFF8EC, #FFF3DB); border: 1px solid rgba(200,146,42,0.25); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
                .points-banner .pts-icon { font-size: 1.5rem; }
                .points-banner .pts-text strong { color: #C8922A; font-size: 0.95rem; }
                .points-banner .pts-text small { color: #9A8060; font-size: 0.78rem; display: block; margin-top: 2px; }
                /* ── Order Summary Card ── */
                .order-card { background: #F9F9FB; border-radius: 14px; overflow: hidden; margin-bottom: 22px; border: 1px solid #EEEFF2; }
                .order-card-head { background: linear-gradient(135deg, #0D0D0D, #1A1510); padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; }
                .order-card-head .order-num { color: #C8922A; font-family: "Playfair Display", serif; font-size: 1rem; font-weight: 700; }
                .order-card-head .order-date { color: rgba(255,255,255,0.4); font-size: 0.75rem; }
                .order-rows { padding: 8px 0; }
                .order-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; border-bottom: 1px solid #F0F1F4; }
                .order-row:last-child { border-bottom: none; }
                .order-row .lbl { color: #8A92A3; font-size: 0.82rem; font-weight: 500; }
                .order-row .val { color: #0D0D0D; font-size: 0.88rem; font-weight: 600; }
                .order-row .val.gold { color: #C8922A; }
                .order-total { background: linear-gradient(135deg, #C8922A, #E8B55A); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
                .order-total .tot-lbl { color: rgba(255,255,255,0.75); font-size: 0.8rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; }
                .order-total .tot-val { color: #fff; font-family: "Playfair Display", serif; font-size: 1.4rem; font-weight: 700; }
                /* ── Facture notice ── */
                .facture-notice { background: #F0FBF4; border: 1px solid rgba(39,174,96,0.3); border-radius: 12px; padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: flex-start; gap: 12px; }
                .facture-notice .fn-icon { font-size: 1.4rem; margin-top: 2px; }
                .facture-notice .fn-text strong { color: #1A7A45; font-size: 0.9rem; }
                .facture-notice .fn-text small { color: #5A8A6A; font-size: 0.78rem; display: block; margin-top: 3px; }
                /* ── Products Table ── */
                .section-title { font-size: 0.78rem; font-weight: 600; color: #8A92A3; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
                .products-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
                .products-table thead tr { background: #F4F5F8; }
                .products-table th { padding: 10px 12px; font-size: 0.72rem; font-weight: 600; color: #8A92A3; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
                .products-table td { padding: 11px 12px; border-bottom: 1px solid #F0F1F4; font-size: 0.85rem; color: #2D3748; vertical-align: top; }
                .products-table tr:last-child td { border-bottom: none; }
                .products-table .prod-opt { color: #9AA0AC; font-size: 0.75rem; margin-top: 3px; }
                .products-table .td-center { text-align: center; }
                .products-table .td-right { text-align: right; font-weight: 600; color: #C8922A; }
                /* ── Status + CTA ── */
                .status-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 22px; }
                .status-badge { display: inline-flex; align-items: center; gap: 6px; background: #FFFBF0; border: 1px solid rgba(200,146,42,0.35); color: #A07020; font-size: 0.78rem; font-weight: 600; padding: 7px 14px; border-radius: 20px; }
                .status-badge::before { content: ""; display: inline-block; width: 7px; height: 7px; background: #C8922A; border-radius: 50%; }
                .delivery-note { color: #7A8694; font-size: 0.8rem; }
                .cta-btn { display: block; text-align: center; background: linear-gradient(135deg, #C8922A, #E8B55A); color: #fff; font-weight: 600; font-size: 0.88rem; padding: 14px 30px; border-radius: 30px; text-decoration: none; letter-spacing: 0.5px; margin-bottom: 10px; }
                /* ── Footer ── */
                .footer { background: #0D0D0D; padding: 24px 30px; text-align: center; border-radius: 0 0 20px 20px; }
                .footer .ft-brand { color: #C8922A; font-family: "Playfair Display", serif; font-size: 0.9rem; font-weight: 600; margin-bottom: 6px; }
                .footer .ft-links { margin: 8px 0; }
                .footer .ft-links a { color: rgba(255,255,255,0.3); font-size: 0.72rem; text-decoration: none; margin: 0 8px; }
                .footer .ft-copy { color: rgba(255,255,255,0.2); font-size: 0.68rem; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="header">
                    <div class="brand">✦ Awa Ka Sugu ✦</div>
                    <div class="divider"></div>
                    <div class="tagline">Boutique IBA Design &amp; Restaurant Sofia</div>
                    <div class="badge-confirmed">✓ Commande confirmée</div>
                </div>
                <div class="body">
                    <p class="greeting">Merci, <span>' . htmlspecialchars($nom) . '</span> !</p>
                    <p class="subtitle">Votre commande a bien été enregistrée et est en cours de traitement.</p>';
        
        // Points gagnés
        if ($client_id && $fidelite_actif == 1 && $points_gagnes > 0) {
            $message_html .= '
                    <div class="points-banner">
                        <span class="pts-icon">⭐</span>
                        <div class="pts-text">
                            <strong>' . $points_gagnes . ' points de fidélité gagnés !</strong>
                            <small>Continuez à cumuler des points pour des réductions exclusives.</small>
                        </div>
                    </div>';
        }
        
        $message_html .= '
                    <div class="order-card">
                        <div class="order-card-head">
                            <span class="order-num">Commande #' . $numero_commande . '</span>
                            <span class="order-date">' . date('d/m/Y à H:i') . '</span>
                        </div>
                        <div class="order-rows">
                            <div class="order-row">
                                <span class="lbl">Mode de paiement</span>
                                <span class="val">' . ucfirst(str_replace('_', ' ', $mode_paiement)) . '</span>
                            </div>
                            <div class="order-row">
                                <span class="lbl">Adresse de livraison</span>
                                <span class="val">' . nl2br(htmlspecialchars($adresse)) . '</span>
                            </div>
                        </div>
                        <div class="order-total">
                            <span class="tot-lbl">Total à payer</span>
                            <span class="tot-val">' . number_format($total_apres_reductions, 0, ',', ' ') . ' FCFA</span>
                        </div>
                    </div>
                    
                    <div class="facture-notice">
                        <span class="fn-icon">📎</span>
                        <div class="fn-text">
                            <strong>Votre facture est jointe à cet email</strong>
                            <small>Facture N° ' . $facture_info['numero_facture'] . ' — Conservez-la précieusement.</small>
                        </div>
                    </div>
                    
                    <p class="section-title">Articles commandés</p>
                    <table class="products-table">
                        <thead><tr><th>Produit</th><th class="td-center">Qté</th><th class="td-right">Prix</th><th class="td-right">Total</th></tr></thead>
                        <tbody>';
        
        foreach ($_SESSION['panier'] as $item) {
            $message_html .= '<tr><td>' . htmlspecialchars($item['nom']);
            if (!empty($item['couleur_nom']) || !empty($item['taille_nom'])) {
                $options = [];
                if (!empty($item['couleur_nom'])) $options[] = 'Couleur: ' . htmlspecialchars($item['couleur_nom']);
                if (!empty($item['taille_nom'])) $options[] = 'Taille: ' . htmlspecialchars($item['taille_nom']);
                $message_html .= '<div class="prod-opt">' . implode(' · ', $options) . '</div>';
            }
            $message_html .= '</td><td class="td-center">' . $item['quantite'] . '</td><td style="text-align:right;color:#555;">' . number_format($item['prix'], 0, ',', ' ') . ' F</td><td class="td-right">' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' F</td></tr>';
        }
        
        $message_html .= '
                        </tbody>
                    </table>
                    
                    <div class="status-row">
                        <span class="status-badge">En attente de validation</span>
                        <span class="delivery-note">🚚 Livraison sous 24h–48h à Bamako</span>
                    </div>
                    
                    <a href="' . SITE_URL . '/boutique/suivi.php" class="cta-btn">Suivre ma commande →</a>
                </div>
                <div class="footer">
                    <div class="ft-brand">✦ Awa Ka Sugu ✦</div>
                    <div class="ft-links">
                        <a href="#">Boutique</a>
                        <a href="#">Contact</a>
                        <a href="#">CGV</a>
                    </div>
                    <div class="ft-copy">&copy; ' . date('Y') . ' Awa Ka Sugu — Cet email est généré automatiquement, merci de ne pas y répondre.</div>
                </div>
            </div>
        </body>
        </html>';
        
        // Envoyer l'email de confirmation au client avec Brevo et la facture PDF en pièce jointe
        $email_client_envoye = envoyerEmail($email, $sujet, $message_html, $facture_info['pdf_path']);
        
        // ============================================
        // ENVOI DE NOTIFICATION AUX ADMINISTRATEURS ET VENDEURS (VERSION SIMPLIFIÉE ET FIABLE)
        // ============================================
        
        // Récupérer TOUS les emails des admins/vendeurs actifs depuis la BDD
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
        
        // Si aucun email trouvé, utiliser un email de fallback pour tester
        if (empty($destinataires)) {
            // Envoyer à l'admin principal pour test
            $destinataires = ['awakasugu@gmail.com'];
            error_log("⚠️ Aucun admin trouvé en BDD, envoi à awakasugu@gmail.com (fallback)");
        }
        
        error_log("📧 Destinataires pour la notification: " . implode(', ', $destinataires));
        
        if (!empty($destinataires)) {
            // Construire le sujet
            $sujet_notification = "🛒 Nouvelle commande #" . $numero_commande . " sur Awa Ka Sugu";
            
            // Construire le message HTML pour les admins
            $message_admin = '
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    @import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600&display=swap");
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: "Inter", Arial, sans-serif; background: #0E0E0E; padding: 30px 15px; }
                    .wrapper { max-width: 620px; margin: 0 auto; }
                    /* ── Header ── */
                    .header { background: linear-gradient(160deg, #0A0A0A 0%, #1C1308 60%, #0A0A0A 100%); padding: 32px 30px; text-align: center; border-radius: 20px 20px 0 0; position: relative; }
                    .header::after { content: ""; display: block; width: 80px; height: 2px; background: linear-gradient(90deg, transparent, #C8922A, transparent); margin: 10px auto 0; }
                    .header .brand { font-family: "Playfair Display", Georgia, serif; font-size: 1.7rem; font-weight: 700; color: #C8922A; letter-spacing: 4px; }
                    .header .tagline { color: rgba(255,255,255,0.3); font-size: 0.68rem; letter-spacing: 3px; text-transform: uppercase; margin-top: 6px; }
                    .header .notif-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(200,146,42,0.12); border: 1px solid rgba(200,146,42,0.35); color: #E8B55A; font-size: 0.72rem; font-weight: 600; padding: 5px 16px; border-radius: 20px; margin-top: 14px; letter-spacing: 1px; }
                    /* ── Body ── */
                    .body { background: #ffffff; padding: 32px 30px; }
                    /* ── Alert banner ── */
                    .alert-banner { background: linear-gradient(135deg, #FFF8EC, #FFFAF2); border: 1px solid rgba(200,146,42,0.3); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 14px; }
                    .alert-banner .ab-icon { font-size: 2rem; line-height: 1; }
                    .alert-banner .ab-text .ab-title { font-size: 1rem; font-weight: 700; color: #0D0D0D; }
                    .alert-banner .ab-text .ab-title span { color: #C8922A; }
                    .alert-banner .ab-text .ab-sub { font-size: 0.8rem; color: #8A7A60; margin-top: 3px; }
                    /* ── Client card ── */
                    .client-card { background: #F9F9FB; border-radius: 14px; overflow: hidden; margin-bottom: 22px; border: 1px solid #EEEFF2; }
                    .client-card-head { background: #0D0D0D; padding: 10px 18px; }
                    .client-card-head span { color: rgba(255,255,255,0.4); font-size: 0.68rem; letter-spacing: 2px; text-transform: uppercase; font-weight: 500; }
                    .client-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 18px; border-bottom: 1px solid #F0F1F4; }
                    .client-row:last-child { border-bottom: none; }
                    .client-row .cr-lbl { color: #8A92A3; font-size: 0.8rem; font-weight: 500; }
                    .client-row .cr-val { color: #1A1A2E; font-size: 0.88rem; font-weight: 600; max-width: 60%; text-align: right; }
                    /* ── Total band ── */
                    .total-band { background: linear-gradient(135deg, #C8922A, #E8B55A); border-radius: 12px; padding: 16px 22px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; }
                    .total-band .tb-lbl { color: rgba(255,255,255,0.75); font-size: 0.78rem; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }
                    .total-band .tb-val { color: #fff; font-family: "Playfair Display", serif; font-size: 1.5rem; font-weight: 700; }
                    /* ── Products table ── */
                    .sec-label { font-size: 0.72rem; font-weight: 600; color: #8A92A3; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
                    .ptable { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
                    .ptable thead tr { background: #F4F5F8; }
                    .ptable th { padding: 10px 12px; font-size: 0.7rem; font-weight: 600; color: #8A92A3; text-transform: uppercase; letter-spacing: 1px; text-align: left; }
                    .ptable td { padding: 11px 12px; border-bottom: 1px solid #F0F1F4; font-size: 0.85rem; color: #2D3748; }
                    .ptable tr:last-child td { border-bottom: none; }
                    .ptable .td-c { text-align: center; }
                    .ptable .td-r { text-align: right; font-weight: 700; color: #C8922A; }
                    /* ── CTA ── */
                    .cta-btn { display: block; text-align: center; background: linear-gradient(135deg, #0D0D0D, #2A1F0A); color: #C8922A; font-weight: 700; font-size: 0.9rem; padding: 15px 30px; border-radius: 30px; text-decoration: none; letter-spacing: 0.5px; border: 2px solid #C8922A; margin-bottom: 12px; }
                    .auto-note { text-align: center; color: #A0A8B4; font-size: 0.75rem; margin-top: 8px; }
                    /* ── Footer ── */
                    .footer { background: #0D0D0D; padding: 20px 30px; text-align: center; border-radius: 0 0 20px 20px; }
                    .footer .ft-brand { color: #C8922A; font-family: "Playfair Display", serif; font-size: 0.85rem; margin-bottom: 6px; }
                    .footer .ft-copy { color: rgba(255,255,255,0.18); font-size: 0.65rem; margin-top: 6px; }
                </style>
            </head>
            <body>
                <div class="wrapper">
                    <div class="header">
                        <div class="brand">✦ Awa Ka Sugu ✦</div>
                        <div class="tagline">Espace Administration</div>
                        <div class="notif-badge">🛒 Nouvelle commande reçue</div>
                    </div>
                    <div class="body">
                        <div class="alert-banner">
                            <span class="ab-icon">📦</span>
                            <div class="ab-text">
                                <div class="ab-title">Commande <span>#' . $numero_commande . '</span> — Action requise</div>
                                <div class="ab-sub">Une nouvelle commande vient d\'être enregistrée · ' . date('d/m/Y à H:i') . '</div>
                            </div>
                        </div>
                        
                        <div class="client-card">
                            <div class="client-card-head"><span>Informations client</span></div>
                            <div class="client-row"><span class="cr-lbl">Client</span><span class="cr-val">' . htmlspecialchars($nom) . '</span></div>
                            <div class="client-row"><span class="cr-lbl">Téléphone</span><span class="cr-val">' . htmlspecialchars($telephone) . '</span></div>
                            <div class="client-row"><span class="cr-lbl">Adresse livraison</span><span class="cr-val">' . nl2br(htmlspecialchars($adresse)) . '</span></div>
                            <div class="client-row"><span class="cr-lbl">Mode de paiement</span><span class="cr-val">' . ucfirst(str_replace('_', ' ', $mode_paiement)) . '</span></div>
                        </div>
                        
                        <div class="total-band">
                            <span class="tb-lbl">Montant total</span>
                            <span class="tb-val">' . number_format($total_apres_reductions, 0, ',', ' ') . ' FCFA</span>
                        </div>
                        
                        <p class="sec-label">Articles commandés</p>
                        <table class="ptable">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th class="td-c">Qté</th>
                                    <th class="td-r">Total</th>
                                </tr>
                            </thead>
                            <tbody>';
            
            foreach ($_SESSION['panier'] as $item) {
                $message_admin .= '
                                <tr>
                                    <td>' . htmlspecialchars($item['nom']) . '</td>
                                    <td class="td-c">' . $item['quantite'] . '</td>
                                    <td class="td-r">' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' F</td>
                                </tr>';
            }
            
            $message_admin .= '
                            </tbody>
                        </table>
                        
                        <a href="http://localhost/awakasugu/admin/commande_detail.php?id=' . $commande_id . '" class="cta-btn">Traiter cette commande →</a>
                        <p class="auto-note">Notification automatique — Connectez-vous à l\'administration pour gérer les commandes.</p>
                    </div>
                    <div class="footer">
                        <div class="ft-brand">✦ Awa Ka Sugu — Administration ✦</div>
                        <div class="ft-copy">&copy; ' . date('Y') . ' Awa Ka Sugu — Email automatique, merci de ne pas y répondre.</div>
                    </div>
                </div>
            </body>
            </html>';
            
            // Envoyer l'email à tous les destinataires
            $notification_envoyee = envoyerEmailMultiples($destinataires, $sujet_notification, $message_admin);
            
            if ($notification_envoyee) {
                error_log("✅ Notification email envoyée pour la commande #" . $numero_commande);
            } else {
                error_log("❌ Erreur lors de l'envoi de la notification");
            }
        } else {
            error_log("⚠️ Aucun destinataire configuré pour les notifications de commande");
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
        <h1>📦 Finaliser ma commande</h1>
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
            
            <div class="section-title">📍 Informations de livraison</div>
            
            <?php if($commande_directe_message): ?>
                <div class="alert-info-commande">
                    <i class="bi bi-bag-check"></i>
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