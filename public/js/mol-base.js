(function () {
    'use strict';

    function molUrl(template, values = {}) {
        if (!template) return '';

        return Object.keys(values).reduce((url, key) => {
            return url.replaceAll(`__${key}__`, encodeURIComponent(values[key]));
        }, template);
    }

    window.MOLUrl = molUrl;

    window.MOLRequireChatConfig = function () {
        if (!window.MOL_CHAT || !window.MOL_CHAT.urls) {
            console.error('MOL_CHAT config missing. Add window.MOL_CHAT = {...} in Twig before this script.');
            return false;
        }

        return true;
    };

    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('friendsModal');

        if (!modal) {
            return;
        }

        modal.addEventListener('show.bs.modal', () => {
            loadPendingRequests();
            loadFriends();
        });
    });

    async function loadPendingRequests() {
        const container = document.getElementById('pendingRequestsContainer');

        if (!container || !window.MOL_FRIEND) {
            return;
        }

        const res = await fetch(window.MOL_FRIEND.pendingListUrl);

        if (!res.ok) {
            return;
        }

        const data = await res.json();

        if (!data.items.length) {
            container.innerHTML = `<div class="text-muted">Aucune demande reçue.</div>`;
            return;
        }

        container.innerHTML = buildFriendTable(data.items, true);
    }

    async function loadFriends() {
        const container = document.getElementById('friendsContainer');

        if (!container || !window.MOL_FRIEND) {
            return;
        }

        const res = await fetch(window.MOL_FRIEND.friendsListUrl);

        if (!res.ok) {
            return;
        }

        const data = await res.json();

        if (!data.items.length) {
            container.innerHTML = `<div class="text-muted">Aucun ami pour le moment.</div>`;
            return;
        }

        container.innerHTML = buildFriendTable(data.items, false);
    }

    function buildFriendTable(items, showActions) {
        return `
            <div class="mol-friend-list">
                ${items.map(u => `
                    <div class="mol-friend-row">
                        <div class="mol-avatar-wrapper">
                            <img src="${u.avatarUrl}" class="mol-avatar" alt="">
                            <span class="mol-status ${u.online ? 'online' : 'offline'}"></span>
                        </div>

                        <div class="mol-friend-info">
                            <div class="mol-friend-name">${u.name}</div>
                            <div class="mol-friend-meta">
                                ${u.online ? 'En ligne' : 'Hors ligne'}
                            </div>
                        </div>

                        <div class="mol-friend-actions">
                            ${showActions ? `
                                <button class="btn btn-sm btn-success"
                                        onclick="acceptFriend(${u.friendshipId})">
                                    Accepter
                                </button>

                                <button class="btn btn-sm btn-light"
                                        onclick="rejectFriend(${u.friendshipId})">
                                    Refuser
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-danger"
                                        onclick="blockFriend(${u.userId})">
                                    Bloquer
                                </button>
                            `}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    window.loadPendingRequests = loadPendingRequests;
    window.loadFriends = loadFriends;
    window.buildFriendTable = buildFriendTable;

    window.acceptFriend = async function (id) {
        if (!window.MOL_FRIEND) return;

        await fetch(molUrl(window.MOL_FRIEND.acceptUrlTpl, { ID: id }), {
            method: 'POST'
        });

        loadPendingRequests();
        loadFriends();
    };

    window.rejectFriend = async function (id) {
        if (!window.MOL_FRIEND) return;

        await fetch(molUrl(window.MOL_FRIEND.rejectUrlTpl, { ID: id }), {
            method: 'POST'
        });

        loadPendingRequests();
    };

    window.blockFriend = async function (userId) {
        if (!window.MOL_FRIEND) return;

        await fetch(molUrl(window.MOL_FRIEND.blockUrlTpl, { ID: userId }), {
            method: 'POST'
        });

        loadFriends();
    };

})();