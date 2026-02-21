(function($) {
    'use strict';

    /**
     * Tawasol Chat Application
     * Handles the full-screen UI, real-time messaging, and authentication flow.
     */
    const TawasolApp = {
        /**
         * Escape HTML to prevent XSS
         */
        escapeHTML: function(str) {
            return $('<div>').text(str).html();
        },

        currentConversation: null, // ID of the currently selected conversation
        pollingInterval: null,      // Interval for fetching new messages
        presenceInterval: null,     // Interval for updating user status
        unreadCounts: {},           // Track unread messages per conversation
        lastMessageId: 0,           // Tracking for incremental message fetching

        /**
         * Initialize the application components.
         */
        init: function() {
            this.renderOverlay();
            this.bindEvents();
            this.detectLanguage();
            if (tawasolVars.userId == 0) {
                this.renderLogin();
            } else {
                this.loadConversations();
            }
        },

        renderLogin: function() {
            const i18n = tawasolVars.i18n;
            const html = `
                <div id="tawasol-auth-container">
                    <div class="tawasol-auth-box">
                        <h2>${i18n.welcome}</h2>
                        <div id="tawasol-auth-step-1">
                            <input type="text" id="tawasol-identifier" placeholder="${i18n.email} ${i18n.phone}">
                            <button id="tawasol-check-user">${i18n.continue}</button>
                        </div>
                        <div id="tawasol-auth-step-2" style="display:none;">
                            <p id="tawasol-welcome-back"></p>
                            <input type="password" id="tawasol-pin" placeholder="${i18n.pin}" maxlength="6">
                            <button id="tawasol-login-btn">${i18n.login}</button>
                        </div>
                        <div id="tawasol-auth-register" style="display:none;">
                            <h3>${i18n.register}</h3>
                            <input type="email" id="tawasol-reg-email" placeholder="${i18n.email}">
                            <input type="text" id="tawasol-reg-phone" placeholder="${i18n.phone}">
                            <input type="text" id="tawasol-reg-username" placeholder="${i18n.username}">
                            <div id="tawasol-username-suggestions"></div>
                            <input type="password" id="tawasol-reg-pin" placeholder="${i18n.pin}" maxlength="6">
                            <button id="tawasol-register-btn">${i18n.register}</button>
                        </div>
                    </div>
                </div>
            `;
            $('.tawasol-main-container').html(html);
        },

        renderOverlay: function() {
            const i18n = tawasolVars.i18n;
            const isDedicated = $('#tawasol-login-page-trigger').length || $('#tawasol-chat-page-trigger').length;

            const launcherHtml = isDedicated ? '' : `
                <div id="tawasol-launcher" title="${i18n.welcome}">
                    <span class="tawasol-launcher-icon">💬</span>
                </div>
            `;
            const html = `
                <div id="tawasol-chat-overlay" class="${isDedicated ? 'dedicated' : ''}">
                    <div class="tawasol-top-bar">
                        <button id="tawasol-mobile-menu" class="tawasol-mobile-only">☰</button>
                        <div class="tawasol-brand">Tawasol</div>
                        <div class="tawasol-top-actions">
                            <button id="tawasol-theme-toggle">🌓</button>
                            ${isDedicated && tawasolVars.userId != 0 ? '<button id="tawasol-logout" title="Logout">🚪</button>' : ''}
                            ${isDedicated ? '' : '<button id="tawasol-close-chat">✖</button>'}
                        </div>
                    </div>
                    <div class="tawasol-main-container">
                        <aside class="tawasol-sidebar">
                            <div class="tawasol-search-box">
                                <input type="text" id="tawasol-sidebar-search" placeholder="${i18n.search}">
                                <div class="tawasol-search-type-toggle">
                                    <label><input type="radio" name="search_type" value="users" checked> Users</label>
                                    <label><input type="radio" name="search_type" value="messages"> Messages</label>
                                </div>
                            </div>
                            <div id="tawasol-global-search-results" style="display:none;">
                                <!-- Global user search results here -->
                            </div>
                            <div class="tawasol-conversations-list">
                                <!-- Conversations will be loaded here -->
                            </div>
                        </aside>
                        <main class="tawasol-chat-window">
                            <div class="tawasol-chat-header">
                                <div class="tawasol-current-chat-info">${i18n.selectConv}</div>
                                <div class="tawasol-header-actions">
                                    <button id="tawasol-view-profile" style="display:none;">👤</button>
                                </div>
                            </div>
                            <div class="tawasol-messages-list">
                                <!-- Messages will be loaded here -->
                            </div>
                            <div class="tawasol-message-input-area">
                                <form id="tawasol-send-message-form">
                                    <input type="text" id="tawasol-message-input" placeholder="${i18n.typeMessage}">
                                    <button type="submit">${i18n.send}</button>
                                </form>
                            </div>
                        </main>
                    </div>
                </div>
            `;
            $('body').append(launcherHtml + html);
        },

        bindEvents: function() {
            const self = this;

            $(document).on('click', '#tawasol-mobile-menu', () => {
                $('.tawasol-sidebar').toggleClass('hidden');
            });

            $(document).on('click', '#tawasol-launcher', () => {
                self.openChat();
            });

            $(document).on('click', '#tawasol-logout', () => {
                window.location.href = tawasolVars.homeUrl + '/wp-login.php?action=logout';
            });

            $(document).on('click', '#tawasol-close-chat', () => {
                if ($('#tawasol-login-page-trigger').length || $('#tawasol-chat-page-trigger').length) {
                    window.location.href = tawasolVars.homeUrl;
                } else {
                    $('#tawasol-chat-overlay').removeClass('active');
                    clearInterval(self.pollingInterval);
                }
            });

            $('#tawasol-theme-toggle').on('click', () => {
                const currentTheme = $('html').attr('data-theme');
                $('html').attr('data-theme', currentTheme === 'dark' ? 'light' : 'dark');
            });

            $(document).on('click', '.tawasol-conversation-item', function() {
                const id = $(this).data('id');
                self.selectConversation(id);
            });

            $('#tawasol-send-message-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            $(document).on('click', '#tawasol-check-user', () => self.handleCheckUser());
            $(document).on('click', '#tawasol-login-btn', () => self.handleLogin());
            $(document).on('click', '#tawasol-register-btn', () => self.handleRegister());

            $(document).on('input', '#tawasol-reg-username', () => self.handleUsernameCheck());

            $(document).on('input', '#tawasol-sidebar-search', function() {
                const term = $(this).val();
                const type = $('input[name="search_type"]:checked').val();

                if (term.length >= 2) {
                    if (type === 'users') {
                        self.searchGlobalUsers(term);
                    } else {
                        self.searchGlobalMessages(term);
                    }
                } else {
                    $('#tawasol-global-search-results').hide().empty();
                    $('.tawasol-conversations-list').show();
                }

                const lowerTerm = term.toLowerCase();
                $('.tawasol-conversation-item').each(function() {
                    const title = $(this).find('.tawasol-conv-title').text().toLowerCase();
                    $(this).toggle(title.indexOf(lowerTerm) > -1);
                });
            });

            $(document).on('click', '.tawasol-search-result-item', function() {
                if ($(this).hasClass('message-result')) {
                    self.selectConversation($(this).data('id'));
                } else {
                    const userId = $(this).data('id');
                    const name = $(this).data('name');
                    self.startNewChat(userId, name);
                }
                $('#tawasol-sidebar-search').val('');
                $('#tawasol-global-search-results').hide().empty();
                $('.tawasol-conversations-list').show();
            });

            $(document).on('click', '#tawasol-view-profile', () => {
                alert('User Profile: ' + $('.tawasol-current-chat-info').text());
            });

            $(document).on('contextmenu', '.tawasol-message', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const isMe = $(this).hasClass('me');
                if (isMe) {
                    self.showMessageOptions(id, e.pageX, e.pageY);
                } else {
                    self.showSimpleMessageOptions(id, e.pageX, e.pageY);
                }
            });

            $(document).on('click', '.tawasol-edit-msg', function() {
                const id = $(this).data('id');
                const oldContent = $(`.tawasol-message[data-id="${id}"] .tawasol-msg-content`).text();
                const newContent = prompt('Edit message:', oldContent);
                if (newContent && newContent !== oldContent) {
                    self.editMessage(id, newContent);
                }
            });

            $(document).on('click', '.tawasol-delete-msg', function() {
                const id = $(this).data('id');
                if (confirm('Delete this message for everyone?')) {
                    self.deleteMessage(id);
                }
            });

            $(document).on('click', '.tawasol-pin-msg', function() {
                const id = $(this).data('id');
                const isPinned = $(this).data('pinned') === true;
                self.pinMessage(id, !isPinned);
            });
        },

        showMessageOptions: function(id, x, y) {
            $('.tawasol-msg-options').remove();
            const isPinned = $(`.tawasol-message[data-id="${id}"]`).hasClass('pinned');
            const html = `
                <div class="tawasol-msg-options" style="position:fixed; top:${y}px; left:${x}px; background:var(--tawasol-bg); border:1px solid var(--tawasol-border); z-index:1000001; padding:5px; border-radius:4px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                    <button class="tawasol-pin-msg" data-id="${id}" data-pinned="${isPinned}" style="display:block; width:100%; padding:5px 10px; border:none; background:none; cursor:pointer; text-align:left;">${isPinned ? 'Unpin' : 'Pin'}</button>
                    <button class="tawasol-edit-msg" data-id="${id}" style="display:block; width:100%; padding:5px 10px; border:none; background:none; cursor:pointer; text-align:left;">Edit</button>
                    <button class="tawasol-delete-msg" data-id="${id}" style="display:block; width:100%; padding:5px 10px; border:none; background:none; cursor:pointer; text-align:left; color:red;">Delete</button>
                </div>
            `;
            $('body').append(html);

            $(document).one('click', () => $('.tawasol-msg-options').remove());
        },

        showSimpleMessageOptions: function(id, x, y) {
            $('.tawasol-msg-options').remove();
            const isPinned = $(`.tawasol-message[data-id="${id}"]`).hasClass('pinned');
            const html = `
                <div class="tawasol-msg-options" style="position:fixed; top:${y}px; left:${x}px; background:var(--tawasol-bg); border:1px solid var(--tawasol-border); z-index:1000001; padding:5px; border-radius:4px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                    <button class="tawasol-pin-msg" data-id="${id}" data-pinned="${isPinned}" style="display:block; width:100%; padding:5px 10px; border:none; background:none; cursor:pointer; text-align:left;">${isPinned ? 'Unpin' : 'Pin'}</button>
                </div>
            `;
            $('body').append(html);
            $(document).one('click', () => $('.tawasol-msg-options').remove());
        },

        searchGlobalMessages: function(term) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/messages/search',
                method: 'GET',
                data: { term: term },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(messages) {
                    const container = $('#tawasol-global-search-results');
                    container.empty();
                    if (messages.length > 0) {
                        $('.tawasol-conversations-list').hide();
                        container.show();
                        messages.forEach(msg => {
                            container.append(`
                                <div class="tawasol-search-result-item message-result" data-id="${msg.conversation_id}">
                                    <div class="tawasol-user-info">
                                        <div class="tawasol-msg-snippet">${self.escapeHTML(msg.content)}</div>
                                        <div class="tawasol-user-username">${msg.created_at}</div>
                                    </div>
                                </div>
                            `);
                        });
                    }
                }
            });
        },

        searchGlobalUsers: function(term) {
            $.ajax({
                url: tawasolVars.restUrl + '/users/search',
                method: 'GET',
                data: { term: term },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(users) {
                    const container = $('#tawasol-global-search-results');
                    container.empty();
                    if (users.length > 0) {
                        $('.tawasol-conversations-list').hide();
                        container.show();
                        users.forEach(user => {
                            container.append(`
                                <div class="tawasol-search-result-item" data-id="${user.id}" data-name="${user.display_name}">
                                    <div class="tawasol-user-avatar">👤</div>
                                    <div class="tawasol-user-info">
                                        <div class="tawasol-user-name">${user.display_name}</div>
                                        <div class="tawasol-user-username">@${user.username}</div>
                                    </div>
                                </div>
                            `);
                        });
                    }
                }
            });
        },

        startNewChat: function(userId, name) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/conversations',
                method: 'POST',
                data: {
                    type: 'one-on-one',
                    participants: [userId],
                    title: name
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(res) {
                    $('#tawasol-sidebar-search').val('');
                    $('#tawasol-global-search-results').hide().empty();
                    $('.tawasol-conversations-list').show();
                    self.loadConversations();
                    self.selectConversation(res.conversation_id);
                }
            });
        },

        handleCheckUser: function() {
            const identifier = $('#tawasol-identifier').val();
            const i18n = tawasolVars.i18n;
            $.ajax({
                url: tawasolVars.restUrl + '/auth/check-user',
                method: 'POST',
                data: { identifier: identifier },
                success: (res) => {
                    if (res.exists) {
                        $('#tawasol-auth-step-1').hide();
                        $('#tawasol-auth-step-2').show();
                        $('#tawasol-welcome-back').text(i18n.welcomeBack.replace('%s', res.name));
                    } else {
                        $('#tawasol-auth-step-1').hide();
                        $('#tawasol-auth-register').show();
                    }
                }
            });
        },

        handleLogin: function() {
            const self = this;
            const identifier = $('#tawasol-identifier').val();
            const pin = $('#tawasol-pin').val();
            $.ajax({
                url: tawasolVars.restUrl + '/auth/login',
                method: 'POST',
                data: { identifier: identifier, pin: pin },
                success: (res) => {
                    if (res.success) {
                        tawasolVars.userId = res.user.id;
                        self.initAfterAuth();
                    }
                }
            });
        },

        handleUsernameCheck: function() {
            const username = $('#tawasol-reg-username').val();
            const i18n = tawasolVars.i18n;
            if (username.length < 3) return;
            $.ajax({
                url: tawasolVars.restUrl + '/auth/check-username',
                method: 'GET',
                data: { username: username },
                success: (res) => {
                    const sugg = $('#tawasol-username-suggestions');
                    sugg.empty();
                    if (!res.available) {
                        sugg.append('<p>' + i18n.usernameTaken.replace('%s', res.suggestions.join(', ')) + '</p>');
                    }
                }
            });
        },

        handleRegister: function() {
            const self = this;
            const data = {
                email: $('#tawasol-reg-email').val(),
                phone: $('#tawasol-reg-phone').val(),
                username: $('#tawasol-reg-username').val(),
                pin: $('#tawasol-reg-pin').val()
            };
            $.ajax({
                url: tawasolVars.restUrl + '/auth/register',
                method: 'POST',
                data: data,
                success: (res) => {
                    if (res.success) {
                        tawasolVars.userId = res.user.id;
                        self.initAfterAuth();
                    }
                }
            });
        },

        initAfterAuth: function() {
            $('#tawasol-chat-overlay').remove();
            $('#tawasol-launcher').remove();
            this.init();
            this.openChat();
        },

        detectLanguage: function() {
            const isRTL = $('body').hasClass('rtl') || $('html').attr('dir') === 'rtl';
            if (isRTL) {
                $('#tawasol-chat-overlay').attr('dir', 'rtl');
            }
        },

        /**
         * Open the full-screen chat overlay and start background updates.
         */
        openChat: function() {
            $('#tawasol-chat-overlay').addClass('active');
            this.loadConversations();
            this.startPresenceUpdates();
            this.requestNotificationPermission();
        },

        requestNotificationPermission: function() {
            if ("Notification" in window) {
                Notification.requestPermission();
            }
        },

        startPresenceUpdates: function() {
            const self = this;
            this.updatePresence('online');
            clearInterval(this.presenceInterval);
            this.presenceInterval = setInterval(() => this.updatePresence('online'), 30000);
        },

        updatePresence: function(status) {
            $.ajax({
                url: tawasolVars.restUrl + '/presence',
                method: 'POST',
                data: { status: status },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                }
            });
        },

        /**
         * Fetch the list of conversations for the current user.
         */
        loadConversations: function() {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/conversations',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(conversations) {
                    const list = $('.tawasol-conversations-list');
                    list.empty();
                    conversations.forEach(conv => {
                        const title = conv.title || 'Chat #' + conv.id;
                        const unreadCount = self.unreadCounts[conv.id] || 0;
                        list.append(`
                            <div class="tawasol-conversation-item" data-id="${conv.id}">
                                <div class="tawasol-conv-avatar">👥</div>
                                <div class="tawasol-conv-info">
                                    <div class="tawasol-conv-title">${self.escapeHTML(title)}</div>
                                    <div class="tawasol-conv-last-msg">...</div>
                                </div>
                                ${unreadCount > 0 ? `<div class="tawasol-unread-badge">${unreadCount}</div>` : ''}
                                <div class="tawasol-presence-indicator" data-user-id="${conv.id}"></div>
                            </div>
                        `);
                    });
                }
            });
        },

        selectConversation: function(id) {
            this.currentConversation = id;
            this.lastMessageId = 0;
            this.unreadCounts[id] = 0;
            $('.tawasol-conversation-item').removeClass('active');
            $(`.tawasol-conversation-item[data-id="${id}"]`).addClass('active');
            $('.tawasol-messages-list').empty();

            if ($(window).width() <= 768) {
                $('.tawasol-sidebar').addClass('hidden');
            }

            const title = $(`.tawasol-conversation-item[data-id="${id}"] .tawasol-conv-title`).text();
            $('.tawasol-current-chat-info').text(title);
            $('#tawasol-view-profile').show();

            this.loadMessages(id);

            // Start polling
            clearInterval(this.pollingInterval);
            this.pollingInterval = setInterval(() => {
                this.loadMessages(id, true);
                this.checkActivePresence(id);
            }, 3000);
        },

        checkActivePresence: function(id) {
            // In a real app, we'd know the other user's ID.
            // For this demo, we'll skip complex participant mapping and just update the UI if possible.
        },

        /**
         * Load messages for a specific conversation.
         * Supports incremental polling via the 'after' parameter.
         */
        loadMessages: function(id, isPolling = false) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/conversations/${id}/messages`,
                method: 'GET',
                data: { after: this.lastMessageId },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(messages) {
                    if (messages.length === 0) return;

                    const list = $('.tawasol-messages-list');
                    const shouldScroll = isPolling ? (list.scrollTop() + list.innerHeight() >= list[0].scrollHeight - 50) : true;

                    messages.forEach(msg => {
                        if (msg.id <= self.lastMessageId) return;

                        const isMe = msg.sender_id == tawasolVars.userId;
                        let statusIcon = '✓';
                        if (msg.status === 'delivered') statusIcon = '✓✓';
                        if (msg.status === 'read') statusIcon = '<span class="read">✓✓</span>';

                        list.append(`
                            <div class="tawasol-message ${isMe ? 'me' : 'them'} ${msg.is_pinned == 1 ? 'pinned' : ''}" data-id="${msg.id}">
                                ${msg.is_pinned == 1 ? '<div class="tawasol-pin-indicator" style="font-size:0.7rem; color:var(--tawasol-primary);">📌 Pinned</div>' : ''}
                                <div class="tawasol-msg-content">${self.escapeHTML(msg.content)}</div>
                                <div class="tawasol-msg-meta">
                                    ${msg.is_edited == 1 ? '<span class="tawasol-edited-label" style="font-size:0.7rem; opacity:0.6;">(edited)</span>' : ''}
                                    ${self.escapeHTML(msg.created_at)}
                                    ${isMe ? `<span class="tawasol-msg-status">${statusIcon}</span>` : ''}
                                </div>
                            </div>
                        `);

                        if (!isMe && msg.status !== 'read') {
                            if (self.currentConversation == id) {
                                self.markAsRead(msg.id);
                            } else {
                                self.unreadCounts[id] = (self.unreadCounts[id] || 0) + 1;
                                self.showNotification(msg);
                            }
                        }

                        self.lastMessageId = Math.max(self.lastMessageId, msg.id);
                    });

                    if (shouldScroll) {
                        list.scrollTop(list[0].scrollHeight);
                    }
                }
            });
        },

        sendMessage: function() {
            const self = this;
            const input = $('#tawasol-message-input');
            const content = input.val();
            if (!content || !this.currentConversation) return;

            $.ajax({
                url: tawasolVars.restUrl + '/messages',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                data: {
                    conversation_id: this.currentConversation,
                    content: content,
                    content_type: 'text'
                },
                success: function() {
                    input.val('');
                    self.loadMessages(self.currentConversation);
                }
            });
        },

        showNotification: function(msg) {
            if ("Notification" in window && Notification.permission === "granted") {
                new Notification("New Tawasol Message", {
                    body: msg.content,
                    icon: tawasolVars.iconUrl // Assuming we add this to vars
                });
            }
        },

        markAsRead: function(messageId) {
            $.ajax({
                url: tawasolVars.restUrl + `/messages/${messageId}/read`,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                }
            });
        },

        editMessage: function(id, content) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/messages/${id}`,
                method: 'PATCH',
                data: { content: content },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: () => {
                    self.lastMessageId = 0;
                    self.selectConversation(self.currentConversation);
                }
            });
        },

        pinMessage: function(id, pin) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/messages/${id}/pin`,
                method: 'POST',
                data: { pin: pin },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: () => {
                    self.lastMessageId = 0;
                    self.selectConversation(self.currentConversation);
                }
            });
        },

        deleteMessage: function(id) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/messages/${id}`,
                method: 'DELETE',
                data: { everyone: 'true' },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: () => {
                    self.lastMessageId = 0;
                    self.selectConversation(self.currentConversation);
                }
            });
        }
    };

    $(document).ready(function() {
        TawasolApp.init();

        // Expose openChat to window for testing or triggers
        window.tawasolOpenChat = () => TawasolApp.openChat();

        // Auto-open on dedicated pages
        if ($('#tawasol-login-page-trigger').length || $('#tawasol-chat-page-trigger').length) {
            TawasolApp.openChat();
        }
    });

})(jQuery);
