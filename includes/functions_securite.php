<?php
// ============================================
// FONCTIONS DE SÉCURITÉ - AWA KA SUGU
// ============================================
// Gestion des tentatives de connexion, blocages, logs
// ============================================

// ============================================
// CHARGEMENT DE PHPMailer
// ============================================
require_once __DIR__ . '/PHPMailer-7.1.1/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-7.1.1/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-7.1.1/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie un email avec PHPMailer via Brevo
 */
function envoyerEmailSecurite($destinataire, $sujet, $message_html) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP Brevo
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'af53c2001@smtp-brevo.com';
$smtp_password = 'REMPLACEZ_MOI_PAR_VOTRE_VRAIE_CLE';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Expéditeur et destinataire
        $mail->setFrom('chacha16.com@gmail.com', 'Awa Ka Sugu - Sécurité');
        $mail->addAddress($destinataire);
        $mail->addReplyTo('chacha16.com@gmail.com', 'Awa Ka Sugu');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = '[AWA KA SUGU] ' . $sujet;
        $mail->Body = $message_html;
        $mail->AltBody = strip_tags($message_html);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur envoi email sécurité: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Enregistre une tentative de connexion dans la base de données
 */
function enregistrer_tentative($pdo, $email, $success = false) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO tentatives_connexion (email, ip_address, tentative_time, success, user_agent) 
        VALUES (?, ?, NOW(), ?, ?)
    ");
    return $stmt->execute([$email, $ip, $success ? 1 : 0, $user_agent]);
}

/**
 * Vérifie si une IP est bloquée
 */
function is_ip_bloquee($pdo, $ip) {
    $stmt = $pdo->prepare("
        SELECT id FROM comptes_bloques 
        WHERE ip_address = ? 
        AND type = 'ip' 
        AND fin_blocage > NOW()
        LIMIT 1
    ");
    $stmt->execute([$ip]);
    return $stmt->fetch() !== false;
}

/**
 * Vérifie si un compte (email) est bloqué
 */
function is_compte_bloque($pdo, $email) {
    $stmt = $pdo->prepare("
        SELECT id, fin_blocage FROM comptes_bloques 
        WHERE email = ? 
        AND type = 'compte' 
        AND fin_blocage > NOW()
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $result = $stmt->fetch();
    if ($result) {
        return [
            'bloque' => true,
            'fin_blocage' => $result['fin_blocage']
        ];
    }
    return ['bloque' => false];
}

/**
 * Compte les tentatives échouées pour un email dans les 15 dernières minutes
 */
function compter_tentatives_echouees($pdo, $email) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb 
        FROM tentatives_connexion 
        WHERE email = ? 
        AND success = 0 
        AND tentative_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email]);
    return (int)$stmt->fetchColumn();
}

/**
 * Compte les tentatives échouées pour une IP dans les 15 dernières minutes
 */
function compter_tentatives_ip_echouees($pdo, $ip) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as nb 
        FROM tentatives_connexion 
        WHERE ip_address = ? 
        AND success = 0 
        AND tentative_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn();
}

/**
 * Bloque une IP pour 15 minutes
 */
function bloquer_ip($pdo, $ip, $raison = 'Trop de tentatives échouées') {
    $stmt = $pdo->prepare("
        DELETE FROM comptes_bloques 
        WHERE ip_address = ? AND type = 'ip'
    ");
    $stmt->execute([$ip]);
    
    $stmt = $pdo->prepare("
        INSERT INTO comptes_bloques (ip_address, blocage_time, fin_blocage, raison, type) 
        VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?, 'ip')
    ");
    return $stmt->execute([$ip, $raison]);
}

/**
 * Bloque un compte (email) pour 30 minutes
 */
function bloquer_compte($pdo, $email, $raison = 'Trop de tentatives échouées') {
    $stmt = $pdo->prepare("
        DELETE FROM comptes_bloques 
        WHERE email = ? AND type = 'compte'
    ");
    $stmt->execute([$email]);
    
    $stmt = $pdo->prepare("
        INSERT INTO comptes_bloques (email, ip_address, blocage_time, fin_blocage, raison, type) 
        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?, 'compte')
    ");
    return $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $raison]);
}

/**
 * Débloque un compte (pour le Super Admin)
 */
