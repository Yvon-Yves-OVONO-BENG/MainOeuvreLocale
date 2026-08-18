'use strict';

// ============================================
// 1. EFFET DE SCROLL - CORRIGÉ
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.pm-navbar-pro');
    const isHomePage = window.location.pathname === '/' || window.location.pathname === '/accueil';
    
    // Si ce n'est PAS la page d'accueil, on garde le style blanc fixe
    if (!isHomePage) {
        if (navbar) {
            navbar.style.background = '#ffffff';
            navbar.style.borderBottom = '1px solid rgba(15,23,42,0.06)';
            navbar.style.boxShadow = '0 4px 24px rgba(0,0,0,0.06)';
            navbar.classList.add('scrolled');
            
            // Mettre à jour tous les éléments
            updateNavbarElements(navbar, true);
        }
        return;
    }
    
    // PAGE D'ACCUEIL - EFFET DE SCROLL
    function updateNavbarElements(navbar, isScrolled) {
        if (!navbar) return;
        
        // Logo
        const logo = navbar.querySelector('.pm-brand img');
        if (logo) {
            logo.style.filter = isScrolled ? 'none' : 'brightness(0) invert(1)';
        }
        
        // Liens du menu
        navbar.querySelectorAll('.pm-navbar-inner > ul li a.pm-menu-link').forEach(el => {
            if (isScrolled) {
                el.style.color = '#1f2937 !important';
                el.style.fontWeight = '500 !important';
                if (el.classList.contains('active')) {
                    el.style.background = 'rgba(37,99,235,0.10) !important';
                } else {
                    el.style.background = 'transparent !important';
                }
                const icon = el.querySelector('i');
                if (icon) icon.style.color = '#1f2937';
            } else {
                el.style.color = '#ffffff !important';
                el.style.fontWeight = '600 !important';
                if (el.classList.contains('active')) {
                    el.style.background = 'rgba(255,255,255,0.20) !important';
                } else {
                    el.style.background = 'transparent !important';
                }
                const icon = el.querySelector('i');
                if (icon) icon.style.color = '#ffffff';
            }
        });
        
        // Bouton langue
        const langBtn = navbar.querySelector('.pm-lang-btn');
        if (langBtn) {
            if (isScrolled) {
                langBtn.style.color = '#1f2937';
                langBtn.style.borderColor = 'rgba(15,23,42,0.15)';
                langBtn.style.background = 'rgba(255,255,255,0.9)';
            } else {
                langBtn.style.color = '#ffffff';
                langBtn.style.borderColor = 'rgba(255,255,255,0.25)';
                langBtn.style.background = 'rgba(255,255,255,0.08)';
            }
        }
        
        // Boutons Connexion / Inscription
        const authBtns = navbar.querySelectorAll('.mol-auth-btn');
        
        authBtns.forEach(btn => {
            if (btn.matches(':hover')) return;
        
            if (isScrolled) {
                btn.style.color = '#1f2937';
                btn.style.borderColor = 'rgba(15,23,42,0.15)';
                btn.style.background = 'rgba(255,255,255,0.9)';
            } else {
                btn.style.color = '#ffffff';
                btn.style.borderColor = 'rgba(255,255,255,0.25)';
                btn.style.background = 'rgba(255,255,255,0.08)';
            }
        });
        
        // Bouton utilisateur
        const userBtn = navbar.querySelector('.pm-user-btn');
        if (userBtn) {
            if (isScrolled) {
                userBtn.style.color = '#1f2937';
                userBtn.style.borderColor = 'rgba(15,23,42,0.15)';
                userBtn.style.background = 'rgba(255,255,255,0.9)';
            } else {
                userBtn.style.color = '#ffffff';
                userBtn.style.borderColor = 'rgba(255,255,255,0.25)';
                userBtn.style.background = 'rgba(255,255,255,0.08)';
            }
        }
        
        // Hamburger
        const hamburger = document.getElementById('pmHamburgerBtn');
        if (hamburger) {
            if (isScrolled) {
                hamburger.style.color = '#1f2937';
                hamburger.style.borderColor = 'rgba(15,23,42,0.15)';
                hamburger.style.background = 'rgba(255,255,255,0.9)';
                hamburger.querySelectorAll('span').forEach(el => el.style.background = '#1f2937');
            } else {
                hamburger.style.color = '#ffffff';
                hamburger.style.borderColor = 'rgba(255,255,255,0.25)';
                hamburger.style.background = 'rgba(255,255,255,0.08)';
                hamburger.querySelectorAll('span').forEach(el => el.style.background = '#ffffff');
            }
        }
    }
    
    function updateNavbar(isScrolled) {
        if (!navbar) return;
        
        if (isScrolled) {
            navbar.style.background = '#ffffff';
            navbar.style.borderBottom = '1px solid rgba(15,23,42,0.06)';
            navbar.style.boxShadow = '0 4px 24px rgba(0,0,0,0.06)';
            navbar.classList.add('scrolled');
        } else {
            navbar.style.background = 'transparent';
            navbar.style.borderBottom = '1px solid rgba(255,255,255,0.12)';
            navbar.style.boxShadow = 'none';
            navbar.classList.remove('scrolled');
        }
        
        // Mettre à jour tous les éléments
        updateNavbarElements(navbar, isScrolled);
    }
    
    let scrollTimeout;
    let lastScrollY = window.pageYOffset;
    
    window.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            const currentScrollY = window.pageYOffset;
            // Mettre à jour seulement si la position a changé
            if (currentScrollY !== lastScrollY) {
                lastScrollY = currentScrollY;
                updateNavbar(currentScrollY > 50);
            }
        }, 10);
    });
    
    // Initialisation - FORCER la mise à jour après un court délai
    setTimeout(() => {
        updateNavbar(window.pageYOffset > 50);
    }, 100);
    
    // FORCER la mise à jour au retour en haut de page (click sur le logo ou lien)
    document.querySelectorAll('a[href="#"], a[href="/"]').forEach(link => {
        link.addEventListener('click', function() {
            setTimeout(() => {
                updateNavbar(window.pageYOffset > 50);
            }, 50);
        });
    });
});
// ============================================
// 2. DROPDOWN LANGUE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const langSelector = document.querySelector('.pm-lang-selector');
    if (!langSelector) return;
    
    const langBtn = langSelector.querySelector('.pm-lang-btn');
    const langDropdown = langSelector.querySelector('.pm-lang-dropdown');
    
    if (langBtn && langDropdown) {
        langBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = langDropdown.style.opacity === '1';
            langDropdown.style.opacity = isOpen ? '0' : '1';
            langDropdown.style.visibility = isOpen ? 'hidden' : 'visible';
            langDropdown.style.pointerEvents = isOpen ? 'none' : 'auto';
            langDropdown.style.transform = isOpen ? 'translateY(8px)' : 'translateY(0)';
            const icon = this.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        });
        
        document.addEventListener('click', function(e) {
            if (!langSelector.contains(e.target)) {
                langDropdown.style.opacity = '0';
                langDropdown.style.visibility = 'hidden';
                langDropdown.style.pointerEvents = 'none';
                langDropdown.style.transform = 'translateY(8px)';
                const icon = langBtn.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        });
    }
});

