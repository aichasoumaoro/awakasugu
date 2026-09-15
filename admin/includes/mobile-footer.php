<?php
// ============================================
// GRAPHIQUES AVANCÉS - ADMIN AWA KA SUGU
// ============================================
?>
<script>
// ============================================
// GRAPHIQUE ÉVOLUTION DES MARGES
// ============================================
function initGraphiquesAvances(dataMarge) {
    const ctx = document.getElementById('margeChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dataMarge.labels || ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'],
            datasets: [{
                label: 'Marge brute (FCFA)',
                data: dataMarge.data || [],
                backgroundColor: 'rgba(200,146,42,0.7)',
                borderColor: '#C8922A',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => 'Marge: ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA'
                    }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// ============================================
// GRAPHIQUE RÉPARTITION PRODUITS PAR MARGE
// ============================================
function initDonutMarge(data) {
    const ctx = document.getElementById('donutMarge');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Marge > 50%', 'Marge 20-50%', 'Marge < 20%', 'Sans marge'],
            datasets: [{
                data: data || [0, 0, 0, 0],
                backgroundColor: ['#27AE60', '#F39C12', '#E74C3C', '#95A5A6'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
</script>