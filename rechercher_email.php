<?php
// ============================================
// RECHERCHE DES FICHIERS D'EMAIL ET FACTURE
// ============================================

echo "<h1>🔍 Recherche des fichiers d'email et factures</h1>";
echo "<hr>";

$dossier_racine = __DIR__;
echo "<p>📂 Dossier de recherche : <code>$dossier_racine</code></p>";

// Mots-clés à rechercher dans les fichiers
$mots_cles = [
    'envoyerEmail',
    'msgHTML',
    'commande_confirmation',
    'html2text',
    'facture',
    'dompdf',
    'TCPDF',
    'generer_facture',
    'confirm_commande'
];

// Rechercher dans tous les fichiers PHP
function rechercherDansFichiers($dossier, $termes) {
    $resultats = [];
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dossier, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($files as $file) {
        if ($file->getExtension() == 'php') {
            $chemin = $file->getPathname();
            $contenu = file_get_contents($chemin);
            
            foreach ($termes as $terme) {
                if (stripos($contenu, $terme) !== false) {
                    $resultats[$chemin][] = $terme;
                }
            }
        }
    }
    
    return $resultats;
}

$resultats = rechercherDansFichiers($dossier_racine, $mots_cles);

echo "<h2>📋 Fichiers contenant des mots-clés</h2>";

if (empty($resultats)) {
    echo "<div style='background:#fff3cd;padding:15px;border-radius:8px;border:1px solid #ffc107;'>";
    echo "⚠️ Aucun fichier trouvé avec les mots-clés. Vérifiez manuellement ces dossiers :<br>";
    echo "• <code>client/</code><br>";
    echo "• <code>admin/</code><br>";
    echo "• <code>includes/</code><br>";
    echo "• <code>traitement/</code><br>";
    echo "</div>";
} else {
    echo "<div style='background:#d4edda;padding:15px;border-radius:8px;border:1px solid #c3e6cb;'>";
    echo "✅ <strong>" . count($resultats) . " fichier(s) trouvé(s)</strong><br><br>";
    foreach ($resultats as $fichier => $termes) {
        $chemin_relatif = str_replace($dossier_racine . '/', '', $fichier);
        echo "📁 <code><strong>" . $chemin_relatif . "</strong></code><br>";
        echo "<span style='color:#666;font-size:0.85rem;margin-left:20px;'>🔑 Mots-clés : " . implode(', ', array_unique($termes)) . "</span><br><br>";
    }
    echo "</div>";
}

echo "<hr>";

// ============================================
// AFFICHER LES DOSSIERS PRINCIPAUX
// ============================================
echo "<h2>📁 Structure des dossiers</h2>";
echo "<pre style='background:#f8f9fa;padding:15px;border-radius:8px;font-size:0.8rem;'>";

function afficherStructure($dossier, $indent = 0) {
    $exclus = ['.', '..', 'vendor', 'node_modules', '.git', 'cache'];
    $fichiers = scandir($dossier);
    sort($fichiers);
    
    foreach ($fichiers as $f) {
        if (in_array($f, $exclus)) continue;
        
        $chemin = $dossier . '/' . $f;
        $espaces = str_repeat('  ', $indent);
        
        if (is_dir($chemin)) {
            // Ne montrer que les dossiers importants
            $dossiers_importants = ['client', 'admin', 'includes', 'factures', 'traitement', 'email'];
            if (in_array($f, $dossiers_importants) || $indent > 0) {
                echo $espaces . "📁 <strong>$f/</strong>\n";
                if ($indent < 2) {
                    afficherStructure($chemin, $indent + 1);
                }
            }
        } else {
            if (in_array($f, ['fonctions_email.php', 'email_functions.php', 'generer_facture.php', 'commande.php'])) {
                echo $espaces . "📄 <strong style='color:#C8922A;'>$f</strong>\n";
            }
        }
    }
}

afficherStructure($dossier_racine);
echo "</pre>";

echo "<hr>";
echo "<h3>💡 Conseils :</h3>";
echo "<ul>";
echo "<li>L'email de confirmation est généralement dans un fichier appelé <strong>commande.php</strong> ou <strong>validation_commande.php</strong></li>";
echo "<li>La génération de facture est souvent dans <strong>admin/generer_facture.php</strong></li>";
echo "<li>Le template de l'email est soit dans la même fonction, soit dans un fichier <strong>email_template.php</strong></li>";
echo "<li>La facture PDF utilise généralement <strong>dompdf</strong> ou <strong>TCPDF</strong></li>";
echo "</ul>";
?>