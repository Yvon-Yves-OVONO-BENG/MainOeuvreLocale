// public/js/admin/professions.js
(function ($, window, document) {
    'use strict';

    var config = window.ADMIN_PROFESSIONS_CONFIG || {};
    var currentPage = 1;
    var selectedIds = [];
    var searchTimer = null;
    var isModalOpen = false;
    var categoriesLoaded = false;
    var categoriesRequest = null;
    var dataRequest = null;
    var editRequest = null;
    var editRequestToken = 0;
    var saveRequest = null;
    var isSaving = false;
    var activeEditButton = null;
    var editButtonsObserver = null;
    var saveButtonObserver = null;
    var initializedForm = null;
    var nativeSubmitHandler = null;

    var requiredRoutes = [
        'dataUrl',
        'categoriesUrl',
        'createUrl',
        'showUrlTemplate',
        'editUrlTemplate',
        'deleteUrlTemplate',
        'batchDeleteUrl',
        'photosBaseUrl'
    ];

    function hasValidConfiguration() {
        var missing = requiredRoutes.filter(function (key) {
            return typeof config[key] !== 'string' || config[key] === '';
        });

        if (missing.length === 0) {
            return true;
        }

        console.error(
            'Configuration des professions incomplète :',
            missing.join(', ')
        );
        showError('La configuration de la page est incomplète.');
        return false;
    }

    function normalizeEntityId(value) {
        var id = String(value == null ? '' : value).trim();

        if (
            id === '' ||
            id.toLowerCase() === 'nan' ||
            id.toLowerCase() === 'undefined' ||
            id.toLowerCase() === 'null'
        ) {
            return '';
        }

        return id;
    }

    function entityUrl(template, id) {
        var entityId = normalizeEntityId(id);

        if (!entityId) {
            throw new Error('Identifiant de profession invalide.');
        }

        return template.replace(
            /\/0(?=\/|$)/,
            '/' + encodeURIComponent(entityId)
        );
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function photoUrl(filename) {
        var encodedFilename = String(filename)
            .split('/')
            .map(encodeURIComponent)
            .join('/');

        return config.photosBaseUrl.replace(/\/?$/, '/') + encodedFilename;
    }

    function responseMessage(xhr, fallback) {
        if (
            xhr &&
            xhr.responseJSON &&
            typeof xhr.responseJSON.message === 'string'
        ) {
            return xhr.responseJSON.message;
        }

        return fallback;
    }

    function showError(message) {
        if (window.Swal) {
            window.Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: message
            });
            return;
        }

        window.alert(message);
    }

    function showSuccess(message) {
        if (window.Swal) {
            window.Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: message,
                timer: 2000,
                showConfirmButton: false
            });
        }
    }

    function init() {
        config = window.ADMIN_PROFESSIONS_CONFIG || {};

        if (!hasValidConfiguration()) {
            return;
        }

        /*
         * Turbo ne doit jamais prendre en charge ce formulaire : son
         * enregistrement et le rafraîchissement du tableau sont déjà gérés
         * intégralement en AJAX dans ce fichier.
         */
        $('#professionForm').attr('data-turbo', 'false');

        loadCategories();
        loadData();
        initDragAndDrop();

        /*
         * Les gestionnaires sont nommés puis remplacés afin d'éviter leur
         * duplication si le script est réévalué après une navigation AJAX.
         */
        $(document).off('.professions');
        $('#professionModal').off('.professions');
        $('#professionForm').off('.professions');
        $('#tableBody').off('.professions');

        $(document).on('keydown.professions', function (event) {
            if (event.key === 'Escape' && isModalOpen) {
                closeModal();
            }
        });

        $('#professionModal').on('click.professions', function (event) {
            if (event.target === this) {
                closeModal();
            }
        });

        /*
         * Le bouton possède son propre spinner. Cette protection empêche un
         * éventuel spinner global de la page de désactiver le bouton avant
         * l'envoi réel du formulaire.
         */
        $('#btnSaveProfession')
            .addClass('no-spinner')
            .attr('data-no-spinner', 'true')
            .on('click.professions', function (event) {
                /*
                 * Le clic doit continuer jusqu'au submit du formulaire, mais
                 * ne doit jamais atteindre le spinner global de la page.
                 */
                event.stopImmediatePropagation();
            });

        /*
         * Le listener natif en phase de capture passe avant un éventuel
         * onsubmit présent dans l'ancien Twig. Un seul POST AJAX est ainsi
         * autorisé et Turbo ne peut pas conserver un état de soumission entre
         * deux modifications.
         */
        nativeSubmitHandler = function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return window.saveProfession(event);
        };
        initializedForm.addEventListener(
            'submit',
            nativeSubmitHandler,
            true
        );

        /*
         * Un seul gestionnaire délégué reste actif après chaque rechargement
         * AJAX du tableau. Il remplace le onclick injecté dans chaque ligne.
         */
        $('#tableBody').on(
            'click.professions',
            '.action-btn-edit',
            function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
                unlockEditButtons(this);
                window.openEditModal(
                    $(this).attr('data-profession-id'),
                    this
                );
                return false;
            }
        );

        $('#tableBody').on(
            'click.professions',
            '.action-btn-delete',
            function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
                window.deleteProfession(
                    $(this).attr('data-profession-id')
                );
                return false;
            }
        );

        initEditButtonsGuard();
        initSaveButtonGuard();
    }

    function resetEditButton(button) {
        if (!button) {
            return;
        }

        if (button.disabled) {
            button.disabled = false;
        }

        ['is-loading', 'loading', 'btn-loading'].forEach(function (name) {
            if (button.classList.contains(name)) {
                button.classList.remove(name);
            }
        });

        if (button.getAttribute('aria-busy') !== 'false') {
            button.setAttribute('aria-busy', 'false');
        }

        var icon = button.querySelector('i');
        if (icon && icon.className !== 'fas fa-pen') {
            icon.className = 'fas fa-pen';
        }
    }

    function unlockEditButtons(exceptButton) {
        var buttons = document.querySelectorAll(
            '#tableBody .action-btn-edit'
        );

        Array.prototype.forEach.call(buttons, function (button) {
            if (button !== exceptButton && button !== activeEditButton) {
                resetEditButton(button);
            }
        });
    }

    function initEditButtonsGuard() {
        var tableBody = document.getElementById('tableBody');

        if (!tableBody || !window.MutationObserver) {
            unlockEditButtons();
            return;
        }

        if (editButtonsObserver) {
            editButtonsObserver.disconnect();
        }

        /*
         * Certains scripts globaux ajoutent "disabled" à tous les boutons.
         * Cette garde ne touche qu'aux boutons Modifier qui ne chargent pas
         * actuellement une profession.
         */
        editButtonsObserver = new window.MutationObserver(function () {
            unlockEditButtons(activeEditButton);
        });

        editButtonsObserver.observe(tableBody, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [
                'disabled',
                'class',
                'aria-busy'
            ]
        });

        unlockEditButtons();
    }

    function resetSaveButton() {
        var button = document.getElementById('btnSaveProfession');
        var isEdit;

        if (!button || isSaving) {
            return;
        }

        isEdit = String($('#professionId').val() || '') !== '';

        button.disabled = false;
        button.type = 'submit';
        button.classList.add('no-spinner');
        button.classList.remove('is-loading', 'loading', 'btn-loading');
        button.setAttribute('data-no-spinner', 'true');
        button.setAttribute('data-turbo', 'false');
        button.setAttribute('aria-busy', 'false');

        if (isEdit) {
            button.classList.remove('btn-primary');
            button.classList.add('btn-warning');
            button.innerHTML =
                '<i class="fas fa-edit me-1"></i>Modifier';
        } else {
            button.classList.remove('btn-warning');
            button.classList.add('btn-primary');
            button.innerHTML =
                '<i class="fas fa-save me-1"></i>Enregistrer';
        }
    }

    function initSaveButtonGuard() {
        var button = document.getElementById('btnSaveProfession');

        if (!button) {
            return;
        }

        if (saveButtonObserver) {
            saveButtonObserver.disconnect();
        }

        if (window.MutationObserver) {
            /*
             * Certains scripts globaux réappliquent "disabled" juste après
             * la fin du POST. Tant qu'aucun enregistrement n'est en cours,
             * le bouton de la modale doit rester utilisable.
             */
            saveButtonObserver = new window.MutationObserver(function () {
                if (
                    !isSaving &&
                    isModalOpen &&
                    (
                        button.disabled ||
                        button.classList.contains('is-loading') ||
                        button.classList.contains('loading') ||
                        button.classList.contains('btn-loading') ||
                        button.getAttribute('aria-busy') === 'true'
                    )
                ) {
                    resetSaveButton();
                }
            });

            saveButtonObserver.observe(button, {
                attributes: true,
                attributeFilter: [
                    'disabled',
                    'class',
                    'aria-busy'
                ]
            });
        }

        resetSaveButton();
    }

    function openModal() {
        $('body').addClass('modal-open-custom');
        $('#professionModal').addClass('active');
        isModalOpen = true;
        resetSaveButton();
    }

    window.closeModal = function () {
        var form = document.getElementById('professionForm');

        $('#professionModal').removeClass('active');
        $('body').removeClass('modal-open-custom');
        isModalOpen = false;

        if (form) {
            form.reset();
        }

        $('#professionId').val('');
        $('#professionNom').removeClass('is-invalid');
        removePhoto();
        resetSaveButton();
    };

    function initDragAndDrop() {
        var uploadZone = document.getElementById('photoUploadZone');
        var fileInput = document.getElementById('professionPhoto');

        if (!uploadZone || !fileInput) {
            return;
        }

        if (uploadZone.dataset.professionsDndInitialized === 'true') {
            return;
        }

        uploadZone.dataset.professionsDndInitialized = 'true';

        uploadZone.addEventListener('dragover', function (event) {
            event.preventDefault();
            uploadZone.classList.add('border-primary');
        });

        uploadZone.addEventListener('dragleave', function (event) {
            event.preventDefault();
            uploadZone.classList.remove('border-primary');
        });

        uploadZone.addEventListener('drop', function (event) {
            event.preventDefault();
            uploadZone.classList.remove('border-primary');

            if (!event.dataTransfer.files.length) {
                return;
            }

            try {
                fileInput.files = event.dataTransfer.files;
            } catch (error) {
                console.warn('Glisser-déposer non pris en charge.', error);
                return;
            }

            previewPhoto(fileInput);
        });
    }

    window.loadCategories = function () {
        if (
            categoriesRequest &&
            categoriesRequest.readyState !== 4
        ) {
            return categoriesRequest;
        }

        if (categoriesLoaded) {
            return $.Deferred().resolve().promise();
        }

        categoriesRequest = $.ajax({
            url: config.categoriesUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            timeout: 15000,
            data: {
                limit: 1000
            }
        }).done(function (response) {
            var options = [
                '<option value="">Sélectionner une catégorie</option>'
            ];

            var categories = Array.isArray(response.data)
                ? response.data.slice()
                : [];

            categories.sort(function (firstCategory, secondCategory) {
                var firstName = String(firstCategory.nom || '').trim();
                var secondName = String(secondCategory.nom || '').trim();
                var firstIsOther = firstName.localeCompare(
                    'Autres',
                    'fr',
                    { sensitivity: 'base' }
                ) === 0;
                var secondIsOther = secondName.localeCompare(
                    'Autres',
                    'fr',
                    { sensitivity: 'base' }
                ) === 0;

                if (firstIsOther !== secondIsOther) {
                    return firstIsOther ? 1 : -1;
                }

                return firstName.localeCompare(
                    secondName,
                    'fr',
                    {
                        sensitivity: 'base',
                        numeric: true
                    }
                );
            });

            categories.forEach(function (category) {
                var categoryId = normalizeEntityId(category.id);

                if (!categoryId) {
                    return;
                }

                options.push(
                    '<option value="' + escapeHtml(categoryId) + '">' +
                    escapeHtml(category.nom) +
                    '</option>'
                );
            });

            $('#professionCategorie').html(options.join(''));
            categoriesLoaded = true;
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }

            console.error(
                'Erreur chargement catégories :',
                xhr.status,
                config.categoriesUrl,
                xhr.responseText
            );
            showError('Impossible de charger les catégories.');
        });

        return categoriesRequest;
    };

    window.loadData = function (resetPage) {
        if (resetPage === true) {
            currentPage = 1;
        }

        window.clearTimeout(searchTimer);

        searchTimer = window.setTimeout(function () {
            var search = String($('#searchInput').val() || '').trim();

            if (dataRequest && dataRequest.readyState !== 4) {
                dataRequest.abort();
            }

            dataRequest = $.ajax({
                url: config.dataUrl,
                method: 'GET',
                dataType: 'json',
                cache: false,
                timeout: 15000,
                data: {
                    search: search,
                    page: currentPage
                }
            }).done(function (response) {
                renderTable(response.data || []);
                renderPagination(response);
                $('#totalCount').text(Number(response.total) || 0);
            }).fail(function (xhr) {
                if (xhr.statusText === 'abort') {
                    return;
                }

                console.error(
                    'Erreur chargement professions :',
                    xhr.status,
                    config.dataUrl,
                    xhr.responseText
                );

                renderTable([]);
                renderPagination({
                    totalPages: 0,
                    currentPage: 1
                });
                $('#totalCount').text('0');
                showError('Impossible de charger les professions.');
            });
        }, 300);
    };

    function renderTable(data) {
        var html = [];

        if (!data.length) {
            html.push(
                '<tr>',
                '<td colspan="7" class="empty-state">',
                '<div class="empty-icon"><i class="fas fa-briefcase"></i></div>',
                '<div class="empty-title">Aucune profession trouvée</div>',
                '<div class="empty-desc">Commencez par créer votre première profession</div>',
                '</td>',
                '</tr>'
            );
        } else {
            data.forEach(function (item) {
                var id = normalizeEntityId(item.id);
                var name = escapeHtml(item.nom || 'Sans nom');

                if (!id) {
                    console.warn('Profession ignorée : slug invalide.', item);
                    return;
                }
                var rawDescription = String(item.description || '');
                var shortDescription = rawDescription.length > 80
                    ? rawDescription.substring(0, 80) + '…'
                    : rawDescription;
                var description = shortDescription
                    ? escapeHtml(shortDescription)
                    : '<span style="color:var(--gray-400);">—</span>';
                var category = item.categorie && item.categorie.nom
                    ? '<span class="badge-premium badge-premium-primary">' +
                        '<span class="dot"></span>' +
                        escapeHtml(item.categorie.nom) +
                      '</span>'
                    : '<span class="badge-premium badge-premium-gray">Non catégorisé</span>';
                var photo = renderPhoto(item, name);
                var jobs = Number(item.jobs_count) || 0;
                var jobsClass = 'jobs-badge-zero';

                if (jobs > 20) {
                    jobsClass = 'jobs-badge-high';
                } else if (jobs > 5) {
                    jobsClass = 'jobs-badge-medium';
                } else if (jobs > 0) {
                    jobsClass = 'jobs-badge-low';
                }

                html.push(
                    '<tr>',
                    '<td>',
                    '<label class="checkbox-premium">',
                    '<input type="checkbox" class="row-checkbox" value="',
                    escapeHtml(id), '" ',
                    'onchange="updateSelection()" ',
                    selectedIds.indexOf(id) !== -1 ? 'checked' : '',
                    '>',
                    '<span class="checkmark"></span>',
                    '</label>',
                    '</td>',
                    '<td>', photo, '</td>',
                    '<td><strong style="color:var(--gray-800);">', name, '</strong></td>',
                    '<td style="color:var(--gray-600);">', description, '</td>',
                    '<td>', category, '</td>',
                    '<td style="text-align:center;">',
                    '<span class="jobs-badge ', jobsClass, '">',
                    '<span class="icon"><i class="fas fa-briefcase"></i></span>',
                    jobs,
                    '</span>',
                    '</td>',
                    '<td>',
                    '<div class="actions-group" style="justify-content:center;">',
                    '<button type="button" class="action-btn action-btn-edit no-spinner" ',
                    'data-no-spinner="true" data-turbo="false" ',
                    'data-profession-id="', escapeHtml(id), '" aria-busy="false" ',
                    'title="Modifier">',
                    '<i class="fas fa-pen"></i>',
                    '<span class="tooltip-action">Modifier</span>',
                    '</button>',
                    '<button type="button" class="action-btn action-btn-delete" ',
                    'data-profession-id="', escapeHtml(id), '" title="Supprimer">',
                    '<i class="fas fa-trash-alt"></i>',
                    '<span class="tooltip-action">Supprimer</span>',
                    '</button>',
                    '</div>',
                    '</td>',
                    '</tr>'
                );
            });
        }

        $('#tableBody').html(html.join(''));
        updateSelectAllState();
    }

    function renderPhoto(item, escapedName) {
        if (!item.photo) {
            return [
                '<div class="photo-cell-premium">',
                '<div class="no-photo"><i class="fas fa-image"></i></div>',
                '</div>'
            ].join('');
        }

        var url = escapeHtml(photoUrl(item.photo));

        return [
            '<div class="photo-cell-premium">',
            '<img class="profession-photo" src="', url, '" alt="', escapedName,
            '" loading="lazy" onerror="handleProfessionPhotoError(this)">',
            '<div class="photo-tooltip-premium">',
            '<img src="', url, '" alt="', escapedName,
            '" onerror="this.closest(\'.photo-tooltip-premium\').remove()">',
            '<span class="tooltip-name">', escapedName, '</span>',
            '</div>',
            '</div>'
        ].join('');
    }

    window.handleProfessionPhotoError = function (image) {
        var parent = image ? image.parentElement : null;
        var fallback;
        var icon;

        if (!parent) {
            return;
        }

        image.remove();

        if (parent.querySelector('.no-photo')) {
            return;
        }

        fallback = document.createElement('div');
        fallback.className = 'no-photo';
        icon = document.createElement('i');
        icon.className = 'fas fa-image';
        fallback.appendChild(icon);
        parent.insertBefore(fallback, parent.firstChild);
    };

    function renderPagination(response) {
        var totalPages = Number(response.totalPages) || 0;
        var activePage = Number(response.currentPage) || 1;
        var html = [];
        var previousWasGap = false;

        if (totalPages <= 1) {
            $('#pagination').empty();
            return;
        }

        html.push(pageButton(
            activePage - 1,
            '<span class="arrow"><i class="fas fa-chevron-left"></i></span>',
            activePage === 1
        ));

        for (var page = 1; page <= totalPages; page += 1) {
            var visible = page === 1 ||
                page === totalPages ||
                Math.abs(page - activePage) <= 1;

            if (visible) {
                html.push(pageButton(page, String(page), false, page === activePage));
                previousWasGap = false;
            } else if (!previousWasGap) {
                html.push(
                    '<button type="button" class="page-btn" disabled>…</button>'
                );
                previousWasGap = true;
            }
        }

        html.push(pageButton(
            activePage + 1,
            '<span class="arrow"><i class="fas fa-chevron-right"></i></span>',
            activePage === totalPages
        ));

        $('#pagination').html(html.join(''));
    }

    function pageButton(page, label, disabled, active) {
        return [
            '<button type="button" class="page-btn',
            active ? ' active' : '',
            disabled ? ' disabled' : '',
            '" onclick="goToPage(', page, ')"',
            disabled ? ' disabled' : '',
            '>',
            label,
            '</button>'
        ].join('');
    }

    window.goToPage = function (page) {
        var targetPage = Number(page);

        if (targetPage < 1 || targetPage === currentPage) {
            return;
        }

        currentPage = targetPage;
        loadData();

        var table = $('.table-responsive');
        if (table.length && table.offset()) {
            $('html, body').animate({
                scrollTop: table.offset().top - 100
            }, 300);
        }
    };

    window.toggleSelectAll = function () {
        var checked = $('#selectAll').prop('checked');

        $('.row-checkbox').each(function () {
            var id = normalizeEntityId($(this).val());

            $(this).prop('checked', checked);

            if (!id) {
                return;
            }

            if (checked && selectedIds.indexOf(id) === -1) {
                selectedIds.push(id);
            } else if (!checked) {
                selectedIds = selectedIds.filter(function (selectedId) {
                    return selectedId !== id;
                });
            }
        });

        updateToolbar();
    };

    window.updateSelection = function () {
        selectedIds = $('.row-checkbox:checked').map(function () {
            return normalizeEntityId($(this).val());
        }).get().filter(function (id) {
            return id !== '';
        });

        updateSelectAllState();
        updateToolbar();
    };

    function updateSelectAllState() {
        var total = $('.row-checkbox').length;
        var checked = $('.row-checkbox:checked').length;

        $('#selectAll').prop('checked', total > 0 && checked === total);
    }

    function updateToolbar() {
        if (selectedIds.length) {
            $('#toolbar').stop(true, true).slideDown(200);
            $('#selectedCount').text(
                selectedIds.length + ' profession(s) sélectionnée(s)'
            );
        } else {
            $('#toolbar').stop(true, true).slideUp(200);
        }
    }

    window.previewPhoto = function (input) {
        var file = input && input.files ? input.files[0] : null;
        var allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!file) {
            return;
        }

        if (file.size > 15 * 1024 * 1024) {
            showError('La photo ne doit pas dépasser 15 Mo avant compression.');
            input.value = '';
            return;
        }

        if (allowedTypes.indexOf(file.type) === -1) {
            showError('Utilisez une image JPG, PNG, GIF ou WebP.');
            input.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            $('#photoUploadZone').hide();
            $('#photoPreview').attr('src', event.target.result);
            $('#photoPreviewContainer').show();
        };
        reader.readAsDataURL(file);
    };

    window.removePhoto = function () {
        $('#professionPhoto').val('');
        $('#photoPreview').attr('src', '');
        $('#photoPreviewContainer').hide();
        $('#photoUploadZone').show();
    };

    window.openCreateModal = function () {
        var form = document.getElementById('professionForm');

        if (form) {
            form.reset();
        }

        $('#professionId').val('');
        $('#professionModalLabel').html(
            '<i class="fas fa-plus-circle me-2"></i>Nouvelle Profession'
        );
        $('#btnSaveProfession')
            .removeClass('btn-warning')
            .addClass('btn-primary')
            .html('<i class="fas fa-save me-1"></i>Enregistrer');
        $('#professionNom').removeClass('is-invalid');

        removePhoto();
        openModal();
    };

    window.openEditModal = function (id, triggerButton) {
        var requestedId = normalizeEntityId(id);
        var requestToken;
        var categoryRequest;
        var professionRequest;

        if (!requestedId) {
            showError('Cette profession est invalide.');
            return false;
        }

        if (editRequest && editRequest.readyState !== 4) {
            editRequest.abort();
        }

        if (activeEditButton) {
            resetEditButton(activeEditButton);
        }

        unlockEditButtons(triggerButton);
        activeEditButton = triggerButton || null;
        editRequestToken += 1;
        requestToken = editRequestToken;

        if (activeEditButton) {
            $(activeEditButton)
                .prop('disabled', true)
                .addClass('is-loading')
                .attr('aria-busy', 'true')
                .find('i')
                .attr('class', 'fas fa-spinner fa-spin');
        }

        categoryRequest = loadCategories();

        professionRequest = $.ajax({
            url: entityUrl(config.showUrlTemplate, requestedId),
            method: 'GET',
            dataType: 'json',
            cache: false,
            timeout: 15000
        });
        editRequest = professionRequest;

        /*
         * On attend explicitement les deux requêtes. Cette construction ne
         * conserve aucune callback imbriquée de la précédente profession.
         */
        $.when(professionRequest, categoryRequest).done(function (
            professionResult
        ) {
            var profession = Array.isArray(professionResult)
                ? professionResult[0]
                : professionResult;

            if (
                requestToken !== editRequestToken ||
                normalizeEntityId(profession.id) !== requestedId
            ) {
                return;
            }

            $('#professionId').val(profession.id);
            $('#professionNom').val(profession.nom || '');
            $('#professionDescription').val(profession.description || '');
            $('#professionCategorie').val(
                profession.categorie == null
                    ? ''
                    : String(profession.categorie)
            );
            $('#professionNom').removeClass('is-invalid');

            if (profession.photo) {
                $('#photoPreview').attr(
                    'src',
                    photoUrl(profession.photo)
                );
                $('#photoPreviewContainer').show();
                $('#photoUploadZone').hide();
            } else {
                removePhoto();
            }

            $('#professionModalLabel').html(
                '<i class="fas fa-edit me-2"></i>Modifier Profession'
            );
            $('#btnSaveProfession')
                .removeClass('btn-primary')
                .addClass('btn-warning')
                .prop('disabled', false)
                .html('<i class="fas fa-edit me-1"></i>Modifier');

            openModal();
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }

            console.error(
                'Erreur chargement profession :',
                xhr.status,
                xhr.responseText
            );
            showError(
                xhr.statusText === 'timeout'
                    ? 'Le chargement a pris trop de temps. Veuillez réessayer.'
                    : 'Impossible de charger cette profession.'
            );
        }).always(function () {
            if (requestToken === editRequestToken) {
                editRequest = null;
            }

            if (activeEditButton === triggerButton && activeEditButton) {
                resetEditButton(activeEditButton);

                activeEditButton = null;
            }

            unlockEditButtons();
        });

        return false;
    };

    window.saveProfession = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        /*
         * Certains formulaires appellent cette fonction à la fois via
         * onclick et onsubmit. Un seul POST doit partir.
         */
        if (isSaving) {
            return false;
        }

        var id = String($('#professionId').val() || '');
        var name = String($('#professionNom').val() || '').trim();
        var button = $('#btnSaveProfession');
        var formData;
        var selectedFile;

        if (!name) {
            $('#professionNom').addClass('is-invalid');
            showError('Veuillez saisir un nom de profession.');
            return false;
        }

        $('#professionNom').removeClass('is-invalid');

        formData = new FormData();
        formData.append('nom', name);
        formData.append(
            'description',
            String($('#professionDescription').val() || '').trim()
        );
        formData.append(
            'categorie',
            String($('#professionCategorie').val() || '')
        );

        selectedFile = $('#professionPhoto')[0]
            ? $('#professionPhoto')[0].files[0]
            : null;
        if (selectedFile) {
            formData.append('photo', selectedFile);
        }

        isSaving = true;
        button
            .prop('disabled', true)
            .addClass('is-loading')
            .attr('aria-busy', 'true')
            .html('<i class="fas fa-spinner fa-spin me-1"></i>Enregistrement...');

        saveRequest = $.ajax({
            url: id
                ? entityUrl(config.editUrlTemplate, id)
                : config.createUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 30000
        }).done(function (response) {
            /*
             * Libérer l'état AVANT de fermer la modale. closeModal peut ainsi
             * remettre immédiatement le bouton dans un état neuf, prêt pour
             * la profession suivante.
             */
            isSaving = false;
            saveRequest = null;
            closeModal();
            loadData();
            showSuccess(response.message || 'Profession enregistrée.');
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }

            console.error('Erreur enregistrement profession :', xhr);
            showError(responseMessage(
                xhr,
                xhr.statusText === 'timeout'
                    ? 'Le serveur met trop de temps à répondre. Veuillez réessayer.'
                    : 'Impossible d’enregistrer la profession.'
            ));
        }).always(function () {
            isSaving = false;
            saveRequest = null;
            resetSaveButton();
            unlockEditButtons();
        });

        return false;
    };

    window.deleteProfession = function (id) {
        id = normalizeEntityId(id);

        if (!id) {
            showError('Cette profession est invalide.');
            return;
        }

        confirmDeletion(
            'Supprimer cette profession ?',
            'Cette action est irréversible.',
            function () {
                return $.ajax({
                    url: entityUrl(config.deleteUrlTemplate, id),
                    method: 'DELETE',
                    dataType: 'json'
                });
            }
        );
    };

    window.deleteSelected = function () {
        if (!selectedIds.length) {
            return;
        }

        confirmDeletion(
            'Supprimer la sélection ?',
            'Vous allez supprimer ' + selectedIds.length + ' profession(s).',
            function () {
                return $.ajax({
                    url: config.batchDeleteUrl,
                    method: 'POST',
                    contentType: 'application/json; charset=UTF-8',
                    dataType: 'json',
                    data: JSON.stringify({
                        ids: selectedIds
                    })
                });
            }
        );
    };

    function confirmDeletion(title, text, requestFactory) {
        if (!window.Swal) {
            return;
        }

        window.Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            requestFactory().done(function (response) {
                selectedIds = [];
                updateToolbar();
                loadData();
                showSuccess(response.message || 'Suppression terminée.');
            }).fail(function (xhr) {
                console.error('Erreur suppression profession :', xhr);
                showError(responseMessage(
                    xhr,
                    'Impossible de supprimer la profession.'
                ));
            });
        });
    }

    function resetPageState() {
        editRequestToken += 1;

        window.clearTimeout(searchTimer);

        [categoriesRequest, dataRequest, editRequest, saveRequest]
            .forEach(function (request) {
                if (request && request.readyState !== 4) {
                    request.abort();
                }
            });

        categoriesRequest = null;
        dataRequest = null;
        editRequest = null;
        saveRequest = null;
        isSaving = false;
        categoriesLoaded = false;

        if (activeEditButton) {
            resetEditButton(activeEditButton);
            activeEditButton = null;
        }

        if (editButtonsObserver) {
            editButtonsObserver.disconnect();
            editButtonsObserver = null;
        }

        if (saveButtonObserver) {
            saveButtonObserver.disconnect();
            saveButtonObserver = null;
        }

        if (initializedForm && nativeSubmitHandler) {
            initializedForm.removeEventListener(
                'submit',
                nativeSubmitHandler,
                true
            );
        }
        nativeSubmitHandler = null;

        $(document).off('.professions');
        $('#professionModal').off('.professions');
        $('#professionForm').off('.professions');
        $('#tableBody').off('.professions');

        initializedForm = null;
    }

    function destroy() {
        resetPageState();
        document.removeEventListener('turbo:load', boot);
        document.removeEventListener(
            'turbo:before-cache',
            resetPageState
        );
    }

    function boot() {
        var form = document.getElementById('professionForm');

        if (!form) {
            return;
        }

        if (initializedForm === form) {
            unlockEditButtons();
            return;
        }

        initializedForm = form;
        init();
    }

    /*
     * Nettoie une ancienne instance si Turbo réévalue ce fichier, puis
     * réinitialise la page après chaque navigation Turbo sans rechargement.
     */
    if (typeof window.__destroyAdminProfessions === 'function') {
        window.__destroyAdminProfessions();
    }
    window.__destroyAdminProfessions = destroy;

    $(boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:before-cache', resetPageState);
})(window.jQuery, window, document);
