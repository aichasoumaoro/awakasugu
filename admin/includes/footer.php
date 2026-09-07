<?php
// ============================================
// FOOTER ADMIN - AWA KA SUGU
// ============================================
?>
<script>
document.querySelectorAll('.modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

function confirmDelete(message) {
    return confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?');
}
</script>
</body>
</html>