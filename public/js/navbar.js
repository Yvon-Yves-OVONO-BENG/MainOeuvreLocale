/**
 * Navbar Pro SaaS Premium++
 * Gestion complète du menu, dropdowns et mobile
 */

document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // 1. SCROLL EFFECT
    // ==========================================
    const navbar = document.querySelector('.pm-navbar-pro');
    if (navbar) {
        let lastScroll = 0;
        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            
            if (currentScroll > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });
    }

    // ==========================================
    // 2. MOBILE MENU
    // ==========================================
    const hamburger = document.getElementById('pmHamburgerBtn');
    const mobileMenu = document.getElementById('pmMobileMenu');
    const overlay = document.getElementById('pmOverlay');
    const closeBtn = document.getElementById('pmMobileClose');

    function openMobileMenu() {
        mobileMenu.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        hamburger.classList.add('is-active');
        hamburger.setAttribute('aria-expanded', 'true');
    }

    function closeMobileMenu() {
        mobileMenu.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        hamburger.classList.remove('is-active');
        hamburger.setAttribute('aria-expanded', 'false');
    }

    if (hamburger) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            if (mobileMenu.classList.contains('open')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeMobileMenu();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeMobileMenu();
            }
        });
    }

    // Fermeture avec Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('open')) {
            closeMobileMenu();
        }
    });

    // ==========================================
    // 3. MOBILE DROPDOWN
    // ==========================================
    document.querySelectorAll('[data-mobile-dropdown-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.pm-mobile-dropdown');
            const isOpen = parent.classList.contains('is-open');
            
            // Ferme les autres
            document.querySelectorAll('.pm-mobile-dropdown.is-open').forEach(function(item) {
                if (item !== parent) {
                    item.classList.remove('is-open');
                    const toggle = item.querySelector('[data-mobile-dropdown-toggle]');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                }
            });
            
            parent.classList.toggle('is-open');
            this.setAttribute('aria-expanded', parent.classList.contains('is-open'));
        });
    });

    // ==========================================
    // 4. LANGUE DROPDOWN
    // ==========================================
    const langDropdown = document.querySelector('[data-lang-dropdown]');
    if (langDropdown) {
        const langToggle = langDropdown.querySelector('[data-lang-toggle]');
        const langMenu = langDropdown.querySelector('[data-lang-menu]');

        if (langToggle) {
            langToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = langDropdown.classList.toggle('is-open');
                this.setAttribute('aria-expanded', isOpen);
            });
        }

        // Fermeture des autres dropdowns utilisateur
        langDropdown.querySelectorAll('.pm-lang-item').forEach(function(item) {
            item.addEventListener('click', function() {
                langDropdown.classList.remove('is-open');
                if (langToggle) langToggle.setAttribute('aria-expanded', 'false');
            });
        });

        // Fermeture au clic extérieur
        document.addEventListener('click', function(e) {
            if (!langDropdown.contains(e.target)) {
                langDropdown.classList.remove('is-open');
                if (langToggle) langToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ==========================================
    // 5. USER DROPDOWN
    // ==========================================
    const userDropdown = document.querySelector('[data-user-dropdown]');
    if (userDropdown) {
        const userToggle = userDropdown.querySelector('[data-user-toggle]');
        const userMenu = userDropdown.querySelector('[data-user-menu]');

        if (userToggle) {
            userToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = userDropdown.classList.toggle('is-open');
                this.setAttribute('aria-expanded', isOpen);
            });
        }

        // Fermeture au clic extérieur
        document.addEventListener('click', function(e) {
            if (userDropdown && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('is-open');
                if (userToggle) userToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ==========================================
    // 6. FERMETURE DES DROPDOWNS SI 2 OUVERTS
    // ==========================================
    document.addEventListener('click', function(e) {
        const langOpen = document.querySelector('[data-lang-dropdown].is-open');
        const userOpen = document.querySelector('[data-user-dropdown].is-open');
        
        if (langOpen && !langOpen.contains(e.target)) {
            langOpen.classList.remove('is-open');
            const toggle = langOpen.querySelector('[data-lang-toggle]');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
        
        if (userOpen && !userOpen.contains(e.target)) {
            userOpen.classList.remove('is-open');
            const toggle = userOpen.querySelector('[data-user-toggle]');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
    });

    // ==========================================
    // 7. DESKTOP DROPDOWN HOVER (accessibilité)
    // ==========================================
    document.querySelectorAll('.pm-dropdown').forEach(function(dropdown) {
        const toggle = dropdown.querySelector('.pm-dropdown-toggle');
        const menu = dropdown.querySelector('.pm-dropdown-menu');
        
        if (toggle && menu) {
            // Fermeture au clic sur les liens
            menu.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    // On laisse le hover gérer la fermeture
                });
            });
        }
    });

    // ==========================================
    // 8. RESPONSIVE: fermeture sur resize
    // ==========================================
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992 && mobileMenu) {
                closeMobileMenu();
            }
        }, 250);
    });

    console.log('🚀 Navbar Pro SaaS Premium++ initialized');
});


