<?php
// ============================================
// CALLBACK POUR LES PAIEMENTS (WEBHOOK)
// Reçoit les notifications des API Orange Money et Wave
// ============================================

// ============================================
// 1. LOG POUR DÉBOGUER
// ============================================
$log_file = __DIR__ . '/callback_log.txt';

// Lire les données reçues
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Si pas de données JSON, essayer de lire POST ou GET
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

// Si toujours pas de données, essayer GET
if (!$data && !empty($_GET)) {
    $data = $_GET;
}

// Log des données reçues
file_put_contents($log_file, date('Y-m-d H:i:s') . " - INPUT: " . $input . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - DATA: " . json_encode($data) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - POST: " . json_encode($_POST) . "\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - GET: " . json_encode($_GET) . "\n\n", FILE_APPEND);

// ============================================
// 2. TRAITEMENT DES DONNÉES
// ============================================
if ($data) {
    $host = 'localhost';
    $dbname = 'awakasugu_db';
    $user = 'root';
    $pass = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo "DB_ERROR";
        exit;
    }
    
    // Déterminer le mode de paiement
    $mode = $data['mode'] ?? $data['payment_method'] ?? $data['type'] ?? '';
    $commande_id = $data['commande_id'] ?? $data['order_id'] ?? $data['transaction_id'] ?? 0;
    $statut = $data['status'] ?? $data['statut'] ?? '';
    $transaction_id = $data['transaction_id'] ?? $data['reference'] ?? '';
    
    // Vérifier si le paiement est réussi
    $est_success = in_array($statut, ['success', 'SUCCESS', 'completed', 'COMPLETED', 'paye', 'PAYE', 'confirmed', 'CONFIRMED']);
    
    if ($est_success && $commande_id > 0) {
        // Vérifier si la commande existe
        $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch();
        
        if ($commande) {
            // Mettre à jour le statut de la commande
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'confirmee' WHERE id = ?");
            $stmt->execute([$commande_id]);
            
            // Mettre à jour le statut de la facture
            $stmt = $pdo->prepare("UPDATE factures SET statut_paiement = 'payee' WHERE commande_id = ?");
            $stmt->execute([$commande_id]);
            
            // Sauvegarder l'ID de transaction
            if (!empty($transaction_id)) {
                $stmt = $pdo->prepare("UPDATE commandes SET transaction_id = ? WHERE id = ?");
                $stmt->execute([$transaction_id, $commande_id]);
            }
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - ✅ PAIEMENT CONFIRMÉ pour commande #$commande_id\n", FILE_APPEND);
            
            // ============================================
            // ENVOI D'EMAIL DE CONFIRMATION
            // ============================================
            require_once '../includes/envoi_email.php';
            
            $email = $commande['email_client'] ?? $commande['email'] ?? '';
            
            if (!empty($email)) {
                $sujet = "✅ Paiement confirmé - Commande Awa Ka Sugu N° " . $commande['numero_commande'];
                
                $message_html = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
                        .header { background: linear-gradient(135deg, #0D0D0D, #1A1A1A); padding: 30px; text-align: center; border-bottom: 3px solid #C8922A; }
                        .header h1 { font-family: 'Georgia', serif; color: #C8922A; margin: 0; font-size: 1.8rem; letter-spacing: 3px; }
                        .header p { color: rgba(255,255,255,0.4); margin: 5px 0 0; font-size: 0.8rem; }
                        .content { padding: 30px; }
                        .content h2 { font-size: 1.2rem; color: #0D0D0D; margin-bottom: 10px; }
                        .info-box { background: #F8F9FA; padding: 15px 20px; border-radius: 10px; margin: 15px 0; border-left: 4px solid #27AE60; }
                        .info-box p { margin: 5px 0; font-size: 0.9rem; color: #333; }
                        .info-box strong { color: #0D0D0D; }
                        .info-box .total { font-size: 1.3rem; font-weight: 700; color: #27AE60; text-align: right; margin-top: 10px; padding-top: 10px; border-top: 2px solid #27AE60; }
                        .footer { background: #F8F9FA; padding: 20px; text-align: center; color: #8A99AA; font-size: 0.8rem; border-top: 1px solid #E8ECF0; }
                        .badge-success { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: #D4EDDA; color: #155724; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>✦ AWA KA SUGU ✦</h1>
                            <p>Boutique IBA Design & Restaurant Sofia</p>
                        </div>
                        <div class='content'>
                            <h2>Bonjour " . htmlspecialchars($commande['nom_client']) . " !</h2>
                            <p>Votre paiement a été confirmé avec succès.</p>
                            
                            <div class='info-box'>
                                <p><strong>📦 Numéro de commande :</strong> <span style='color:#C8922A;font-weight:700;'>" . $commande['numero_commande'] . "</span></p>
                                <p><strong>💳 Paiement :</strong> " . ucfirst(str_replace('_', ' ', $mode)) . "</p>
                                <p><strong>✅ Statut :</strong> <span class='badge-success'>Payée</span></p>
                                <div class='total'>💰 " . number_format($commande['total'], 0, ',', ' ') . " FCFA</div>
                            </div>
                            
                            <p style='color:#666;font-size:0.85rem;'>Votre commande est en cours de préparation.</p>
                        </div>
                        <div class='footer'>
                            <p>Awa Ka Sugu &copy; " . date('Y') . " - Tous droits réservés</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                envoyerEmail($email, $sujet, $message_html);
            }
            
            echo "OK";
            exit;
        }
    }
}

// Si on arrive ici, c'est que le traitement a échoué
file_put_contents($log_file, date('Y-m-d H:i:s') . " - ❌ TRAITEMENT ÉCHOUÉ\n", FILE_APPEND);
echo "ERROR";
?>