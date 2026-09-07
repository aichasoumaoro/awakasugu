<?php
// ============================================
// ENVOI D'EMAIL AVEC BREVO
// ============================================

require_once __DIR__ . '/PHPMailer-7.1.1/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-7.1.1/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-7.1.1/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function envoyerEmail($destinataire, $sujet, $message_html, $piece_jointe = null) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'af53c2001@smtp-brevo.com';
       $smtp_password = 'REMPLACEZ_MOI_PAR_VOTRE_VRAIE_CLE';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        $mail->setFrom('chacha16.com@gmail.com', 'Awa Ka Sugu');
        $mail->addAddress($destinataire);
        $mail->addReplyTo('chacha16.com@gmail.com', 'Awa Ka Sugu');
        
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message_html;
        $mail->AltBody = strip_tags($message_html);
        
        if ($piece_jointe && file_exists($piece_jointe)) {
            $mail->addAttachment($piece_jointe);
        }
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email: " . $mail->ErrorInfo);
        return false;
    }
}