// ============================================
// 3. DROPDOWN COMPTE UTILISATEUR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.querySelector('.pm-user-dropdown');
    if (!userDropdown) return;
    
    const userBtn = userDropdown.querySelector('.pm-user-btn');
    const userMenu = userDropdown.querySelector('.pm-user-dropdown-menu');
    
    if (userBtn && userMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = userMenu.style.opacity === '1';
            userMenu.style.opacity = isOpen ? '0' : '1';
            userMenu.style.visibility = isOpen ? 'hidden' : 'visible';
            userMenu.style.pointerEvents = isOpen ? 'none' : 'auto';
            userMenu.style.transform = isOpen ? 'translateY(8px)' : 'translateY(0)';
            const icon = this.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        });
        
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                userMenu.style.opacity = '0';
                userMenu.style.visibility = 'hidden';
                userMenu.style.pointerEvents = 'none';
                userMenu.style.transform = 'translateY(8px)';
                const icon = userBtn.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }
        });
    }
});

// ============================================
// 4. DROPDOWN PROFILS (Desktop)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.querySelector('.pm-dropdown');
    if (!dropdown) return;
    
    const toggle = dropdown.querySelector('.pm-dropdown-toggle');
    const menu = dropdown.querySelector('.pm-dropdown-menu');
    
    if (toggle && menu) {
        let timeoutId;
        
        dropdown.addEventListener('mouseenter', function() {
            clearTimeout(timeoutId);
            menu.style.opacity = '1';
            menu.style.visibility = 'visible';
            menu.style.pointerEvents = 'auto';
            menu.style.transform = 'translateX(-50%) translateY(0)';
            const icon = toggle.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = 'rotate(180deg)';
        });
        
        dropdown.addEventListener('mouseleave', function() {
            timeoutId = setTimeout(() => {
                menu.style.opacity = '0';
                menu.style.visibility = 'hidden';
                menu.style.pointerEvents = 'none';
                menu.style.transform = 'translateX(-50%) translateY(8px)';
                const icon = toggle.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            }, 150);
        });
        
        menu.addEventListener('mouseenter', function() {
            clearTimeout(timeoutId);
        });
        
        menu.addEventListener('mouseleave', function() {
            dropdown.dispatchEvent(new Event('mouseleave'));
        });
    }
});

