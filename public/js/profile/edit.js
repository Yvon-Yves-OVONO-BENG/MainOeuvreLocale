(function () {
            function updateNavbarHeight() {
                const navbar = document.querySelector('.pm-navbar');
                const height = navbar ? Math.ceil(navbar.offsetHeight || 74) : 74;

                document.documentElement.style.setProperty('--mol-navbar-height', height + 'px');
            }

            updateNavbarHeight();

            window.addEventListener('load', updateNavbarHeight);
            window.addEventListener('resize', updateNavbarHeight);
            document.addEventListener('DOMContentLoaded', updateNavbarHeight);
        })();
    


        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('profileForm');
            const toggle = document.getElementById('molGeoEnabled');
            const latitude = document.getElementById('molGeoLatitude');
            const longitude = document.getElementById('molGeoLongitude');
            const status = document.getElementById('molGeoStatus');

            if (!form || !toggle || !latitude || !longitude || !status) return;

            let locating = false;

            const setStatus = (message, error = false) => {
                status.textContent = message;
                status.style.color = error ? '#b42318' : '#198754';
            };

            const hasCoordinates = () => {
                return latitude.value !== ''
                    && longitude.value !== ''
                    && Number.isFinite(Number(latitude.value))
                    && Number.isFinite(Number(longitude.value));
            };

            const requestPosition = () => new Promise((resolve, reject) => {
                if (!window.isSecureContext) {
                    reject(new Error('La géolocalisation nécessite une connexion HTTPS.'));
                    return;
                }

                if (!navigator.geolocation) {
                    reject(new Error('La géolocalisation n’est pas disponible sur cet appareil.'));
                    return;
                }

                locating = true;
                toggle.disabled = true;
                setStatus('Recherche de votre position…');

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        latitude.value = String(position.coords.latitude);
                        longitude.value = String(position.coords.longitude);
                        locating = false;
                        toggle.disabled = false;
                        setStatus('Position récupérée. Enregistrez le profil pour l’activer.');
                        resolve();
                    },
                    (geoError) => {
                        const messages = {
                            1: 'Autorisation refusée. La géolocalisation reste désactivée.',
                            2: 'Position indisponible. Réessayez dans quelques instants.',
                            3: 'Le délai de géolocalisation est dépassé.'
                        };
                        const message = messages[geoError.code] || 'Impossible de récupérer votre position.';
                        locating = false;
                        toggle.disabled = false;
                        toggle.checked = false;
                        latitude.value = '';
                        longitude.value = '';
                        setStatus(message, true);
                        reject(new Error(message));
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 30000,
                        timeout: 15000
                    }
                );
            });

            toggle.addEventListener('change', () => {
                if (!toggle.checked) {
                    latitude.value = '';
                    longitude.value = '';
                    setStatus('La géolocalisation sera désactivée après enregistrement.');
                    return;
                }

                requestPosition().catch(() => {});
            });

            form.addEventListener('submit', async (event) => {
                if (!toggle.checked || hasCoordinates() || locating) return;

                event.preventDefault();
                try {
                    await requestPosition();
                    form.requestSubmit();
                } catch (_) {
                    // Le message est déjà affiché sous le switch.
                }
            });
        });
    


        (function() {
          function initProfileValidation() {
            const form = document.getElementById('profileForm');
            if (!form) return;
            if (form.dataset.profileValidationInitialized === 'true') return;

            form.dataset.profileValidationInitialized = 'true';

            // Configuration des champs requis par profil
            const mode = form.dataset.profileMode || 'particulier';
            const photoRequired = form.dataset.photoRequired === '1';
            const cvRequired = form.dataset.cvRequired === '1';
            const companyRequiredFields = ['email', 'phone', 'country', 'companyLegalName', 'companyTradeName', 'companyRegistrationNumber', 'companyTaxNumber', 'companyContactName', 'city'];
            const talentRequiredFields = ['email', 'phone', 'country', 'fullName', 'sexe', 'adress', 'city', 'bio', 'experience', 'categorie', 'profession', 'experienceYears', 'skills'];
            const particulierRequiredFields = ['email', 'phone', 'country', 'fullName', 'sexe', 'adress', 'city'];

            // Les fichiers déjà enregistrés ne sont pas redemandés en modification.
            if (photoRequired) {
                companyRequiredFields.push('photoFile');
                talentRequiredFields.push('photoFile');
                particulierRequiredFields.push('photoFile');
            }

            if (cvRequired) {
                talentRequiredFields.push('cvFile');
            }

            const fieldConfig = {
                'talent': {
                    required: talentRequiredFields,
                    optional: ['cniFile']
                },
                'company': {
                    required: companyRequiredFields,
                    optional: []
                },
                'particulier': {
                    required: particulierRequiredFields,
                    optional: ['cniFile']
                },
                'moderateur': {
                    required: ['email', 'phone', 'country'],
                    optional: ['fullName', 'sexe', 'adress', 'city', 'bio', 'experience', 'categorie', 'profession', 'experienceYears', 'skills', 'photoFile', 'cniFile', 'cvFile']
                },
                'admin': {
                    required: ['email', 'phone', 'country'],
                    optional: ['fullName', 'sexe', 'adress', 'city', 'bio', 'experience', 'categorie', 'profession', 'experienceYears', 'skills', 'photoFile', 'cniFile', 'cvFile']
                },
                'superAdmin': {
                    required: ['email', 'phone', 'country'],
                    optional: ['fullName', 'sexe', 'adress', 'city', 'bio', 'experience', 'categorie', 'profession', 'experienceYears', 'skills', 'photoFile', 'cniFile', 'cvFile']
                }
            };

            // Récupérer la configuration pour le mode actuel
            const config = fieldConfig[mode] || fieldConfig['particulier'];
            const requiredFieldNames = config.required || [];
            const optionalFieldNames = config.optional || [];

            // Sélectionner tous les conteneurs de champs
            const containers = form.querySelectorAll('.field-container');
            
            // Fonction pour vérifier si un champ est visible
            function isFieldVisible(field) {
                // Les bibliothèques comme TomSelect masquent le SELECT natif.
                // C'est le conteneur fonctionnel qui détermine si le champ est affiché.
                let element = field.closest('.field-container') || field;
                while (element && element !== form) {
                    const style = window.getComputedStyle(element);
                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                        return false;
                    }
                    element = element.parentElement;
                }
                return true;
            }

            function hasValidValue(field) {
                let hasValue = false;

                if (field.tagName === 'SELECT' && field.multiple) {
                    hasValue = Array.from(field.selectedOptions).some(function(option) {
                        return option.value !== '' && option.value !== '0';
                    });
                } else if (field.tagName === 'SELECT') {
                    hasValue = field.value !== '' && field.value !== '0';
                } else if (field.type === 'file') {
                    hasValue = Boolean(field.files && field.files.length > 0);
                } else {
                    hasValue = Boolean(field.value && field.value.trim() !== '');
                }

                return hasValue && field.checkValidity();
            }

            // Appliquer la configuration
            containers.forEach(function(container) {
                const field = container.querySelector('input, select, textarea');
                if (!field) return;

                // Trouver le nom du champ (via name ou id)
                let fieldName = field.name || field.id || '';
                // Extraire le nom du champ (pour les champs Symfony)
                if (fieldName.includes('[')) {
                    const match = fieldName.match(/\[([^\]]+)\]/);
                    if (match) fieldName = match[1];
                }
                // Nettoyer
                fieldName = fieldName.replace(/^account_profile_edit_/, '').replace(/^account_profile_edit\[/, '').replace(/\]$/, '');

                // Déterminer si le champ doit être requis
                const isRequired = requiredFieldNames.includes(fieldName);
                const isOptional = optionalFieldNames.includes(fieldName);

                // Marquer le conteneur
                if (isRequired) {
                    container.dataset.required = 'true';
                    container.classList.add('field-required');
                } else {
                    container.dataset.required = 'false';
                    container.classList.remove('field-required');
                }

                // Ajouter l'attribut required si nécessaire
                if (isRequired) {
                    field.setAttribute('required', 'required');
                    field.dataset.required = 'true';
                } else {
                    field.removeAttribute('required');
                    field.dataset.required = 'false';
                }
            });

            // Fonction pour valider un champ
            function validateField(field) {
                const container = field.closest('.field-container');
                if (!container) return true;

                // Vérifier si le champ doit être requis
                const isRequired = container.dataset.required === 'true';
                if (!isRequired) {
                    field.classList.remove('is-valid', 'is-invalid', 'error');
                    const icon = container.querySelector('.validation-icon');
                    if (icon) icon.classList.remove('show');
                    return true;
                }

                // Si le champ n'est pas visible, on le considère comme valide
                if (!isFieldVisible(field)) {
                    field.classList.remove('is-valid', 'is-invalid', 'error');
                    return true;
                }

                const icon = container.querySelector('.validation-icon');
                
                const isValid = hasValidValue(field);

                field.classList.remove('is-valid', 'is-invalid', 'error');
                
                const tsWrapper = container.querySelector('.ts-wrapper');
                if (tsWrapper) {
                    tsWrapper.classList.remove('is-valid', 'is-invalid');
                }

                if (isValid) {
                    field.classList.add('is-valid');
                    if (tsWrapper) tsWrapper.classList.add('is-valid');
                    if (icon) icon.classList.remove('show');
                } else {
                    if (field.dataset.touched === 'true' || form.dataset.submitted === 'true') {
                        field.classList.add('is-invalid');
                        if (tsWrapper) tsWrapper.classList.add('is-invalid');
                        if (icon) icon.classList.add('show');
                    }
                }
                
                return isValid;
            }

            // Attacher les événements
            const allFields = form.querySelectorAll('input, select, textarea');
            const submitBtn = document.getElementById('submitBtn');

            function updateSubmitButton() {
                let formIsComplete = true;

                allFields.forEach(function(field) {
                    const container = field.closest('.field-container');
                    if (!container || container.dataset.required !== 'true') return;
                    if (!isFieldVisible(field)) return;

                    if (!hasValidValue(field)) {
                        formIsComplete = false;
                    }
                });

                if (submitBtn) {
                    submitBtn.disabled = !formIsComplete;
                    submitBtn.setAttribute('aria-disabled', formIsComplete ? 'false' : 'true');
                    submitBtn.title = formIsComplete
                        ? ''
                        : 'Remplissez tous les champs obligatoires pour enregistrer.';
                }

                return formIsComplete;
            }

            allFields.forEach(function(field) {
                field.addEventListener('focusout', function() {
                    this.dataset.touched = 'true';
                    validateField(this);
                    updateSubmitButton();
                });

                field.addEventListener('input', function() {
                    if (this.dataset.touched === 'true') {
                        validateField(this);
                    }
                    updateSubmitButton();
                });

                field.addEventListener('change', function() {
                    if (this.dataset.touched === 'true') {
                        validateField(this);
                    }
                    updateSubmitButton();
                });
            });

            // Intercepter la soumission
            form.addEventListener('submit', function(e) {
                this.dataset.submitted = 'true';

                let hasError = false;
                let firstErrorField = null;

                allFields.forEach(function(field) {
                    const container = field.closest('.field-container');
                    if (!container) return;

                    const isRequired = container.dataset.required === 'true';
                    if (!isRequired) return;

                    if (!isFieldVisible(field)) return;

                    field.dataset.touched = 'true';
                    
                    const icon = container.querySelector('.validation-icon');
                    
                    const isValid = hasValidValue(field);

                    field.classList.remove('is-valid', 'is-invalid', 'error');
                    
                    const tsWrapper = container.querySelector('.ts-wrapper');
                    if (tsWrapper) {
                        tsWrapper.classList.remove('is-valid', 'is-invalid');
                    }

                    if (!isValid) {
                        field.classList.add('is-invalid', 'error');
                        if (tsWrapper) tsWrapper.classList.add('is-invalid');
                        if (icon) icon.classList.add('show');
                        hasError = true;
                        if (!firstErrorField) firstErrorField = field;
                    } else {
                        field.classList.add('is-valid');
                        if (tsWrapper) tsWrapper.classList.add('is-valid');
                        if (icon) icon.classList.remove('show');
                    }
                });

                if (hasError) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (firstErrorField) {
                        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => firstErrorField.focus(), 300);
                    }
                    
                    const btn = document.getElementById('submitBtn');
                    if (btn) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="typcn typcn-warning"></i> Veuillez remplir tous les champs';
                        btn.classList.add('btn-danger');
                        btn.classList.remove('btn-primary');
                        
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.classList.remove('btn-danger');
                            btn.classList.add('btn-primary');
                        }, 3000);
                    }
                    
                    return false;
                }

                if (!updateSubmitButton()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                return true;
            });

            // Gestion TomSelect
            document.querySelectorAll('.ts-wrapper').forEach(function(wrapper) {
                const select = wrapper.querySelector('select');
                if (!select) return;

                select.addEventListener('change', function() {
                    if (this.dataset.touched !== 'true') {
                        this.dataset.touched = 'true';
                    }
                    validateField(this);
                    updateSubmitButton();
                });
            });

            // Gestion fichiers
            document.querySelectorAll('input[type="file"]').forEach(function(field) {
                field.addEventListener('change', function() {
                    this.dataset.touched = 'true';
                    validateField(this);
                    updateSubmitButton();
                });
            });

            // Appliquer la validation initiale
            allFields.forEach(function(field) {
                validateField(field);
            });
            updateSubmitButton();
          }

          if (document.readyState === 'loading') {
              document.addEventListener('DOMContentLoaded', initProfileValidation, { once: true });
          } else {
              initProfileValidation();
          }

          document.addEventListener('turbo:load', initProfileValidation);
        })();
    


        document.addEventListener('DOMContentLoaded', () => {
        const cat  = document.querySelector('.js-categorie');
        const prof = document.querySelector('.js-profession');
        if (!cat || !prof) return;

        const hasSelect2 = (el) => window.jQuery && jQuery(el).data('select2');

        const resetProf = () => {
            prof.innerHTML = '<option value="">-- Sélectionner une profession --</option>';
            prof.disabled = true;
            if (hasSelect2(prof)) jQuery(prof).trigger('change.select2');
            prof.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const buildUrl = (categorieId) => {
            const baseUrl = cat.dataset.url;
            if (!baseUrl) return `/ajax/professions/${categorieId}`;
            return baseUrl
            .replace(/\/0$/, '/' + categorieId)
            .replace(/categorieId=0\b/, 'categorieId=' + categorieId);
        };

        const loadProfessions = async (categorieId) => {
            const url = buildUrl(categorieId);
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return await res.json();
        };

        const fillProfessions = (data, selectedId = '') => {
            resetProf();

            data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = String(item.id);
            opt.textContent = item.name;
            prof.appendChild(opt);
            });

            prof.disabled = false;

            if (selectedId) {
            prof.value = String(selectedId);
            }

            if (hasSelect2(prof)) jQuery(prof).trigger('change.select2');
            prof.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const refresh = async (initialLoad) => {
            const categorieId = cat.value;

            if (!categorieId) {
            resetProf();
            return;
            }

            const selectedId = initialLoad ? prof.value : '';

            try {
            const data = await loadProfessions(categorieId);
            fillProfessions(data, selectedId);
            } catch (e) {
            console.error('Erreur chargement professions:', e);
            resetProf();
            }
        };

        refresh(true);

        const onChange = () => refresh(false);

        cat.addEventListener('change', onChange);
        if (hasSelect2(cat)) jQuery(cat).on('change', onChange);

        document.addEventListener('submit', function () {
            if (prof) prof.disabled = false;
        }, true);
        });
    


        document.getElementById('cvInput')?.addEventListener('change', function () {
            if (this.files.length) {
                console.log('CV sélectionné :', this.files[0].name);
            }
        });
    


        document.getElementById('cniInput')?.addEventListener('change', function () {
            if (this.files.length) {
            console.log('CNI sélectionnée :', this.files[0].name);
            }
        });
    


        function initSkillsSelect() {
            document.querySelectorAll('.js-skills').forEach(function (el) {
                if (el.tomselect) {
                    el.tomselect.destroy();
                }

                new TomSelect(el, {
                    plugins: {
                        remove_button: {
                            title: 'Retirer'
                        }
                    },
                    maxItems: null,
                    create: false,
                    persist: false,
                    hideSelected: true,
                    closeAfterSelect: false,
                    placeholder: el.dataset.placeholder || 'Choisir des compétences…'
                });
            });
        }

        document.addEventListener('DOMContentLoaded', initSkillsSelect);
        document.addEventListener('turbo:load', initSkillsSelect);
    

