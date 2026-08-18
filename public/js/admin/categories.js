// public/js/admin/categories.js
(function ($, window, document) {
    'use strict';

    window.ADMIN_CATEGORIES_SINGLE_HANDLER = true;

    var config = window.ADMIN_CATEGORIES_CONFIG || {};
    var currentPage = 1;
    var selectedIds = [];
    var searchTimer = null;
    var isModalOpen = false;
    var showRequest = null;
    var saveRequest = null;

    var requiredRoutes = [
        'dataUrl',
        'createUrl',
        'showUrlTemplate',
        'editUrlTemplate',
        'deleteUrlTemplate',
        'batchDeleteUrl'
    ];

    function hasValidConfiguration() {
        var missing = requiredRoutes.filter(function (key) {
            return typeof config[key] !== 'string' || config[key] === '';
        });

        if (missing.length === 0) {
            return true;
        }

        console.error(
            'Configuration des catégories incomplète :',
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
            throw new Error('Identifiant de catégorie invalide.');
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
                timer: 1800,
                showConfirmButton: false
            });
        }
    }

    function setTableLoading() {
        $('#tableBody').html(
            '<tr>' +
                '<td colspan="5" style="padding:2.5rem;text-align:center;color:#6B7280;">' +
                    '<i class="fas fa-spinner fa-spin" style="margin-right:.5rem;"></i>' +
                    'Chargement des catégories...' +
                '</td>' +
            '</tr>'
        );
    }

    function init() {
        if (!hasValidConfiguration()) {
            return;
        }

        loadData();

        $('#categorieModal')
            .off('click.categories')
            .on('click.categories', function (event) {
            if (event.target === this) {
                closeModal();
            }
        });

        $(document)
            .off('keydown.categories')
            .on('keydown.categories', function (event) {
            if (event.key === 'Escape' && isModalOpen) {
                closeModal();
            }
        });

        /*
         * Un seul gestionnaire enregistre le formulaire. Cette liaison
         * remplace les interceptions concurrentes de Turbo/spinner qui
         * pouvaient lancer deux sauvegardes et mémoriser le HTML du spinner.
         */
        $('#categorieForm')
            .attr('data-turbo', 'false')
            .attr('data-no-submit-spinner', '1')
            .off('submit.categories')
            .on('submit.categories', window.saveCategorie);

        $('#btnSaveCategorie')
            .off('click.categories')
            .on('click.categories', function (event) {
                event.stopPropagation();
            });

        /*
         * Les slugs sont des chaînes opaques. Les actions sont déléguées au
         * tableau afin de ne jamais les convertir en nombres ni produire NaN.
         */
        $('#tableBody')
            .off('.categoriesRows')
            .on(
                'change.categoriesRows',
                '.row-checkbox',
                function () {
                    window.toggleSelection($(this).val(), this.checked);
                }
            )
            .on(
                'click.categoriesRows',
                '.action-btn-edit',
                function (event) {
                    event.preventDefault();
                    window.editCategorie(
                        $(this).attr('data-category-id')
                    );
                }
            )
            .on(
                'click.categoriesRows',
                '.action-btn-delete',
                function (event) {
                    event.preventDefault();
                    window.deleteCategorie(
                        $(this).attr('data-category-id')
                    );
                }
            );

        resetSaveButton();
    }

    function resetSaveButton() {
        var isEdit = String($('#categorieId').val() || '').trim() !== '';
        var button = $('#btnSaveCategorie');
        var form = document.getElementById('categorieForm');

        if (!button.length) {
            return;
        }

        button
            .prop('disabled', false)
            .removeClass(
                'is-loading loading btn-loading mol-submit-loading'
            )
            .attr('aria-busy', 'false')
            .attr('data-no-spinner', 'true')
            .attr('data-turbo', 'false')
            .removeAttr(
                'data-loading data-original-html data-original-value'
            )
            .css('min-width', '')
            .html(
                isEdit
                    ? '<i class="fas fa-edit me-1"></i>Modifier'
                    : '<i class="fas fa-save me-1"></i>Enregistrer'
            );

        $('#categorieForm')
            .attr('data-turbo', 'false')
            .attr('data-no-submit-spinner', '1')
            .removeAttr('data-category-submitting')
            .removeAttr('data-form-submitted')
            .removeAttr('data-clicked-submit-button')
            .removeClass('is-submitting');

        if (form) {
            form._molSubmitButton = null;
        }
    }

    function openModal() {
        resetSaveButton();
        $('body').addClass('modal-open-custom');
        $('#categorieModal').addClass('active');
        isModalOpen = true;

        window.setTimeout(function () {
            resetSaveButton();
            $('#categorieNom').trigger('focus');
        }, 80);
    }

    window.openCreateModal = function () {
        var form = document.getElementById('categorieForm');

        if (form) {
            form.reset();
        }

        $('#categorieId').val('');
        $('#categorieNom').removeClass('is-invalid');
        $('#categorieModalLabel').html(
            '<i class="fas fa-plus-circle"></i> Nouvelle Catégorie'
        );

        resetSaveButton();
        openModal();
    };

    window.closeModal = function () {
        var form = document.getElementById('categorieForm');

        $('#categorieModal').removeClass('active');
        $('body').removeClass('modal-open-custom');
        isModalOpen = false;

        if (form) {
            form.reset();
        }

        $('#categorieId').val('');
        $('#categorieNom').removeClass('is-invalid');
        resetSaveButton();
    };

    window.loadData = function (resetPage) {
        if (resetPage === true) {
            currentPage = 1;
        }

        window.clearTimeout(searchTimer);

        searchTimer = window.setTimeout(function () {
            var search = String($('#searchInput').val() || '').trim();

            setTableLoading();

            $.ajax({
                url: config.dataUrl,
                method: 'GET',
                dataType: 'json',
                cache: false,
                data: {
                    search: search,
                    page: currentPage
                }
            }).done(function (response) {
                var totalPages = Number(response.totalPages) || 0;

                if (totalPages > 0 && currentPage > totalPages) {
                    currentPage = totalPages;
                    loadData();
                    return;
                }

                selectedIds = [];
                renderTable(response.data || []);
                renderPagination(response);
                $('#totalCount').text(Number(response.total) || 0);
                updateSelectionUi();
            }).fail(function (xhr) {
                console.error(
                    'Erreur chargement catégories :',
                    xhr.status,
                    config.dataUrl,
                    xhr.responseText
                );

                selectedIds = [];
                renderTable([]);
                renderPagination({
                    totalPages: 0,
                    currentPage: 1
                });
                $('#totalCount').text('0');
                updateSelectionUi();
                showError('Impossible de charger les catégories.');
            });
        }, 250);
    };

    function renderTable(data) {
        var rows = [];

        if (!Array.isArray(data) || data.length === 0) {
            $('#tableBody').html(
                '<tr>' +
                    '<td colspan="5">' +
                        '<div class="empty-state">' +
                            '<div class="empty-icon"><i class="fas fa-tags"></i></div>' +
                            '<div class="empty-title">Aucune catégorie trouvée</div>' +
                            '<div class="empty-desc">Ajoutez une catégorie ou modifiez votre recherche.</div>' +
                        '</div>' +
                    '</td>' +
                '</tr>'
            );
            return;
        }

        data.forEach(function (categorie) {
            var id = normalizeEntityId(categorie.id);
            var description = String(categorie.description || '').trim();
            var professionsCount = Number(categorie.professions_count) || 0;

            if (!id) {
                console.warn('Catégorie ignorée : slug invalide.', categorie);
                return;
            }

            rows.push(
                '<tr>' +
                    '<td>' +
                        '<label class="checkbox-premium">' +
                            '<input type="checkbox" class="row-checkbox" value="' +
                                escapeHtml(id) + '">' +
                            '<span class="checkmark"></span>' +
                        '</label>' +
                    '</td>' +
                    '<td><strong style="font-weight:600;color:#1F2937;">' +
                        escapeHtml(categorie.nom) +
                    '</strong></td>' +
                    '<td>' +
                        (description
                            ? escapeHtml(description)
                            : '<span style="color:#9CA3AF;">—</span>') +
                    '</td>' +
                    '<td style="text-align:center;">' +
                        '<span class="badge-premium badge-premium-primary">' +
                            '<i class="fas fa-briefcase"></i> ' + professionsCount +
                        '</span>' +
                    '</td>' +
                    '<td>' +
                        '<div class="actions-group" style="justify-content:center;">' +
                            '<button type="button" class="action-btn action-btn-edit" ' +
                                'data-category-id="' + escapeHtml(id) + '" aria-label="Modifier">' +
                                '<i class="fas fa-pen"></i>' +
                                '<span class="tooltip-action">Modifier</span>' +
                            '</button>' +
                            '<button type="button" class="action-btn action-btn-delete" ' +
                                'data-category-id="' + escapeHtml(id) + '" aria-label="Supprimer">' +
                                '<i class="fas fa-trash-alt"></i>' +
                                '<span class="tooltip-action">Supprimer</span>' +
                            '</button>' +
                        '</div>' +
                    '</td>' +
                '</tr>'
            );
        });

        $('#tableBody').html(rows.join(''));
    }

    function paginationButton(label, page, disabled, active, extraClass) {
        var classes = 'page-btn';

        if (disabled) {
            classes += ' disabled';
        }
        if (active) {
            classes += ' active';
        }
        if (extraClass) {
            classes += ' ' + extraClass;
        }

        return (
            '<button type="button" class="' + classes + '" ' +
                (disabled ? 'disabled' : 'onclick="goToPage(' + page + ')"') +
            '>' + label + '</button>'
        );
    }

    function paginationDots() {
        return '<span class="page-btn disabled"><span class="dots">…</span></span>';
    }

    function renderPagination(response) {
        var totalPages = Number(response.totalPages) || 0;
        var responsePage = Number(response.currentPage) || currentPage;
        var html = [];
        var pages = [];
        var page;
        var lastAdded = 0;

        currentPage = responsePage;

        if (totalPages <= 1) {
            $('#pagination').empty();
            return;
        }

        html.push(
            paginationButton(
                '<span class="arrow"><i class="fas fa-chevron-left"></i></span>',
                currentPage - 1,
                currentPage <= 1,
                false
            )
        );

        pages.push(1);

        for (
            page = Math.max(2, currentPage - 2);
            page <= Math.min(totalPages - 1, currentPage + 2);
            page += 1
        ) {
            pages.push(page);
        }

        if (totalPages > 1) {
            pages.push(totalPages);
        }

        pages = pages.filter(function (value, index, values) {
            return values.indexOf(value) === index;
        }).sort(function (a, b) {
            return a - b;
        });

        pages.forEach(function (pageNumber) {
            if (lastAdded && pageNumber - lastAdded > 1) {
                html.push(paginationDots());
            }

            html.push(
                paginationButton(
                    String(pageNumber),
                    pageNumber,
                    false,
                    pageNumber === currentPage
                )
            );
            lastAdded = pageNumber;
        });

        html.push(
            paginationButton(
                '<span class="arrow"><i class="fas fa-chevron-right"></i></span>',
                currentPage + 1,
                currentPage >= totalPages,
                false
            )
        );

        $('#pagination').html(html.join(''));
    }

    window.goToPage = function (page) {
        var targetPage = Number(page);

        if (!Number.isFinite(targetPage) || targetPage < 1 || targetPage === currentPage) {
            return;
        }

        currentPage = targetPage;
        loadData();
    };

    window.toggleSelection = function (id, checked) {
        var entityId = normalizeEntityId(id);
        var index;

        if (!entityId) {
            return;
        }

        index = selectedIds.indexOf(entityId);

        if (checked && index === -1) {
            selectedIds.push(entityId);
        } else if (!checked && index !== -1) {
            selectedIds.splice(index, 1);
        }

        updateSelectionUi();
    };

    window.toggleSelectAll = function () {
        var checked = $('#selectAll').prop('checked');

        selectedIds = [];

        $('.row-checkbox').each(function () {
            $(this).prop('checked', checked);

            if (checked) {
                var entityId = normalizeEntityId(this.value);

                if (entityId) {
                    selectedIds.push(entityId);
                }
            }
        });

        updateSelectionUi();
    };

    function updateSelectionUi() {
        var totalRows = $('.row-checkbox').length;
        var selectedCount = selectedIds.length;
        var selectAll = document.getElementById('selectAll');

        $('#selectedCount').text(selectedCount);
        $('#toolbar').toggleClass('active', selectedCount > 0);

        if (selectAll) {
            selectAll.checked = totalRows > 0 && selectedCount === totalRows;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < totalRows;
        }
    }

    window.editCategorie = function (id) {
        id = normalizeEntityId(id);

        if (!id) {
            showError('Cette catégorie est invalide.');
            return false;
        }

        /* Nettoie obligatoirement l'état laissé par la sauvegarde précédente. */
        resetSaveButton();

        if (showRequest && showRequest.readyState !== 4) {
            showRequest.abort();
        }

        showRequest = $.ajax({
            url: entityUrl(config.showUrlTemplate, id),
            method: 'GET',
            dataType: 'json',
            cache: false
        }).done(function (categorie) {
            $('#categorieId').val(categorie.id);
            $('#categorieNom').val(categorie.nom || '').removeClass('is-invalid');
            $('#categorieDescription').val(categorie.description || '');
            $('#categorieModalLabel').html(
                '<i class="fas fa-edit"></i> Modifier la Catégorie'
            );
            resetSaveButton();
            openModal();
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }

            console.error('Erreur lecture catégorie :', xhr.status, xhr.responseText);
            showError(responseMessage(xhr, 'Impossible de charger cette catégorie.'));
        }).always(function () {
            showRequest = null;
        });
    };

    window.saveCategorie = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (saveRequest && saveRequest.readyState !== 4) {
            return false;
        }

        var id = String($('#categorieId').val() || '').trim();
        var nom = String($('#categorieNom').val() || '').trim();
        var description = String($('#categorieDescription').val() || '').trim();
        var button = $('#btnSaveCategorie');
        var isEdit = id !== '';

        if (nom === '') {
            $('#categorieNom').addClass('is-invalid').trigger('focus');
            resetSaveButton();
            return false;
        }

        $('#categorieNom').removeClass('is-invalid');
        $('#categorieForm')
            .attr('data-category-submitting', 'true')
            .addClass('is-submitting');
        button
            .prop('disabled', true)
            .attr('aria-busy', 'true')
            .html('<i class="fas fa-spinner fa-spin me-1"></i>Enregistrement...');

        saveRequest = $.ajax({
            url: isEdit
                ? entityUrl(config.editUrlTemplate, id)
                : config.createUrl,
            method: isEdit ? 'PUT' : 'POST',
            dataType: 'json',
            contentType: 'application/json; charset=UTF-8',
            processData: false,
            data: JSON.stringify({
                nom: nom,
                description: description || null
            })
        }).done(function (response) {
            closeModal();
            showSuccess(response.message || 'Catégorie enregistrée avec succès.');
            loadData(!isEdit);
        }).fail(function (xhr) {
            console.error('Erreur enregistrement catégorie :', xhr.status, xhr.responseText);
            showError(responseMessage(xhr, 'Impossible d’enregistrer la catégorie.'));
        }).always(function () {
            saveRequest = null;
            resetSaveButton();
        });

        return false;
    };

    window.deleteCategorie = function (id) {
        id = normalizeEntityId(id);

        if (!id) {
            showError('Cette catégorie est invalide.');
            return;
        }

        function removeCategory() {
            return $.ajax({
                url: entityUrl(config.deleteUrlTemplate, id),
                method: 'DELETE',
                dataType: 'json'
            }).done(function (response) {
                showSuccess(response.message || 'Catégorie supprimée avec succès.');
                loadData();
            }).fail(function (xhr) {
                console.error('Erreur suppression catégorie :', xhr.status, xhr.responseText);
                showError(responseMessage(xhr, 'Impossible de supprimer cette catégorie.'));
            });
        }

        if (!window.Swal) {
            if (window.confirm('Supprimer définitivement cette catégorie ?')) {
                removeCategory();
            }
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: 'Supprimer cette catégorie ?',
            text: 'Cette action est définitive.',
            showCancelButton: true,
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#EF4444'
        }).then(function (result) {
            if (result.isConfirmed) {
                removeCategory();
            }
        });
    };

    window.deleteSelected = function () {
        if (selectedIds.length === 0) {
            return;
        }

        var ids = selectedIds.slice();

        function removeCategories() {
            return $.ajax({
                url: config.batchDeleteUrl,
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json; charset=UTF-8',
                processData: false,
                data: JSON.stringify({
                    ids: ids
                })
            }).done(function (response) {
                selectedIds = [];
                updateSelectionUi();
                showSuccess(response.message || 'Catégories supprimées avec succès.');
                loadData();
            }).fail(function (xhr) {
                console.error(
                    'Erreur suppression multiple :',
                    xhr.status,
                    xhr.responseText
                );
                showError(responseMessage(xhr, 'Impossible de supprimer la sélection.'));
            });
        }

        if (!window.Swal) {
            if (window.confirm('Supprimer les catégories sélectionnées ?')) {
                removeCategories();
            }
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: 'Supprimer la sélection ?',
            text: ids.length + ' catégorie(s) seront supprimée(s).',
            showCancelButton: true,
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#EF4444'
        }).then(function (result) {
            if (result.isConfirmed) {
                removeCategories();
            }
        });
    };

    $(init);
})(window.jQuery, window, document);