function debloquer_compte($pdo, $email) {
    $stmt = $pdo->prepare("
        DELETE FROM comptes_bloques 
        WHERE email = ? AND type = 'compte'
    ");
    return $stmt->execute([$email]);
}

/**
 * Vérifie si un email existe dans la table admin
 */
function email_admin_existe($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

/**
 * Envoie une alerte de sécurité via Brevo au Super Admin
 */
function envoyer_alerte_securite($pdo, $sujet, $message) {
    // Récupérer l'email du Super Admin
    $stmt = $pdo->prepare("
        SELECT id, nom, email FROM admin WHERE role = 'super_admin' AND is_active = 1 LIMIT 1
    ");
    $stmt->execute();
    $super_admin = $stmt->fetch();
    
    if (!$super_admin) {
        return false;
    }
    
    $to_email = $super_admin['email'];
    $to_name = $super_admin['nom'] ?? 'Super Admin';
    
    // Construction de l'email HTML
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
    $date = date('d/m/Y H:i:s');
    
    $corps_html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px; }
            .header { background: linear-gradient(135deg, #C8922A, #E8B55A); color: #fff; padding: 20px; border-radius: 10px 10px 0 0; text-align: center; }
            .header h2 { margin: 0; font-size: 22px; }
            .header p { margin: 5px 0 0; opacity: 0.8; font-size: 14px; }
            .content { padding: 25px; background: #fff; border-radius: 0 0 10px 10px; }
            .alert-box { background: #FFF3E0; border-left: 4px solid #E74C3C; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .alert-box strong { color: #E74C3C; }
            .detail { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
            .detail strong { display: inline-block; width: 140px; color: #555; }
            .footer { text-align: center; font-size: 12px; color: #999; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
            .btn { display: inline-block; padding: 10px 25px; background: #C8922A; color: #fff; text-decoration: none; border-radius: 5px; margin-top: 10px; }
            .btn:hover { background: #9A6E1A; }
            .warning { color: #E74C3C; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔒 AWA KA SUGU</h2>
                <p>ALERTE DE SÉCURITÉ</p>
            </div>
            <div class='content'>
                <div class='alert-box'>
                    <p style='margin:0;'><strong>⚠️ " . htmlspecialchars($sujet) . "</strong></p>
                </div>
                
                <div style='margin: 15px 0;'>
                    " . $message . "
                </div>
                
                <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                    <div class='detail'><strong>📅 Date/Heure :</strong> " . $date . "</div>
                    <div class='detail'><strong>🌐 Adresse IP :</strong> " . htmlspecialchars($ip) . "</div>
                    <div class='detail'><strong>👤 Compte visé :</strong> " . htmlspecialchars($to_email) . "</div>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; border: 1px solid #ffc107; margin: 15px 0;'>
                    <p style='margin:0; font-size: 14px;'>
                        <span class='warning'>⚠️</span> 
                        <strong>Recommandation :</strong> Si vous n'êtes pas à l'origine de ces tentatives, 
                        <strong>changez immédiatement votre mot de passe</strong> et vérifiez la sécurité de votre compte.
                    </p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='https://awakasugu.ml/admin' style='display: inline-block; padding: 10px 25px; background: #C8922A; color: #fff; text-decoration: none; border-radius: 5px;'>
                        🔐 Accéder à l'administration
                    </a>
                </div>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " Awa Ka Sugu - Système de sécurité<br>
                Cet email est un message automatique. Ne pas y répondre.
            </div>
        </div>
    </html>
    ";
    
    return envoyerEmailSecurite($to_email, $sujet, $corps_html);
}

// ============================================
// FONCTIONS POUR LE JOURNAL D'AUDIT
// ============================================

/**
 * Enregistre une action dans le journal d'audit (table logs_actions)
 * Utilisée par toutes les pages admin
 */
function enregistrer_log_action($pdo, $admin_id, $admin_nom, $admin_email, $action, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_actions (admin_id, admin_nom, admin_email, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$admin_id, $admin_nom, $admin_email, $action, $details, $ip]);
        
    } catch(PDOException $e) {
        error_log("Erreur log action: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les logs récents
 */
function get_recent_logs($pdo, $limit = 100) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM logs_actions 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Récupère les logs avec filtres (pour la page audit.php)
 */
function get_logs_filtered($pdo, $filters = [], $limit = 50, $offset = 0) {
    $sql = "SELECT * FROM logs_actions WHERE 1=1";
    $params = [];
    
    if (!empty($filters['action'])) {
        $sql .= " AND action LIKE ?";
        $params[] = "%" . $filters['action'] . "%";
    }
    
    if (!empty($filters['admin_id'])) {
        $sql .= " AND admin_id = ?";
        $params[] = (int)$filters['admin_id'];
    }
    
    if (!empty($filters['date_debut'])) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $filters['date_debut'];
    }
    
    if (!empty($filters['date_fin'])) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $filters['date_fin'];
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * Compte le nombre total de logs pour la pagination
 */
function count_logs_filtered($pdo, $filters = []) {
    $sql = "SELECT COUNT(*) FROM logs_actions WHERE 1=1";
    $params = [];
    
    if (!empty($filters['action'])) {
        $sql .= " AND action LIKE ?";
        $params[] = "%" . $filters['action'] . "%";
    }
    
    if (!empty($filters['admin_id'])) {
        $sql .= " AND admin_id = ?";
        $params[] = (int)$filters['admin_id'];
    }
    
    if (!empty($filters['date_debut'])) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $filters['date_debut'];
    }
    
    if (!empty($filters['date_fin'])) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $filters['date_fin'];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les statistiques du journal d'audit
 */
function get_audit_stats($pdo) {
    try {
        $stats = [];
        
        // Total des actions
        $stmt = $pdo->query("SELECT COUNT(*) FROM logs_actions");
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // Actions du jour
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM logs_actions 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['jour'] = (int)$stmt->fetchColumn();
        
        // Actions du mois
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM logs_actions 
            WHERE MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $stats['mois'] = (int)$stmt->fetchColumn();
        
        // Actions les plus fréquentes
        $stmt = $pdo->query("
            SELECT action, COUNT(*) as nb 
            FROM logs_actions 
            GROUP BY action 
            ORDER BY nb DESC 
            LIMIT 10
        ");
        $stats['frequentes'] = $stmt->fetchAll();
        
        return $stats;
    } catch(PDOException $e) {
        error_log("Erreur stats audit: " . $e->getMessage());
        return [
            'total' => 0,
            'jour' => 0,
            'mois' => 0,
            'frequentes' => []
        ];
    }
}

/**
 * Récupère la liste des administrateurs avec leur nombre d'actions
 */
function get_admins_with_actions($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT admin_id, admin_nom, admin_email, COUNT(*) as nb_actions 
            FROM logs_actions 
            GROUP BY admin_id, admin_nom, admin_email 
            ORDER BY nb_actions DESC
        ");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// ============================================
// FONCTIONS DE NETTOYAGE
// ============================================

/**
 * Nettoyer les anciennes tentatives (plus de 7 jours)
 */
function nettoyer_tentatives_anciennes($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM tentatives_connexion 
            WHERE tentative_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Erreur nettoyage tentatives: " . $e->getMessage());
        return false;
    }
}

/**
 * Nettoyer les anciens blocages expirés
 */
function nettoyer_blocages_expires($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM comptes_bloques 
            WHERE fin_blocage < NOW()
        ");
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Erreur nettoyage blocages: " . $e->getMessage());
        return false;
    }
}

/**
 * Nettoyer les anciens logs (plus de 90 jours)
 */
function nettoyer_anciens_logs($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM logs_actions 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        return $stmt->execute();
    } catch(PDOException $e) {
        error_log("Erreur nettoyage logs: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FONCTIONS POUR LES PARAMÈTRES
// ============================================

/**
 * Récupère les paramètres du site
 */
function get_parametres($pdo) {
    try {
        // Vérifier si la table parametres existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'parametres'");
        if ($stmt->rowCount() == 0) {
            // Créer la table parametres
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS parametres (
                    id INT PRIMARY KEY DEFAULT 1,
                    nom_site VARCHAR(100) DEFAULT 'Awa Ka Sugu',
                    description_site TEXT,
                    email_contact VARCHAR(100),
                    telephone_contact VARCHAR(50),
                    adresse TEXT,
                    email_notification VARCHAR(100),
                    taux_tva DECIMAL(5,2) DEFAULT 18,
                    frais_livraison DECIMAL(10,2) DEFAULT 1000,
                    delai_livraison VARCHAR(50) DEFAULT '3-5 jours',
                    devise VARCHAR(10) DEFAULT 'FCFA',
                    icone VARCHAR(255),
                    logo VARCHAR(255),
                    favicon VARCHAR(255),
                    footer_text TEXT,
                    maintenance TINYINT(1) DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Insérer les valeurs par défaut
            $pdo->exec("
                INSERT INTO parametres (id, nom_site, description_site, email_contact, telephone_contact, 
                adresse, email_notification, taux_tva, frais_livraison, delai_livraison, 
                devise, footer_text, maintenance, created_at, updated_at) 
                VALUES (1, 'Awa Ka Sugu', 'Boutique en ligne de vêtements et accessoires', 
                'contact@awakasugu.ml', '+223 75 00 00 00', 'Bamako, Mali', 
                'admin@awakasugu.ml', 18, 1000, '3-5 jours', 'FCFA', 
                '© 2024 Awa Ka Sugu - Tous droits réservés', 0, NOW(), NOW())
            ");
        }
        
        $stmt = $pdo->query("SELECT * FROM parametres WHERE id = 1");
        return $stmt->fetch();
        
    } catch(PDOException $e) {
        error_log("Erreur récupération paramètres: " . $e->getMessage());
        return null;
    }
}

/**
 * Met à jour les paramètres du site
 */
function update_parametres($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            UPDATE parametres SET 
            nom_site = ?, description_site = ?, email_contact = ?, 
            telephone_contact = ?, adresse = ?, email_notification = ?, 
            taux_tva = ?, frais_livraison = ?, delai_livraison = ?, 
            devise = ?, footer_text = ?, maintenance = ?, updated_at = NOW()
            WHERE id = 1
        ");
        return $stmt->execute([
            $data['nom_site'] ?? 'Awa Ka Sugu',
            $data['description_site'] ?? '',
            $data['email_contact'] ?? '',
            $data['telephone_contact'] ?? '',
            $data['adresse'] ?? '',
            $data['email_notification'] ?? '',
            $data['taux_tva'] ?? 18,
            $data['frais_livraison'] ?? 1000,
            $data['delai_livraison'] ?? '3-5 jours',
            $data['devise'] ?? 'FCFA',
            $data['footer_text'] ?? '',
            isset($data['maintenance']) ? 1 : 0
        ]);
    } catch(PDOException $e) {
        error_log("Erreur mise à jour paramètres: " . $e->getMessage());
        return false;
    }
}