// ============================================
// 5. MENU MOBILE - CORRIGÉ ULTIME
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('pmHamburgerBtn');
    const mobileMenu = document.getElementById('pmMobileMenu');
    const overlay = document.getElementById('pmOverlay');
    const closeBtn = document.getElementById('pmMobileClose');
    
    const spans = hamburger ? hamburger.querySelectorAll('span') : [];
    
    function openMobileMenu() {
        if (mobileMenu) {
            mobileMenu.classList.add('open');
            mobileMenu.style.right = '0';
            mobileMenu.style.visibility = 'visible';
            mobileMenu.style.opacity = '1';
            mobileMenu.style.pointerEvents = 'auto';
        }
        if (overlay) {
            overlay.classList.add('active');
            overlay.style.opacity = '1';
            overlay.style.visibility = 'visible';
            overlay.style.pointerEvents = 'auto';
        }
        document.body.style.overflow = 'hidden';
        document.body.classList.add('pm-mobile-open');
        
        if (hamburger) {
            hamburger.classList.add('active');
            if (spans.length === 3) {
                spans[0].style.transform = 'rotate(45deg) translate(6px, 6px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(6px, -6px)';
            }
        }
    }
    
    function closeMobileMenu() {
        if (mobileMenu) {
            mobileMenu.classList.remove('open');
            mobileMenu.style.right = '-420px';
            mobileMenu.style.visibility = 'hidden';
            mobileMenu.style.opacity = '0';
            mobileMenu.style.pointerEvents = 'none';
        }
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.opacity = '0';
            overlay.style.visibility = 'hidden';
            overlay.style.pointerEvents = 'none';
        }
        document.body.style.overflow = '';
        document.body.classList.remove('pm-mobile-open');
        
        if (hamburger) {
            hamburger.classList.remove('active');
            if (spans.length === 3) {
                spans[0].style.transform = 'rotate(0deg) translate(0, 0)';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'rotate(0deg) translate(0, 0)';
            }
        }
    }
    
    if (hamburger) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isOpen = mobileMenu && mobileMenu.classList.contains('open');
            
            if (isOpen) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
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
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMobileMenu();
        }
    });
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992) {
                closeMobileMenu();
            }
        }, 250);
    });
});

// ============================================
// 6. DROPDOWN MOBILE (Profils)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const mobileDropdown = document.querySelector('.pm-mobile-dropdown');
    if (!mobileDropdown) return;
    
    const toggle = mobileDropdown.querySelector('.pm-mobile-dropdown-toggle');
    const menu = mobileDropdown.querySelector('ul');
    
    if (toggle && menu) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const isOpen = menu.style.maxHeight && menu.style.maxHeight !== '0px';
            menu.style.maxHeight = isOpen ? '0' : '500px';
            const icon = this.querySelector('.fa-chevron-down');
            if (icon) icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    }
});