// Effet de scroll - Navbar transparente devient blanche
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.getElementById('mainNavbar');
    const isHomePage = window.location.pathname === '/' || window.location.pathname === '/accueil';
    
    // Appliquer l'effet uniquement sur la page d'accueil
    if (!isHomePage) {
        // Si on est sur une autre page, navbar blanche direct
        navbar.style.background = '#ffffff';
        navbar.style.borderBottom = '1px solid rgba(15, 23, 42, 0.06)';
        navbar.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.04)';
        
        // Changer les couleurs des éléments
        navbar.querySelectorAll('.pm-menu-link, .pm-menu-link i, .pm-lang-btn, .pm-user-btn, .pm-hamburger, .pm-hamburger span').forEach(el => {
            if (el.tagName === 'SPAN' && el.closest('.pm-hamburger')) {
                // Les spans du hamburger
                el.style.background = '#1f2937';
            } else if (el.classList.contains('pm-menu-link') || el.classList.contains('pm-lang-btn') || el.classList.contains('pm-user-btn')) {
                el.style.color = '#1f2937';
                if (el.tagName === 'I' || el.tagName === 'SPAN') {
                    // Pour les icônes et spans
                }
            }
        });
        
        // Logo normal (sans filtre blanc)
        const logo = navbar.querySelector('.pm-brand img');
        if (logo) logo.style.filter = 'none';
        
        return;
    }
    
    // Sur la page d'accueil, effet de scroll
    let lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > 50) {
            // Scrollé -> fond blanc
            navbar.style.background = '#ffffff';
            navbar.style.borderBottom = '1px solid rgba(15, 23, 42, 0.06)';
            navbar.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.04)';
            
            // Texte en noir
            navbar.querySelectorAll('.pm-menu-link, .pm-menu-link i, .pm-lang-btn, .pm-user-btn, .pm-hamburger, .pm-hamburger span, .pm-brand span').forEach(el => {
                if (el.closest('.pm-brand') && el.tagName === 'SPAN') {
                    el.style.color = '#0f172a';
                } else if (el.tagName === 'SPAN' && el.closest('.pm-hamburger')) {
                    el.style.background = '#1f2937';
                } else if (el.classList.contains('pm-menu-link') || el.classList.contains('pm-lang-btn') || el.classList.contains('pm-user-btn')) {
                    el.style.color = '#1f2937';
                } else if (el.tagName === 'I') {
                    el.style.color = '#1f2937';
                }
            });
            
            // Logo normal (sans filtre blanc)
            const logo = navbar.querySelector('.pm-brand img');
            if (logo) logo.style.filter = 'none';
            
            // Boutons
            navbar.querySelectorAll('.pm-btn-soft').forEach(el => {
                el.style.color = '#1f2937';
                el.style.background = '#ffffff';
                el.style.borderColor = 'rgba(15, 23, 42, 0.08)';
            });
            
            navbar.querySelectorAll('.pm-hamburger').forEach(el => {
                el.style.color = '#1f2937';
                el.style.background = '#ffffff';
                el.style.borderColor = '#e2e8f0';
            });
            
        } else {
            // En haut -> transparent
            navbar.style.background = 'transparent';
            navbar.style.borderBottom = '1px solid rgba(255, 255, 255, 0.15)';
            navbar.style.boxShadow = 'none';
            
            // Texte en blanc
            navbar.querySelectorAll('.pm-menu-link, .pm-menu-link i, .pm-lang-btn, .pm-user-btn, .pm-hamburger, .pm-hamburger span, .pm-brand span').forEach(el => {
                if (el.closest('.pm-brand') && el.tagName === 'SPAN') {
                    el.style.color = '#ffffff';
                } else if (el.tagName === 'SPAN' && el.closest('.pm-hamburger')) {
                    el.style.background = '#ffffff';
                } else if (el.classList.contains('pm-menu-link') || el.classList.contains('pm-lang-btn') || el.classList.contains('pm-user-btn')) {
                    el.style.color = '#ffffff';
                } else if (el.tagName === 'I') {
                    el.style.color = '#ffffff';
                }
            });
            
            // Logo blanc
            const logo = navbar.querySelector('.pm-brand img');
            if (logo) logo.style.filter = 'brightness(0) invert(1)';
            
            // Boutons
            navbar.querySelectorAll('.pm-btn-soft').forEach(el => {
                el.style.color = '#ffffff';
                el.style.background = 'rgba(255,255,255,0.1)';
                el.style.borderColor = 'rgba(255,255,255,0.3)';
            });
            
            navbar.querySelectorAll('.pm-hamburger').forEach(el => {
                el.style.color = '#ffffff';
                el.style.background = 'rgba(255,255,255,0.1)';
                el.style.borderColor = 'rgba(255,255,255,0.3)';
            });
        }
        
        lastScroll = currentScroll;
    });
});