(() => {
  const initCountrySelect = () => {
    const element = document.querySelector('.mol-country-select');
    if (!element || element.dataset.countryReady === '1' || typeof window.TomSelect === 'undefined') return;
    element.dataset.countryReady = '1';
    new window.TomSelect(element, {
      create: false,
      allowEmptyOption: true,
      maxItems: 1,
      searchField: ['text'],
      sortField: [{field: 'text', direction: 'asc'}],
      render: {
        option(data, escape) {
          const option = element.querySelector(`option[value="${CSS.escape(String(data.value))}"]`);
          const flag = option?.dataset.flag;
          return `<div class="mol-country-option">${flag ? `<img src="${escape(flag)}" alt="" loading="lazy">` : ''}<span>${escape(data.text)}</span></div>`;
        },
        item(data, escape) {
          const option = element.querySelector(`option[value="${CSS.escape(String(data.value))}"]`);
          const flag = option?.dataset.flag;
          return `<div class="mol-country-option">${flag ? `<img src="${escape(flag)}" alt="">` : ''}<span>${escape(data.text)}</span></div>`;
        }
      },
      onChange() {
        element.dataset.touched = 'true';
        element.classList.remove('is-invalid', 'error');
        element.dispatchEvent(new Event('change', {bubbles:true}));
      }
    });
  };
  document.addEventListener('DOMContentLoaded', initCountrySelect, {once:true});
  document.addEventListener('turbo:load', initCountrySelect);
})();
