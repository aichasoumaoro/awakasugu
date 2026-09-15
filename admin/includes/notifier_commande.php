<?php
// ============================================
// NOTIFICATION DE NOUVELLE COMMANDE
// ============================================

require_once __DIR__ . '/../../includes/envoi_email.php';

/**
 * Construire le message HTML pour la notification
 */
function construireMessageNotification($commande, $details) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #0D0D0D, #1A1A1A); padding: 25px 30px; text-align: center; border-bottom: 4px solid #C8922A; }
            .header h1 { font-family: "Georgia", serif; color: #C8922A; margin: 0; font-size: 1.5rem; letter-spacing: 3px; }
            .header p { color: rgba(255,255,255,0.4); margin: 5px 0 0; font-size: 0.8rem; }
            .content { padding: 25px 30px; }
            .alert { background: #FFF8E1; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #C8922A; margin-bottom: 15px; }
            .alert strong { color: #C8922A; }
            .info-box { background: #F8F9FA; border-radius: 10px; padding: 15px 18px; margin: 15px 0; border-left: 4px solid #C8922A; }
            .info-box p { margin: 4px 0; font-size: 0.9rem; color: #333; }
            .info-box .label { color: #8A99AA; font-weight: 600; }
            .info-box .total { font-size: 1.4rem; font-weight: 700; color: #C8922A; text-align: right; margin-top: 10px; padding-top: 10px; border-top: 2px solid rgba(200,146,42,0.2); }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #F8F9FA; padding: 10px 12px; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: #8A99AA; border-bottom: 2px solid #C8922A; }
            td { padding: 8px 12px; border-bottom: 1px solid #F0F2F5; font-size: 0.85rem; }
            .btn { display: inline-block; background: #C8922A; color: #fff; padding: 10px 25px; border-radius: 30px; text-decoration: none; margin-top: 15px; font-weight: 600; }
            .btn:hover { background: #9A6E1A; }
            .footer { background: #F8F9FA; padding: 15px 20px; text-align: center; color: #8A99AA; font-size: 0.7rem; border-top: 1px solid #E8ECF0; }
            .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.6rem; font-weight: 600; background: #FFF3CD; color: #856404; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✦ AWA KA SUGU ✦</h1>
                <p>Nouvelle commande enregistrée</p>
            </div>
            <div class="content">
                <div class="alert">
                    🛒 <strong>Nouvelle commande #' . $commande['numero_commande'] . '</strong>
                    <br><span style="font-size:0.85rem;color:#666;">Une nouvelle commande vient d\'être passée</span>
                </div>
                
                <div class="info-box">
                    <p><span class="label">👤 Client :</span> ' . htmlspecialchars($commande['nom_client']) . '</p>
                    <p><span class="label">📞 Téléphone :</span> ' . htmlspecialchars($commande['telephone'] ?? 'Non renseigné') . '</p>
                    <p><span class="label">📍 Adresse :</span> ' . nl2br(htmlspecialchars($commande['adresse_livraison'] ?? 'Non renseignée')) . '</p>
                    <p><span class="label">💳 Paiement :</span> ' . ucfirst(str_replace('_', ' ', $commande['mode_paiement'] ?? 'Non défini')) . '</p>
                    <p><span class="label">📅 Date :</span> ' . date('d/m/Y à H:i', strtotime($commande['created_at'] ?? 'now')) . '</p>
                    <div class="total">💰 ' . number_format($commande['total'] ?? 0, 0, ',', ' ') . ' FCFA</div>
                </div>
                
                <h3 style="font-size:1rem;color:#0D0D0D;margin:15px 0 10px;">🛍️ Articles commandés</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th style="text-align:center;">Qté</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    foreach ($details as $d) {
        $html .= '
                        <tr>
                            <td>' . htmlspecialchars($d['nom_produit'] ?? 'Produit') . '</td>
                            <td style="text-align:center;">' . ($d['quantite'] ?? 0) . '</td>
                            <td style="text-align:right;color:#C8922A;font-weight:600;">' . number_format(($d['quantite'] ?? 0) * ($d['prix_unitaire'] ?? 0), 0, ',', ' ') . ' F</td>
                        </tr>';
    }
    
    $html .= '
                    </tbody>
                </table>
                
                <div style="text-align:center;margin:20px 0;">
                    <a href="http://localhost/awakasugu/admin/commande_detail.php?id=' . $commande['id'] . '" class="btn" style="color:#fff;">📋 Voir la commande</a>
                </div>
                
                <p style="text-align:center;font-size:0.8rem;color:#8A99AA;">
                    <i class="bi bi-clock"></i> Ceci est une notification automatique.
                    <br>Pour gérer les commandes, connectez-vous à l\'administration.
                </p>
            </div>
            <div class="footer">
                <p>Awa Ka Sugu &copy; ' . date('Y') . ' - Tous droits réservés</p>
                <p style="font-size:0.6rem;color:#B0B0B0;">Cet email est généré automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Notifier tous les administrateurs et vendeurs d'une nouvelle commande
 * Utilise getDestinatairesNotification() pour ne notifier que ceux qui ne sont PAS connectés
 */
function notifierNouvelleCommande($commande_id, $pdo) {
    
    // Vérifier si la commande existe
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        error_log("❌ Commande $commande_id non trouvée");
        return false;
    }
    
    // Vérifier si la notification a déjà été envoyée
    if (isset($commande['notification_envoyee']) && $commande['notification_envoyee'] == 1) {
        error_log("ℹ️ Notification déjà envoyée pour la commande #" . $commande['numero_commande']);
        return true;
    }
    
    // Récupérer les détails de la commande
    $stmt = $pdo->prepare("SELECT * FROM details_commande WHERE commande_id = ?");
    $stmt->execute([$commande_id]);
    $details = $stmt->fetchAll();
    
    // Construire le sujet
    $sujet = "🛒 Nouvelle commande #" . $commande['numero_commande'] . " sur Awa Ka Sugu";
    
    // Construire le message HTML
    $message_html = construireMessageNotification($commande, $details);
    
    // ============================================
    // RÉCUPÉRER LES DESTINATAIRES (SEULEMENT CEUX NON CONNECTÉS)
    // ============================================
    $destinataires = getDestinatairesNotification($pdo);
    
    if (empty($destinataires)) {
        error_log("⚠️ Aucun destinataire à notifier pour la commande #" . $commande['numero_commande']);
        return false;
    }
    
    error_log("📧 Envoi de la notification à " . count($destinataires) . " destinataires: " . implode(', ', $destinataires));
    
    // Envoyer l'email à tous les destinataires
    $resultat = envoyerEmailMultiples($destinataires, $sujet, $message_html);
    
    if ($resultat) {
        // Marquer la notification comme envoyée
        try {
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET notification_envoyee = 1, notification_date = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$commande_id]);
            error_log("✅ Notification marquée comme envoyée pour la commande #" . $commande['numero_commande']);
        } catch(PDOException $e) {
            error_log("❌ Erreur mise à jour notification_envoyee: " . $e->getMessage());
        }
        
        // Ajouter une notification dans la table notifications
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (type, reference_id, message, created_at, est_lue) 
                VALUES ('commande', ?, ?, NOW(), 0)
            ");
            $message = "Nouvelle commande #" . $commande['numero_commande'] . " de " . $commande['nom_client'];
            $stmt->execute([$commande_id, $message]);
        } catch(PDOException $e) {
            // Ignorer si la table n'existe pas
        }
    } else {
        error_log("❌ Échec de l'envoi des notifications pour la commande #" . $commande['numero_commande']);
    }
    
    return $resultat;
}
?>