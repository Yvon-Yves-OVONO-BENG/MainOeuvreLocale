/**
 * ============================================
 * INITIALISATION DE LA PAGE CHOIX PROFIL
 * Fichier : /public/js/pages/profile-choice.js
 * ============================================
 */

document.addEventListener('DOMContentLoaded', function() {
    initProfileChoiceCards();
});

/**
 * Initialise les cartes de choix avec emojis et descriptions
 */
function initProfileChoiceCards() {
    const labels = document.querySelectorAll('.profile-type-choices label');
    
    if (!labels.length) {
        return;
    }

    const descriptions = {
        'talent': {
            emoji: '🧑‍💼',
            text: 'Talent',
            sub: 'Je cherche du travail'
        },
        'particulier': {
            emoji: '👤',
            text: 'Particulier',
            sub: 'Je cherche des services'
        },
        'company': {
            emoji: '🏢',
            text: 'Entreprise',
            sub: 'Je recrute ou propose des services'
        }
    };

    labels.forEach(function(label) {
        const radio = label.querySelector('input[type="radio"]');
        
        if (!radio) {
            return;
        }

        const value = radio.value;
        const desc = descriptions[value];
        
        if (!desc) {
            return;
        }

        // Reconstruction du contenu du label
        label.innerHTML = 
            '<div class="emoji">' + desc.emoji + '</div>' +
            '<div>' +
                '<div class="label-text">' + desc.text + '</div>' +
                '<div class="label-sub">' + desc.sub + '</div>' +
            '</div>';
    });
}