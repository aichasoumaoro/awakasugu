<?php
// ============================================
// FOOTER - Awa Ka Sugu
// ============================================

// Session publique uniquement
if (session_status() === PHP_SESSION_NONE) {
    session_name('PUBLIC_SESSION');
    session_start();
}

// NE PAS vérifier l'admin ici
?>

<style>
.footer {
    background: #0A0A0A;
    color: rgba(255,255,255,0.6);
    padding: 60px 0 25px;
    margin-top: 60px;
    position: relative;
    overflow: hidden;
}

.footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #C8922A, #E8B55A, #C8922A);
}

.footer .container {
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 40px;
}

.footer-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 40px;
    margin-bottom: 45px;
}

.footer-logo {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    font-weight: 700;
    letter-spacing: 3px;
    background: linear-gradient(135deg, #C8922A, #E8B55A);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 15px;
}

.footer-desc {
    font-size: 0.8rem;
    line-height: 1.6;
    color: rgba(255,255,255,0.45);
    margin-bottom: 20px;
}

.footer-social {
    display: flex;
    gap: 12px;
}

.footer-social a {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.5);
    font-size: 1rem;
    transition: all 0.3s;
    text-decoration: none;
}

.footer-social a:hover {
    background: #C8922A;
    color: #FFFFFF;
    transform: translateY(-3px);
}

.footer-title {
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: 1.5px;
    color: #C8922A;
    margin-bottom: 18px;
    text-transform: uppercase;
    position: relative;
    display: inline-block;
}

.footer-title::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 0;
    width: 30px;
    height: 2px;
    background: #C8922A;
}

.footer-links {
    list-style: none;
    margin: 0;
    padding: 0;
}

.footer-links li {
    margin-bottom: 10px;
}

.footer-links a {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: all 0.3s;
    display: inline-block;
}

.footer-links a:hover {
    color: #C8922A;
    transform: translateX(5px);
}

.footer-contact {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.footer-contact i {
    color: #C8922A;
    width: 20px;
    font-size: 0.9rem;
}

.footer-payment {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.footer-payment span {
    background: rgba(255,255,255,0.05);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 500;
    color: rgba(255,255,255,0.6);
    transition: all 0.3s;
}

.footer-payment span:hover {
    background: rgba(200,146,42,0.15);
    color: #C8922A;
}

.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 25px;
    text-align: center;
    font-size: 0.7rem;
    color: rgba(255,255,255,0.3);
}

.footer-bottom span {
    color: #C8922A;
    font-weight: 500;
}

/* Scroll to top button */
.scroll-top {
    position: fixed;
    bottom: 25px;
    left: 25px;
    z-index: 9999;
    background: rgba(255,255,255,0.05);
    color: #C8922A;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid rgba(200,146,42,0.3);
    opacity: 0;
    visibility: hidden;
}

.scroll-top.show {
    opacity: 1;
    visibility: visible;
}

.scroll-top:hover {
    background: #C8922A;
    color: #1A1A1A;
    transform: translateY(-3px);
}

@media (max-width: 900px) {
    .footer-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
    }
}

@media (max-width: 600px) {
    .footer .container {
        padding: 0 20px;
    }
    
    .footer-grid {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 35px;
    }
    
    .footer-contact {
        justify-content: center;
    }
    
    .footer-social {
        justify-content: center;
    }
    
    .footer-payment {
        justify-content: center;
    }
    
    .footer-title::after {
        left: 50%;
        transform: translateX(-50%);
    }
}
</style>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Colonne 1 - Logo & Infos -->
            <div>
                <div class="footer-logo">✦ AWA KA SUGU ✦</div>
                <p class="footer-desc">
                    Boutique IBA Design & Restaurant Sofia.<br>
                    Mode modeste et cuisine malienne authentique à Bamako.
                </p>
                <div class="footer-social">
                    <a href="https://www.instagram.com/awadoumbia223" target="_blank"><i class="bi bi-instagram"></i></a>
                    <a href="https://www.facebook.com/awadoumbia223" target="_blank"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.tiktok.com/@awadoumbia223" target="_blank"><i class="bi bi-tiktok"></i></a>
                    <a href="https://wa.me/22366746985" target="_blank"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>

            <!-- Colonne 2 - IBA Design -->
            <div>
                <h4 class="footer-title">IBA DESIGN</h4>
                <ul class="footer-links">
                    <li><a href="<?= SITE_URL ?>/boutique/catalogue.php">Tous les produits</a></li>
                    <li><a href="<?= SITE_URL ?>/boutique/nouveautes.php">Nouveautés</a></li>
                    <li><a href="<?= SITE_URL ?>/boutique/promotions.php">Promotions</a></li>
                    <li><a href="<?= SITE_URL ?>/boutique/videos.php">Vidéos Awa Doumbia</a></li>
                </ul>
                <div class="footer-contact">
                    <i class="bi bi-geo-alt"></i> Sebenikoro Koro, Bamako
                </div>
                <div class="footer-contact">
                    <i class="bi bi-telephone"></i> +223 77 77 43 43
                </div>
            </div>

            <!-- Colonne 3 - Restaurant Sofia -->
            <div>
                <h4 class="footer-title">RESTAURANT SOFIA</h4>
                <ul class="footer-links">
                    <li><a href="<?= SITE_URL ?>/restaurant/menu.php">Menu complet</a></li>
                    <li><a href="<?= SITE_URL ?>/restaurant/reservation.php">Réservation</a></li>
                    <li><a href="<?= SITE_URL ?>/restaurant/plat_jour.php">Plat du jour</a></li>
                </ul>
                <div class="footer-contact">
                    <i class="bi bi-geo-alt"></i> Sebenikoro, face mosquée
                </div>
                <div class="footer-contact">
                    <i class="bi bi-telephone"></i> +223 74 74 03 03
                </div>
            </div>

            <!-- Colonne 4 - Contact & Paiement -->
            <div>
                <h4 class="footer-title">CONTACT</h4>
                <div class="footer-contact">
                    <i class="bi bi-envelope"></i> contact@awakasugu.com
                </div>
                <div class="footer-contact">
                    <i class="bi bi-whatsapp"></i> +223 66 74 69 85
                </div>
                <div class="footer-contact">
                    <i class="bi bi-clock"></i> Lun - Sam : 8h - 21h
                </div>
                
                <div class="footer-payment">
                    <span>Orange Money</span>
                    <span>Wave</span>
                    <span>Moov Money</span>
                    <span>Espèces</span>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© <?= date('Y') ?> <span>Awa Ka Sugu</span> — Tous droits réservés</p>
        </div>
    </div>
</footer>

<div class="scroll-top" id="scrollTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="bi bi-arrow-up"></i>
</div>

<script>
window.addEventListener('scroll', function() {
    const scrollBtn = document.getElementById('scrollTop');
    if (window.scrollY > 300) {
        scrollBtn.classList.add('show');
    } else {
        scrollBtn.classList.remove('show');
    }
});
</script>

</body>
</html>