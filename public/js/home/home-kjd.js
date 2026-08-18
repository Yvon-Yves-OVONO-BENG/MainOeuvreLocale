(function () {
				const input = document.getElementById('vipJobSearch');
				const clearBtn = document.getElementById('vipClearSearch');
				if (! input) 
				return;


				// Tous les jobs dans la section (tous tabs confondus)
				const jobs = Array.from(document.querySelectorAll('.vip-latest-jobs .job'));

				// Cache "aucun résultat" (optionnel)
				const empty = document.createElement('div');
				empty.className = 'text-center text-muted py-4';
				empty.style.display = 'none';
				empty.textContent = input.dataset.emptyText || 'Aucun résultat.';
				const body = document.querySelector('.vip-latest-jobs .vip-body') || document.body;
				body.appendChild(empty);

				function normalize(s) {
				return(s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); // enlève accents
				}

				function filter() {
				const q = normalize(input.value.trim());
				clearBtn.style.display = q.length ? 'grid' : 'none';

				let visible = 0;

				jobs.forEach(card => { // texte indexé
				const text = normalize(card.innerText);
				const ok = ! q || text.includes(q);

				card.style.display = ok ? '' : 'none';
				if (ok) 
				visible++;

				});

				empty.style.display = (q && visible === 0) ? 'block' : 'none';
				}

				// debounce léger (évite recalcul à chaque touche trop vite)
				let t = null;
				input.addEventListener('input', () => {
				window.clearTimeout(t);
				t = window.setTimeout(filter, 120);
				});

				clearBtn.addEventListener('click', () => {
				input.value = '';
				input.focus();
				filter();
				});

				// initial
				filter();
				})();
			


		// Script CORRIGÉ pour gérer le changement de style du menu lors du scroll
		document.addEventListener('DOMContentLoaded', function() {
			// Cibler TOUS les éléments de navigation possibles
			const selectors = [
				'.navbar', '.navbar-custom', '.navbar-default', '.main-nav', '.main-menu',
				'.site-header', '.site-nav', '.header', 'header nav', '[role="navigation"]',
				'.navigation', '#navbar', '#main-nav', '#navigation'
			];
			
			let navbar = null;
			
			// Trouver le premier élément de navigation qui existe
			for (let selector of selectors) {
				const element = document.querySelector(selector);
				if (element) {
					navbar = element;
					console.log('Menu trouvé:', selector); // Pour déboguer
					break;
				}
			}
			
			// Si aucun menu n'est trouvé, on cherche n'importe quelle balise nav
			if (!navbar) {
				navbar = document.querySelector('nav');
				if (navbar) {
					console.log('Menu nav trouvé');
				}
			}
			
			if (navbar) {
				// Fonction pour vérifier la position de scroll
				function checkScroll() {
					if (window.scrollY > 50) {
						navbar.classList.add('scrolled');
						console.log('Menu scrolled - blanc'); // Pour déboguer
					} else {
						navbar.classList.remove('scrolled');
						console.log('Menu normal - transparent'); // Pour déboguer
					}
				}
				
				// Vérifier au chargement
				checkScroll();
				
				// Vérifier lors du scroll
				window.addEventListener('scroll', checkScroll);
			} else {
				console.log('Aucun menu trouvé sur la page');
			}
		});
	


    (function(){
      const root = document.getElementById('vipSalarySlider');
      if(!root) return;

      const minEl = document.getElementById('vipSalaryMin');
      const maxEl = document.getElementById('vipSalaryMax');
      const text  = document.getElementById('vipSalaryText');
      const range = document.getElementById('vipSalaryRange');
      const tFrom = document.getElementById('vipThumbFrom');
      const tTo   = document.getElementById('vipThumbTo');

      const MIN = parseInt(root.dataset.min || '0', 10);
      const MAX = parseInt(root.dataset.max || '1000000', 10);

      let from = clamp(parseInt(root.dataset.from || MIN, 10), MIN, MAX);
      let to   = clamp(parseInt(root.dataset.to   || MAX, 10), MIN, MAX);
      if(from > to){ const tmp=from; from=to; to=tmp; }

      function clamp(v,a,b){ return Math.max(a, Math.min(b, v)); }
      function pct(v){ return ( (v - MIN) / (MAX - MIN) ) * 100; }

      function render(){
        const p1 = pct(from);
        const p2 = pct(to);

        tFrom.style.left = p1 + '%';
        tTo.style.left   = p2 + '%';

        const left = Math.min(p1,p2);
        const right = Math.max(p1,p2);
        range.style.left = left + '%';
        range.style.width = (right - left) + '%';

        minEl.value = from;
        maxEl.value = to;
        text.value = `${from} - ${to}`;
      }

      function valueFromClientX(clientX){
        const rect = root.getBoundingClientRect();
        const x = clamp(clientX - rect.left, 0, rect.width);
        const ratio = x / rect.width;
        return Math.round(MIN + ratio * (MAX - MIN));
      }

      function drag(which, clientX){
        const v = valueFromClientX(clientX);
        if(which === 'from'){
          from = clamp(v, MIN, to);
        }else{
          to = clamp(v, from, MAX);
        }
        render();
      }

      function bindThumb(thumb, which){
        let dragging = false;

        const start = (e)=>{
          dragging = true;
          thumb.setPointerCapture?.(e.pointerId);
          drag(which, e.clientX);
        };
        const move = (e)=>{
          if(!dragging) return;
          drag(which, e.clientX);
        };
        const end = ()=>{
          dragging = false;
        };

        thumb.addEventListener('pointerdown', start);
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', end);
      }

      // click on track => move closest thumb
      root.addEventListener('pointerdown', (e)=>{
        if(e.target === tFrom || e.target === tTo) return;
        const v = valueFromClientX(e.clientX);
        const distFrom = Math.abs(v - from);
        const distTo   = Math.abs(v - to);
        if(distFrom <= distTo) from = clamp(v, MIN, to);
        else to = clamp(v, from, MAX);
        render();
      });

      bindThumb(tFrom, 'from');
      bindThumb(tTo, 'to');
      render();
    })();
  


    function initVipToggles(){
      document.querySelectorAll('.vip-toggle').forEach((btn) => {
        // évite de binder 2 fois si Turbo recharge
        if (btn.dataset.bound === "1") return;
        btn.dataset.bound = "1";

        const id = btn.getAttribute('data-target');
        const panel = id ? document.getElementById(id) : null;
        if(!panel) return;

        const text = btn.querySelector('.vip-toggle-text');
        const icon = btn.querySelector('i');

        const expandText   = btn.getAttribute('data-expand')   || 'Tout afficher';
        const collapseText = btn.getAttribute('data-collapse') || 'Réduire';

        const setState = (open) => {
          panel.hidden = !open;
          if(text) text.textContent = open ? collapseText : expandText;
          if(icon) icon.className = open ? 'fa fa-minus' : 'fa fa-list';
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        // état initial
        setState(false);

        btn.addEventListener('click', () => {
          setState(panel.hidden); // hidden => ouvrir, sinon fermer
        });
      });
    }

    function runInit(){
      // attendre 1 tick pour être sûr que Turbo a fini d’injecter le DOM
      requestAnimationFrame(initVipToggles);
    }

    // ✅ 1) Si la page est déjà chargée (cas Turbo), on init direct
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', runInit);
    } else {
      runInit();
    }

    // ✅ 2) Events Turbo (Symfony UX Turbo)
    document.addEventListener('turbo:load', runInit);
    document.addEventListener('turbo:render', runInit);
    document.addEventListener('turbo:frame-load', runInit);

    // ✅ 3) Back/forward cache
    window.addEventListener('pageshow', runInit);
  


    (function () {
        function resetHeroRegistrationButton() {
            document.querySelectorAll('[data-hero-register]').forEach(function (button) {
                button.classList.remove('is-loading');
                button.removeAttribute('aria-busy');
            });
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-hero-register]');

            if (
                !button
                || event.defaultPrevented
                || event.button !== 0
                || event.ctrlKey
                || event.metaKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');
        });

        window.addEventListener('pageshow', resetHeroRegistrationButton);
        document.addEventListener('turbo:load', resetHeroRegistrationButton);
    })();
  


    (function () {
      function fixTopbarAfterRefresh() {
        // supprimer backdrops (offcanvas / modal) qui traînent
        document.querySelectorAll('.offcanvas-backdrop, .modal-backdrop').forEach(e => e.remove());

        // nettoyer body
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        // fermer tout offcanvas/collapse ouvert
        document.querySelectorAll('.offcanvas.show').forEach(el => el.classList.remove('show'));
        document.querySelectorAll('.collapse.show').forEach(el => el.classList.remove('show'));

        // fermer dropdowns ouverts
        document.querySelectorAll('.dropdown-menu.show').forEach(el => el.classList.remove('show'));
      }

      // 1) sur refresh normal
      document.addEventListener('DOMContentLoaded', fixTopbarAfterRefresh);

      // 2) quand le navigateur restaure la page (F5 / back-forward cache)
      window.addEventListener('pageshow', fixTopbarAfterRefresh);
    })();
  


    (function () {
      if (window.__molHomeAjaxPaginationBound) {
        return;
      }

      window.__molHomeAjaxPaginationBound = true;
      const requests = new Map();

      async function loadHomePage(region, section, href) {
        const previousRequest = requests.get(section);
        if (previousRequest) {
          previousRequest.abort();
        }

        const controller = new AbortController();
        requests.set(section, controller);
        region.classList.add('mol-home-pagination-loading');
        region.setAttribute('aria-busy', 'true');

        const requestUrl = new URL(href, window.location.origin);
        requestUrl.hash = '';
        requestUrl.searchParams.set('home_section', section);

        try {
          const response = await fetch(requestUrl.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
            headers: {
              'Accept': 'text/html',
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          if (!response.ok) {
            throw new Error('Réponse AJAX invalide');
          }

          const html = await response.text();
          const documentFragment = new DOMParser().parseFromString(html, 'text/html');
          const replacement = documentFragment.querySelector('[data-home-ajax-pagination="' + section + '"]');

          if (!replacement) {
            throw new Error('Section AJAX introuvable');
          }

          replacement.classList.remove('mol-home-pagination-loading');
          replacement.removeAttribute('aria-busy');
          region.replaceWith(replacement);

          const offsetTop = replacement.getBoundingClientRect().top + window.scrollY - 84;
          const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          window.scrollTo({
            top: Math.max(0, offsetTop),
            behavior: reduceMotion ? 'auto' : 'smooth'
          });
        } catch (error) {
          if (error.name === 'AbortError') {
            return;
          }

          window.location.assign(href);
        } finally {
          if (requests.get(section) === controller) {
            requests.delete(section);
          }

          if (region.isConnected) {
            region.classList.remove('mol-home-pagination-loading');
            region.removeAttribute('aria-busy');
          }
        }
      }

      document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
          return;
        }

        if (!(event.target instanceof Element)) {
          return;
        }

        const link = event.target.closest('a[href]');
        if (!link || link.classList.contains('disabled') || link.classList.contains('active')) {
          return;
        }

        const region = link.closest('[data-home-ajax-pagination]');
        if (!region) {
          return;
        }

        const section = region.dataset.homeAjaxPagination;
        const isJobsPagination = section === 'jobs' && link.closest('.vip-pager');
        const isAnnoncesPagination = section === 'annonces' && link.closest('.anpub-pager');

        if (!isJobsPagination && !isAnnoncesPagination) {
          return;
        }

        event.preventDefault();
        loadHomePage(region, section, link.href);
      });
    })();
  


    (function () {
      const form = document.getElementById('vipLiveSearchForm');
      if (!form) return;

      const qInput = document.getElementById('vipLiveQ');
      const cityInput = document.getElementById('vipLiveCity');
      const professionSelect = document.getElementById('vipLiveProfession');
      const pageInput = form.querySelector('input[name="page"]');

      let timer = null;

      const submitLive = () => {
        if (pageInput) pageInput.value = '1';
        form.requestSubmit();
      };

      const debounceSubmit = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(submitLive, 250);
      };

      if (qInput) {
        qInput.addEventListener('input', debounceSubmit);
      }

      if (cityInput) {
        cityInput.addEventListener('input', debounceSubmit);
      }

      if (professionSelect) {
        professionSelect.addEventListener('change', submitLive);
      }
    })();
  


    
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ph-hero-tags-scroll]').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') {
                return;
            }

            wrap.dataset.bound = '1';

            const scroller = wrap.querySelector('[data-ph-hero-tags-scroller]');
            const prevBtn = wrap.querySelector('[data-ph-hero-tags-prev]');
            const nextBtn = wrap.querySelector('[data-ph-hero-tags-next]');

            if (!scroller || !prevBtn || !nextBtn) {
                return;
            }

            function updateButtons() {
                const canScrollLeft = scroller.scrollLeft > 4;
                const canScrollRight = scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 4;

                prevBtn.classList.toggle('is-disabled', !canScrollLeft);
                nextBtn.classList.toggle('is-disabled', !canScrollRight);
            }

            prevBtn.addEventListener('click', function () {
                scroller.scrollBy({
                    left: -260,
                    behavior: 'smooth'
                });
            });

            nextBtn.addEventListener('click', function () {
                scroller.scrollBy({
                    left: 260,
                    behavior: 'smooth'
                });
            });

            scroller.addEventListener('scroll', updateButtons);
            window.addEventListener('resize', updateButtons);

            setTimeout(updateButtons, 200);
            updateButtons();
        });
    });

      (function () {
        function initPhHeroSlider() {
            const slider = document.getElementById('phHeroSlider');

            if (!slider || slider.dataset.bound === '1') {
                return;
            }

            slider.dataset.bound = '1';

            const slides = Array.from(slider.querySelectorAll('.ph-hero-bg-slide'));

            if (!slides.length) {
                return;
            }

            let current = 0;
            const delay = 6200;

            function showSlide(index) {
                current = (index + slides.length) % slides.length;

                slides.forEach(function (slide, i) {
                    slide.classList.toggle('active', i === current);
                });
            }

            function nextSlide() {
                showSlide(current + 1);
            }

            showSlide(0);
            window.setInterval(nextSlide, delay);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPhHeroSlider);
        } else {
            initPhHeroSlider();
        }

        document.addEventListener('turbo:load', initPhHeroSlider);
    })();
  


    (function () {
        /**
        * Construit une bande réellement infinie :
        * 1. Répète les professions jusqu'à remplir toute la zone visible.
        * 2. Duplique ensuite cette première partie.
        * 3. L'animation CSS de -50% passe ainsi d'une copie à l'autre
        *    sans espace vide et sans rupture visible.
        */
        function initVipJobsTickerLoop() {
            document
                .querySelectorAll('.vip-jobs-ticker-hero')
                .forEach(function (hero) {
                    const mask = hero.querySelector('.vip-jobs-ticker-mask');
                    const track = hero.querySelector('.vip-jobs-ticker-track');

                    if (!mask || !track) {
                        return;
                    }

                    const content = track.querySelector(
                        '.vip-jobs-ticker-content:not([data-vip-ticker-copy])'
                    );

                    if (!content) {
                        return;
                    }

                    /*
                    * Arrête très brièvement l'animation pendant la reconstruction.
                    * Aucune modification du fichier CSS.
                    */
                    track.style.setProperty('animation', 'none', 'important');

                    /*
                    * Nettoyage avant reconstruction :
                    * utile avec Turbo, le retour navigateur ou le redimensionnement.
                    */
                    track
                        .querySelectorAll('[data-vip-ticker-copy="1"]')
                        .forEach(function (copy) {
                            copy.remove();
                        });

                    content
                        .querySelectorAll('[data-vip-ticker-generated="1"]')
                        .forEach(function (item) {
                            item.remove();
                        });

                    const originalItems = Array.from(content.children);

                    if (!originalItems.length) {
                        track.style.removeProperty('animation');
                        return;
                    }

                    const visibleWidth = mask.clientWidth;

                    if (visibleWidth <= 0) {
                        track.style.removeProperty('animation');
                        return;
                    }

                    /*
                    * Répète la liste jusqu'à ce que la première moitié
                    * soit au moins aussi large que la zone visible.
                    */
                    let security = 0;

                    while (
                        content.scrollWidth < visibleWidth &&
                        security < 50
                    ) {
                        originalItems.forEach(function (originalItem) {
                            const clone = originalItem.cloneNode(true);

                            clone.dataset.vipTickerGenerated = '1';
                            clone.setAttribute('aria-hidden', 'true');

                            if (clone.matches('a, button, input, select, textarea')) {
                                clone.setAttribute('tabindex', '-1');
                            }

                            clone
                                .querySelectorAll(
                                    'a, button, input, select, textarea, [tabindex]'
                                )
                                .forEach(function (focusableElement) {
                                    focusableElement.setAttribute('tabindex', '-1');
                                });

                            content.appendChild(clone);
                        });

                        security++;
                    }

                    /*
                    * Deuxième moitié strictement identique.
                    * Elle vient directement derrière la première.
                    */
                    const secondContent = content.cloneNode(true);

                    secondContent.dataset.vipTickerCopy = '1';
                    secondContent.setAttribute('aria-hidden', 'true');

                    secondContent
                        .querySelectorAll(
                            'a, button, input, select, textarea, [tabindex]'
                        )
                        .forEach(function (focusableElement) {
                            focusableElement.setAttribute('tabindex', '-1');
                        });

                    track.appendChild(secondContent);

                    /*
                    * Redémarre proprement l'animation CSS depuis le début.
                    */
                    void track.offsetWidth;

                    requestAnimationFrame(function () {
                        track.style.removeProperty('animation');
                    
                        // Vitesse réduite pour laisser le temps de lire chaque profession.
                        track.style.animationDuration = '90s';
                    });
                });
        }

        /*
        * Fonction globale pour éviter plusieurs initialisations avec Turbo.
        */
        window.initVipJobsTickerLoop = initVipJobsTickerLoop;

        if (!window.vipJobsTickerLoopEventsBound) {
            window.vipJobsTickerLoopEventsBound = true;

            document.addEventListener('turbo:load', function () {
                window.initVipJobsTickerLoop();
            });

            window.addEventListener('pageshow', function () {
                window.initVipJobsTickerLoop();
            });

            let resizeTimer = null;

            window.addEventListener('resize', function () {
                window.clearTimeout(resizeTimer);

                resizeTimer = window.setTimeout(function () {
                    window.initVipJobsTickerLoop();
                }, 180);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                initVipJobsTickerLoop,
                { once: true }
            );
        } else {
            initVipJobsTickerLoop();
        }

        /*
        * Reconstruit une dernière fois après le chargement des polices,
        * car la largeur des textes peut légèrement changer.
        */
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                initVipJobsTickerLoop();
            });
        }
    })();
  

(() => {
  const loadDeferredHeroImages = () => {
    document.querySelectorAll('.ph-hero-bg-slide[data-bg]').forEach((slide) => {
      slide.style.backgroundImage = `url("${slide.dataset.bg}")`;
      slide.removeAttribute('data-bg');
    });
  };
  if ('requestIdleCallback' in window) {
    window.addEventListener('load', () => window.requestIdleCallback(loadDeferredHeroImages, {timeout: 1800}), {once:true});
  } else {
    window.addEventListener('load', () => window.setTimeout(loadDeferredHeroImages, 350), {once:true});
  }
})();