// ============================================
// 7. FORCER LA MISE À JOUR AU CHARGEMENT DE TURBO
// ============================================
document.addEventListener('turbo:load', function() {
    const navbar = document.querySelector('.pm-navbar-pro');
    if (navbar) {
        const isHomePage = window.location.pathname === '/' || window.location.pathname === '/accueil';
        if (!isHomePage) {
            navbar.classList.add('scrolled');
            navbar.style.background = '#ffffff';
            navbar.style.borderBottom = '1px solid rgba(15,23,42,0.06)';
            navbar.style.boxShadow = '0 4px 24px rgba(0,0,0,0.06)';
        } else {
            setTimeout(() => {
                const isScrolled = window.pageYOffset > 50;
                if (isScrolled) {
                    navbar.classList.add('scrolled');
                    navbar.style.background = '#ffffff';
                    navbar.style.borderBottom = '1px solid rgba(15,23,42,0.06)';
                    navbar.style.boxShadow = '0 4px 24px rgba(0,0,0,0.06)';
                } else {
                    navbar.classList.remove('scrolled');
                    navbar.style.background = 'transparent';
                    navbar.style.borderBottom = '1px solid rgba(255,255,255,0.12)';
                    navbar.style.boxShadow = 'none';
                }
            }, 50);
        }
    }
});

// ============================================
// 8. FORCER LA MISE À JOUR APRÈS LE SCROLL COMPLET
// ============================================
document.addEventListener('scroll', function() {
    // Si on est en haut de page, forcer la mise à jour
    if (window.pageYOffset === 0) {
        const navbar = document.querySelector('.pm-navbar-pro');
        if (navbar && window.location.pathname === '/' || window.location.pathname === '/accueil') {
            navbar.classList.remove('scrolled');
            navbar.style.background = 'transparent';
            navbar.style.borderBottom = '1px solid rgba(255,255,255,0.12)';
            navbar.style.boxShadow = 'none';
            
            // Réappliquer les styles non-scrollés
            navbar.querySelectorAll('.pm-navbar-inner > ul li a.pm-menu-link').forEach(el => {
                el.style.color = '#ffffff !important';
                el.style.fontWeight = '600 !important';
                const icon = el.querySelector('i');
                if (icon) icon.style.color = '#ffffff';
            });
            
            const logo = navbar.querySelector('.pm-brand img');
            if (logo) logo.style.filter = 'brightness(0) invert(1)';
        }
    }
}, { passive: true });

// ============================================
// 9. FORCER LE RETOUR EN HAUT
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Intercepter les clics sur les liens d'ancrage et les logos
    document.querySelectorAll('a[href="#"], a[href="/"], a[href*="accueil"]').forEach(link => {
        link.addEventListener('click', function(e) {
            // Laisser le comportement normal se faire
            setTimeout(() => {
                const navbar = document.querySelector('.pm-navbar-pro');
                if (navbar && window.pageYOffset < 10) {
                    const isHomePage = window.location.pathname === '/' || window.location.pathname === '/accueil';
                    if (isHomePage) {
                        navbar.classList.remove('scrolled');
                        navbar.style.background = 'transparent';
                        navbar.style.borderBottom = '1px solid rgba(255,255,255,0.12)';
                        navbar.style.boxShadow = 'none';
                        
                        // Réappliquer les styles blancs
                        navbar.querySelectorAll('.pm-navbar-inner > ul li a.pm-menu-link').forEach(el => {
                            el.style.color = '#ffffff !important';
                            el.style.fontWeight = '600 !important';
                            if (!el.classList.contains('active')) {
                                el.style.background = 'transparent !important';
                            }
                            const icon = el.querySelector('i');
                            if (icon) icon.style.color = '#ffffff';
                        });
                        
                        const logo = navbar.querySelector('.pm-brand img');
                        if (logo) logo.style.filter = 'brightness(0) invert(1)';
                    }
                }
            }, 50);
        });
    });
});





document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mol-auth-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.classList.add('is-loading');
        });
    });
});
