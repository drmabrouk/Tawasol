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
        sseSource: null,            // EventSource object
        typingTimeout: null,        // Timeout for debouncing typing indicator
        isTyping: false,            // Current typing status
        unreadCounts: {},           // Track unread messages per conversation
        lastMessageId: 0,           // Tracking for incremental message fetching
        mediaRecorder: null,        // MediaRecorder instance for voice
        audioChunks: [],            // Chunks of audio data
        recordingTimer: null,       // Timer for voice recording
        outbox: JSON.parse(localStorage.getItem('tawasol_outbox') || '[]'),

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
                this.initSSE();
                this.startOutboxProcessor();
            }
        },

        renderLogin: function() {
            const i18n = tawasolVars.i18n;
            const savedIdentifier = localStorage.getItem('tawasol_last_id') || '';

            const html = `
                <div id="tawasol-auth-container">
                    <div class="tawasol-auth-box">
                        <h2>${i18n.welcome}</h2>
                        <div id="tawasol-auth-step-1">
                            <input type="text" id="tawasol-identifier" placeholder="${i18n.email} ${i18n.phone}" value="${savedIdentifier}">
                            <button id="tawasol-check-user">${i18n.continue}</button>
                        </div>
                        <div id="tawasol-auth-step-2" style="display:none;">
                            <p id="tawasol-welcome-back"></p>
                            <input type="password" id="tawasol-pin" placeholder="${i18n.pin}" maxlength="6">
                            <button id="tawasol-login-btn">${i18n.login}</button>
                            <button class="tawasol-back-btn" style="margin-top:10px; background:none; color:var(--tawasol-text); border:1px solid var(--tawasol-border);">Back</button>
                        </div>
                        <div id="tawasol-auth-register" style="display:none;">
                            <h3>${i18n.register}</h3>
                            <input type="email" id="tawasol-reg-email" placeholder="${i18n.email}" value="${savedIdentifier.includes('@') ? savedIdentifier : ''}">
                            <input type="text" id="tawasol-reg-phone" placeholder="${i18n.phone}" value="${!savedIdentifier.includes('@') ? savedIdentifier : ''}">
                            <input type="text" id="tawasol-reg-username" placeholder="${i18n.username}">
                            <div id="tawasol-username-suggestions"></div>
                            <input type="password" id="tawasol-reg-pin" placeholder="${i18n.pin}" maxlength="6">
                            <button id="tawasol-register-btn">${i18n.register}</button>
                            <button class="tawasol-back-btn" style="margin-top:10px; background:none; color:var(--tawasol-text); border:1px solid var(--tawasol-border);">Back</button>
                        </div>
                    </div>
                </div>
            `;
            $('.tawasol-main-container').html(html);
        },

        renderOverlay: function() {
            const i18n = tawasolVars.i18n;
            const isDedicated = $('#tawasol-login-page-trigger').length || $('#tawasol-chat-page-trigger').length;

            const icons = {
                chat: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>',
                calls: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56-.35-.12-.74-.03-1.01.24l-1.57 1.97c-2.83-1.35-5.48-3.9-6.89-6.83l1.95-1.66c.27-.28.35-.67.24-1.02-.37-1.11-.56-2.3-.56-3.53 0-.54-.45-.99-.99-.99H4.19c-.54 0-1 .45-1 .99 0 9.39 7.61 17 17 17 .54 0 .99-.45.99-.99v-3.44c0-.54-.45-.99-.99-.99z"/></svg>',
                status: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/></svg>',
                profile: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
                settings: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>',
                theme: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zm0 16.5c-4.14 0-7.5-3.36-7.5-7.5S7.86 4.5 12 4.5s7.5 3.36 7.5 7.5-3.36 7.5-7.5 7.5z"/></svg>',
                logout: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M10.09 15.59L11.5 17l5-5-5-5-1.41 1.41L12.67 11H3v2h9.67l-2.58 2.59zM19 3H5c-1.11 0-2 .9-2 2v4h2V5h14v14H5v-4H3v4c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>',
                close: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>',
                attach: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.31 2.69 6 6 6s6-2.69 6-6V6h-1.5z"/></svg>',
                voice: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>',
                archive: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.47 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM6.24 5h11.52l.83 1H5.41l.83-1zM5 19V8h14v11H5zm11-5.5l-4 4-4-4 1.41-1.41L11 13.67V10h2v3.67l1.59-1.59L16 13.5z"/></svg>',
                media: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>',
                menu: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>',
                check: '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
            };

            const launcherHtml = isDedicated ? '' : `
                <div id="tawasol-launcher" title="${i18n.welcome}">
                    <span class="tawasol-launcher-icon">${icons.chat}</span>
                </div>
            `;
            const html = `
                <div id="tawasol-chat-overlay" class="${isDedicated ? 'dedicated' : ''}">
                    <div class="tawasol-top-bar">
                        <button id="tawasol-mobile-menu" class="tawasol-mobile-only">${icons.menu}</button>
                        <div class="tawasol-brand">Tawasol</div>
                        <div class="tawasol-top-actions">
                            <button id="tawasol-theme-toggle" title="Toggle Theme">${icons.theme}</button>
                            ${isDedicated && tawasolVars.userId != 0 ? `<button id="tawasol-logout" title="Logout">${icons.logout}</button>` : ''}
                            ${isDedicated ? '' : `<button id="tawasol-close-chat" title="Close Chat">${icons.close}</button>`}
                        </div>
                    </div>
                    <div class="tawasol-main-container">
                        <nav class="tawasol-nav-bar">
                            <div class="tawasol-nav-top">
                                <div class="tawasol-nav-item active" data-tab="chats" title="Chats">${icons.chat}</div>
                                <div class="tawasol-nav-item" data-tab="calls" title="Calls">${icons.calls}</div>
                                <div class="tawasol-nav-item" data-tab="status" title="Status">${icons.status}</div>
                            </div>
                            <div class="tawasol-nav-bottom">
                                <div class="tawasol-nav-item" data-tab="profile" title="Profile">${icons.profile}</div>
                                <div class="tawasol-nav-item" data-tab="settings" title="Settings">${icons.settings}</div>
                            </div>
                        </nav>
                        <aside class="tawasol-sidebar">
                            <div class="tawasol-search-box">
                                <div style="display:flex; gap:10px; margin-bottom:10px;">
                                    <input type="text" id="tawasol-sidebar-search" placeholder="${i18n.search}" style="flex:1;">
                                    <button id="tawasol-new-group-btn" title="New Group" style="background:var(--tawasol-primary); color:white; border:none; border-radius:8px; padding:0 12px; cursor:pointer;">+</button>
                                </div>
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
                            <!-- Settings Panel (Hidden by default) -->
                            <div id="tawasol-settings-panel" style="display:none; flex:1; flex-direction:column;">
                                <div class="tawasol-chat-header">
                                    <div class="tawasol-current-chat-info">Settings</div>
                                </div>
                                <div class="tawasol-settings-content" style="flex:1; overflow-y:auto; padding:30px;">
                                    <!-- Sub-panels will be loaded here -->
                                </div>
                            </div>

                            <!-- Chat Window -->
                            <div id="tawasol-chat-main" style="display:flex; flex:1; flex-direction:column;">
                            <div class="tawasol-chat-header">
                                <div class="tawasol-current-chat-info">${i18n.selectConv}</div>
                                <div class="tawasol-header-actions">
                                    <button id="tawasol-archive-btn" style="display:none;" title="Archive Chat">${icons.archive}</button>
                                    <button id="tawasol-media-panel-btn" style="display:none;" title="View Media">${icons.media}</button>
                                    <button id="tawasol-view-profile" style="display:none;" title="View Profile">${icons.profile}</button>
                                </div>
                            </div>
                                <div style="flex:1; display:flex; overflow:hidden;">
                                    <div class="tawasol-messages-list">
                                        <div id="tawasol-load-more" style="text-align:center; padding:10px; cursor:pointer; color:var(--tawasol-primary); display:none;">Load Previous Messages</div>
                                        <!-- Messages will be loaded here -->
                                    </div>
                                    <div id="tawasol-media-panel" style="display:none; width:250px; background:var(--tawasol-sidebar-bg); border-inline-start:1px solid var(--tawasol-border); overflow-y:auto; padding:15px;">
                                        <h4>Shared Media</h4>
                                        <div id="tawasol-media-items"></div>
                                    </div>
                            </div>
                            <div class="tawasol-message-input-area">
                                <form id="tawasol-send-message-form">
                                    <button type="button" id="tawasol-attach-btn" title="Attach File">${icons.attach}</button>
                                    <input type="file" id="tawasol-file-input" style="display:none;">
                                    <input type="text" id="tawasol-message-input" placeholder="${i18n.typeMessage}">
                                    <div class="tawasol-input-actions">
                                        <button type="button" id="tawasol-voice-btn" title="Record Voice">${icons.voice}</button>
                                        <button type="submit" id="tawasol-send-btn" title="${i18n.send}">
                                            <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M1.101 21.757L23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"></path></svg>
                                        </button>
                                    </div>
                                </form>
                                <div id="tawasol-voice-recording-overlay" style="display:none; align-items:center; gap:10px; background:var(--tawasol-bg); padding:0 15px;">
                                    <div class="tawasol-recording-indicator">🔴 <span id="tawasol-voice-timer">0:00</span></div>
                                    <div style="flex:1;">Recording...</div>
                                    <button type="button" id="tawasol-voice-cancel" style="background:none; border:none; color:#ea4335; cursor:pointer;">Cancel</button>
                                    <button type="button" id="tawasol-voice-stop" style="background:var(--tawasol-primary); color:white; border:none; border-radius:50%; width:40px; height:40px; cursor:pointer;">${icons.check}</button>
                                </div>
                            </div>
                            </div>
                        </main>
                    </div>
                </div>
            `;
            $('body').append(launcherHtml + html);
        },

        bindEvents: function() {
            const self = this;

            $(document).on('click', '#tawasol-load-more', () => {
                const firstId = $('.tawasol-message').first().data('id');
                if (firstId) self.loadPreviousMessages(self.currentConversation, firstId);
            });

            $(document).on('click', '#tawasol-mobile-menu', () => {
                $('.tawasol-sidebar').toggleClass('hidden');
            });

            $(document).on('click', '#tawasol-launcher', () => {
                self.openChat();
            });

            $(document).on('click', '#tawasol-logout', () => {
                const loginUrl = tawasolVars.homeUrl + '/tawasol-login';
                window.location.href = tawasolVars.homeUrl + '/wp-login.php?action=logout&redirect_to=' + encodeURIComponent(loginUrl);
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
                $('#tawasol-chat-main').show();
                $('#tawasol-settings-panel').hide();
                $('.tawasol-nav-item').removeClass('active');
                $('[data-tab="chats"]').addClass('active');
            });

            $(document).on('click', '.tawasol-nav-item', function() {
                const tab = $(this).data('tab');
                $('.tawasol-nav-item').removeClass('active');
                $(this).addClass('active');

                if (tab === 'settings' || tab === 'profile') {
                    $('#tawasol-chat-main').hide();
                    $('#tawasol-settings-panel').show();
                    self.loadSettings(tab);
                } else {
                    $('#tawasol-chat-main').show();
                    $('#tawasol-settings-panel').hide();
                }
            });

            $('#tawasol-send-message-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            $(document).on('click', '#tawasol-attach-btn', () => {
                $('#tawasol-file-input').click();
            });

            $(document).on('change', '#tawasol-file-input', function() {
                self.handleFileUpload(this.files[0]);
            });

            $(document).on('input', '#tawasol-message-input', function() {
                self.handleTyping();
            });

            $(document).on('click', '#tawasol-check-user', () => self.handleCheckUser());
            $(document).on('click', '#tawasol-login-btn', () => self.handleLogin());
            $(document).on('click', '#tawasol-register-btn', () => self.handleRegister());

            $(document).on('click', '.tawasol-back-btn', () => {
                $('#tawasol-auth-step-2, #tawasol-auth-register').hide();
                $('#tawasol-auth-step-1').fadeIn();
            });

            $(document).on('input', '#tawasol-reg-username', () => self.handleUsernameCheck());

            $(document).on('input', '#tawasol-pin, #tawasol-reg-pin', function() {
                const val = $(this).val();
                $(this).val(val.replace(/\D/g, '').substring(0, 6));
            });

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
                const name = $('.tawasol-current-chat-info').text();
                if (confirm('Do you want to block ' + name + '?')) {
                    self.blockCurrentChatUser();
                }
            });

            $(document).on('click', '#tawasol-archive-btn', () => {
                if (self.currentConversation) {
                    self.toggleArchive(self.currentConversation);
                }
            });

            $(document).on('click', '#tawasol-media-panel-btn', () => {
                $('#tawasol-media-panel').toggle();
                if ($('#tawasol-media-panel').is(':visible')) {
                    self.loadChatMedia();
                }
            });

            $(document).on('click', '#tawasol-voice-btn', () => {
                self.startVoiceRecording();
            });

            $(document).on('click', '#tawasol-voice-stop', () => {
                self.stopVoiceRecording(false);
            });

            $(document).on('click', '#tawasol-voice-cancel', () => {
                self.stopVoiceRecording(true);
            });

            $(document).on('click', '#tawasol-new-group-btn', () => {
                const name = prompt('Enter Group Name:');
                if (name) {
                    self.createNewGroup(name);
                }
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

            $(document).on('contextmenu', '.tawasol-conversation-item', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                self.showConversationOptions(id, e.pageX, e.pageY);
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

            $(document).on('click', '.tawasol-forward-msg', function() {
                const id = $(this).data('id');
                alert('Forwarding message ID: ' + id + ' (Feature coming soon)');
            });
        },

        showMessageOptions: function(id, x, y) {
            $('.tawasol-msg-options').remove();
            const isPinned = $(`.tawasol-message[data-id="${id}"]`).hasClass('pinned');
            const html = `
                <div class="tawasol-msg-options" style="position:fixed; top:${y}px; left:${x}px; background:var(--tawasol-bg); border:1px solid var(--tawasol-border); z-index:1000001; padding:5px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.15); min-width:150px;">
                    <button class="tawasol-forward-msg" data-id="${id}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; font-size:0.9rem;">Forward</button>
                    <button class="tawasol-pin-msg" data-id="${id}" data-pinned="${isPinned}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; font-size:0.9rem;">${isPinned ? 'Unpin' : 'Pin'}</button>
                    <button class="tawasol-edit-msg" data-id="${id}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; font-size:0.9rem;">Edit</button>
                    <button class="tawasol-delete-msg" data-id="${id}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; color:#ea4335; font-size:0.9rem;">Delete</button>
                </div>
            `;
            $('body').append(html);

            $(document).one('click', () => $('.tawasol-msg-options').remove());
        },

        showSimpleMessageOptions: function(id, x, y) {
            $('.tawasol-msg-options').remove();
            const isPinned = $(`.tawasol-message[data-id="${id}"]`).hasClass('pinned');
            const html = `
                <div class="tawasol-msg-options" style="position:fixed; top:${y}px; left:${x}px; background:var(--tawasol-bg); border:1px solid var(--tawasol-border); z-index:1000001; padding:5px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.15); min-width:150px;">
                    <button class="tawasol-forward-msg" data-id="${id}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; font-size:0.9rem;">Forward</button>
                    <button class="tawasol-pin-msg" data-id="${id}" data-pinned="${isPinned}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; font-size:0.9rem;">${isPinned ? 'Unpin' : 'Pin'}</button>
                </div>
            `;
            $('body').append(html);
            $(document).one('click', () => $('.tawasol-msg-options').remove());
        },

        showConversationOptions: function(id, x, y) {
            const self = this;
            $('.tawasol-msg-options').remove();
            const html = `
                <div class="tawasol-msg-options" style="position:fixed; top:${y}px; left:${x}px; background:var(--tawasol-bg); border:1px solid var(--tawasol-border); z-index:1000001; padding:5px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.15); min-width:150px;">
                    <button class="tawasol-archive-conv" data-id="${id}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; font-size:0.9rem;">Archive</button>
                    <button class="tawasol-delete-conv" data-id="${id}" style="display:block; width:100%; padding:8px 12px; border:none; background:none; cursor:pointer; text-align:left; color:#ea4335; font-size:0.9rem;">Delete Chat</button>
                </div>
            `;
            $('body').append(html);

            $('.tawasol-archive-conv').click(() => self.toggleArchive(id));
            $('.tawasol-delete-conv').click(() => {
                if (confirm('Are you sure you want to remove this chat from your list? Messages will be preserved in history.')) {
                    self.localDeleteConversation(id);
                }
            });

            $(document).one('click', () => $('.tawasol-msg-options').remove());
        },

        localDeleteConversation: function(id) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/conversations/${id}/local-delete`,
                method: 'DELETE',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: () => {
                    self.loadConversations();
                    if (self.currentConversation == id) {
                        $('#tawasol-chat-main').hide();
                    }
                }
            });
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
            if (!identifier) return;

            localStorage.setItem('tawasol_last_id', identifier);

            $.ajax({
                url: tawasolVars.restUrl + '/auth/check-user',
                method: 'POST',
                data: { identifier: identifier },
                success: (res) => {
                    if (res.exists) {
                        $('#tawasol-auth-step-1').hide();
                        $('#tawasol-auth-step-2').fadeIn();
                        $('#tawasol-welcome-back').text(i18n.welcomeBack.replace('%s', res.name));
                    } else {
                        $('#tawasol-auth-step-1').hide();
                        $('#tawasol-auth-register').fadeIn();
                    }
                }
            });
        },

        handleLogin: function() {
            const self = this;
            const identifier = $('#tawasol-identifier').val();
            const pin = $('#tawasol-pin').val();
            const i18n = tawasolVars.i18n;

            if (!/^\d{6}$/.test(pin)) {
                alert('PIN must be exactly 6 digits.');
                return;
            }

            $.ajax({
                url: tawasolVars.restUrl + '/auth/login',
                method: 'POST',
                data: { identifier: identifier, pin: pin },
                success: (res) => {
                    if (res.success) {
                        tawasolVars.userId = res.user.id;
                        self.initAfterAuth();
                    }
                },
                error: (xhr) => {
                    const res = xhr.responseJSON;
                    alert(res && res.message ? res.message : i18n.authFailed);
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

            if (!data.email.includes('@')) {
                alert('Please enter a valid email.');
                return;
            }

            if (!/^\d{6}$/.test(data.pin)) {
                alert('PIN must be exactly 6 digits.');
                return;
            }

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
                data: {
                    status: status,
                    is_typing: this.isTyping,
                    conversation_id: this.currentConversation
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                }
            });
        },

        handleTyping: function() {
            const self = this;
            if (!this.isTyping) {
                this.isTyping = true;
                this.updatePresence('online');
            }

            clearTimeout(this.typingTimeout);
            this.typingTimeout = setTimeout(() => {
                self.isTyping = false;
                self.updatePresence('online');
            }, 3000);
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
                        if (conv.is_archived == 1) return; // Hide archived
                        const title = conv.title || 'Chat #' + conv.id;
                        const unreadCount = self.unreadCounts[conv.id] || 0;
                        const avatarIcon = conv.is_self ?
                            '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/></svg>' :
                            '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>';
                        list.append(`
                            <div class="tawasol-conversation-item ${conv.is_self ? 'tawasol-archives' : ''}" data-id="${conv.id}">
                                <div class="tawasol-conv-avatar">${avatarIcon}</div>
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
            const self = this;
            this.currentConversation = id;
            this.lastMessageId = 0;
            this.unreadCounts[id] = 0;
            $('.tawasol-conversation-item').removeClass('active');
            $(`.tawasol-conversation-item[data-id="${id}"]`).addClass('active');
            $('.tawasol-messages-list').empty();

            if ($(window).width() <= 768) {
                $('.tawasol-sidebar').addClass('hidden');
            }

            const convEl = $(`.tawasol-conversation-item[data-id="${id}"]`);
            const title = convEl.find('.tawasol-conv-title').text();

            // Prominent Profile Integration in Chat Header
            const headerInfo = $('.tawasol-current-chat-info');
            headerInfo.html(`
                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="width:40px; height:40px; border-radius:50%; background:#eee; display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid var(--tawasol-border);">
                        👤
                    </div>
                    <div>
                        <div style="font-weight:600; font-size:1rem;">${this.escapeHTML(title)}</div>
                        <div class="tawasol-header-status" style="font-size:0.75rem; opacity:0.7; display:flex; align-items:center; gap:5px;">
                            <span class="tawasol-presence-indicator offline" style="width:8px; height:8px; border:none;"></span> Offline
                        </div>
                    </div>
                </div>
            `);

            $('#tawasol-view-profile').show();
            $('#tawasol-archive-btn').show();
            $('#tawasol-media-panel-btn').show();
            $('#tawasol-media-panel').hide();

            this.loadMessages(id);

            // Start polling
            clearInterval(this.pollingInterval);
            this.pollingInterval = setInterval(() => {
                this.loadMessages(id, true);
                this.checkActivePresence(id);
            }, 3000);
        },

        checkActivePresence: function(id) {
            const self = this;
            // Get other participant presence
            // In a real app, we'd have the user ID of the other person in the conversation
            // For one-on-one, we'll assume there's one other user.
            $.ajax({
                url: tawasolVars.restUrl + '/conversations',
                method: 'GET',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: function(conversations) {
                    const conv = conversations.find(c => c.id == id);
                    if (conv && conv.other_user_id) {
                        self.fetchPresence(conv.other_user_id);
                    }
                }
            });
        },

        fetchPresence: function(userId) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/presence/${userId}`,
                method: 'GET',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: function(res) {
                    const baseTitle = $(`.tawasol-conversation-item[data-id="${self.currentConversation}"] .tawasol-conv-title`).text();
                    const indicator = $(`.tawasol-conversation-item[data-id="${self.currentConversation}"] .tawasol-presence-indicator`);

                    let statusClass = 'offline';
                    let statusText = 'Offline';
                    if (res.status === 'online') {
                        statusClass = 'online';
                        statusText = 'Online';
                    } else if (res.last_seen) {
                        const lastSeen = new Date(res.last_seen);
                        const now = new Date();
                        if (now - lastSeen < 300000) { // 5 minutes
                            statusClass = 'recent';
                            statusText = 'Recently Offline';
                        }
                    }
                    indicator.attr('class', 'tawasol-presence-indicator ' + statusClass);

                    const headerStatus = $('.tawasol-header-status');
                    if (res.is_typing && res.conv_id == self.currentConversation) {
                        headerStatus.html('<span class="tawasol-presence-indicator typing" style="width:8px; height:8px; border:none;"></span> Typing...');
                    } else {
                        headerStatus.html('<span class="tawasol-presence-indicator ' + statusClass + '" style="width:8px; height:8px; border:none;"></span> ' + statusText);
                    }
                }
            });
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
                data: { after: this.lastMessageId, limit: 50 },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(messages) {
                    if (messages.length === 0) return;

                    const list = $('.tawasol-messages-list');
                    const shouldScroll = isPolling ? (list.scrollTop() + list.innerHeight() >= list[0].scrollHeight - 50) : true;

                    messages.forEach(msg => {
                        self.renderMessage(msg, list);
                    });

                    if (messages.length >= 50) {
                        $('#tawasol-load-more').show();
                    } else if (!isPolling) {
                        $('#tawasol-load-more').hide();
                    }

                    if (shouldScroll) {
                        list.scrollTop(list[0].scrollHeight);
                    }
                }
            });
        },

        loadPreviousMessages: function(id, beforeId) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/conversations/${id}/messages`,
                method: 'GET',
                data: { before: beforeId, limit: 50 },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(messages) {
                    if (messages.length === 0) {
                        $('#tawasol-load-more').hide();
                        return;
                    }

                    const list = $('.tawasol-messages-list');
                    const oldHeight = list[0].scrollHeight;

                    messages.reverse().forEach(msg => {
                        self.renderMessage(msg, list, true);
                    });

                    list.scrollTop(list[0].scrollHeight - oldHeight);

                    if (messages.length < 50) {
                        $('#tawasol-load-more').hide();
                    }
                }
            });
        },

        renderMessage: function(msg, list, prepend = false) {
            const self = this;
            if (!prepend && msg.id <= self.lastMessageId && self.lastMessageId !== 0) return;
            if ($(`.tawasol-message[data-id="${msg.id}"]`).length > 0) return;

            const isMe = msg.sender_id == tawasolVars.userId;
            let statusIcon = '✓';
            if (msg.status === 'delivered') statusIcon = '✓✓';
            if (msg.status === 'read') statusIcon = '<span class="read">✓✓</span>';
            if (msg.status === 'played') statusIcon = '<span class="played" style="color:#34b7f1;">✓✓</span>';

            let contentHtml = '';
            if (msg.content_type === 'image') {
                contentHtml = `<img src="${msg.content}" style="max-width:100%; border-radius:10px; cursor:pointer;" onclick="window.open('${msg.content}')">`;
            } else if (msg.content_type === 'file') {
                contentHtml = `<a href="${msg.content}" target="_blank" style="color:inherit; text-decoration:underline;">📄 Attached File</a>`;
            } else if (msg.content_type === 'voice') {
                contentHtml = `<audio src="${msg.content}" controls style="max-width:100%; height:35px;"></audio>`;
            } else {
                contentHtml = self.escapeHTML(msg.content);
            }

            list.append(`
                <div class="tawasol-message ${isMe ? 'me' : 'them'} ${msg.is_pinned == 1 ? 'pinned' : ''}" data-id="${msg.id}">
                    ${msg.is_pinned == 1 ? '<div class="tawasol-pin-indicator" style="font-size:0.7rem; color:var(--tawasol-primary);">📌 Pinned</div>' : ''}
                    <div class="tawasol-msg-content">${contentHtml}</div>
                    <div class="tawasol-msg-meta">
                        ${msg.is_edited == 1 ? '<span class="tawasol-edited-label" style="font-size:0.7rem; opacity:0.6;">(edited)</span>' : ''}
                        ${self.escapeHTML(msg.created_at)}
                        ${isMe ? `<span class="tawasol-msg-status">${statusIcon}</span>` : ''}
                    </div>
                </div>
            `);

            if (!isMe && msg.status !== 'read') {
                if (self.currentConversation == msg.conversation_id) {
                    self.markAsRead(msg.id);
                } else {
                    self.unreadCounts[msg.conversation_id] = (self.unreadCounts[msg.conversation_id] || 0) + 1;
                    self.showNotification(msg);
                }
            }

            self.lastMessageId = Math.max(self.lastMessageId, msg.id);

            // Played status listener for voice messages
            if (msg.content_type === 'voice' && isMe && msg.status !== 'played') {
                const audio = list.find(`.tawasol-message[data-id="${msg.id}"] audio`)[0];
                if (audio) {
                    audio.onplay = () => {
                        // In real WhatsApp, it's marked as played when the OTHER person plays it.
                        // But for our demo, if it's sent to me, I mark it played when I play it.
                    };
                }
            }

            if (msg.content_type === 'voice' && !isMe && msg.status !== 'played') {
                const audio = list.find(`.tawasol-message[data-id="${msg.id}"] audio`)[0];
                if (audio) {
                    audio.onplay = () => self.markAsPlayed(msg.id);
                }
            }
        },

        markAsPlayed: function(messageId) {
            $.ajax({
                url: tawasolVars.restUrl + `/messages/${messageId}/played`,
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                }
            });
        },

        handleFileUpload: function(file) {
            const self = this;
            if (!file) return;

            const tempId = 'temp-upload-' + Date.now();
            const list = $('.tawasol-messages-list');
            const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            // Show optimistic upload state
            list.append(`
                <div class="tawasol-message me tawasol-msg-sending" data-temp-id="${tempId}">
                    <div class="tawasol-msg-content">Uploading ${file.name}...</div>
                    <div class="tawasol-msg-meta">
                        ${timestamp} <span class="tawasol-msg-status">...</span>
                    </div>
                </div>
            `);
            list.scrollTop(list[0].scrollHeight);

            const formData = new FormData();
            formData.append('file', file);

            $.ajax({
                url: tawasolVars.restUrl + '/messages/upload',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: (res) => {
                    $(`[data-temp-id="${tempId}"]`).remove();
                    const type = file.type.startsWith('image/') ? 'image' : 'file';
                    self.sendMediaMessage(res.url, type);
                },
                error: () => {
                    const tempMsg = $(`[data-temp-id="${tempId}"]`);
                    tempMsg.removeClass('tawasol-msg-sending').addClass('tawasol-msg-failed');
                    tempMsg.find('.tawasol-msg-status').text('❌');
                }
            });
        },

        sendMediaMessage: function(url, type) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/messages',
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                data: {
                    conversation_id: this.currentConversation,
                    content: url,
                    content_type: type
                },
                success: () => {
                    self.loadMessages(self.currentConversation);
                }
            });
        },

        sendMessage: function(retryCount = 0, manualContent = null, manualConvId = null, manualType = 'text') {
            const self = this;
            const input = $('#tawasol-message-input');
            const content = manualContent || input.val();
            const convId = manualConvId || this.currentConversation;
            const type = manualType;

            if (!content || !convId) return;

            // Optimistic UI: Append message immediately if it's a new message
            const tempId = 'temp-' + Date.now();
            if (!manualContent || (retryCount === 0 && !manualContent)) {
                const list = $('.tawasol-messages-list');
                const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                let contentHtml = self.escapeHTML(content);
                if (type === 'image') contentHtml = `<img src="${content}" style="max-width:100%;">`;
                if (type === 'voice') contentHtml = `<audio src="${content}" controls></audio>`;
                if (type === 'file') contentHtml = `📄 File`;

                list.append(`
                    <div class="tawasol-message me tawasol-msg-sending" data-temp-id="${tempId}">
                        <div class="tawasol-msg-content">${contentHtml}</div>
                        <div class="tawasol-msg-meta">
                            ${timestamp} <span class="tawasol-msg-status">...</span>
                        </div>
                    </div>
                `);
                list.scrollTop(list[0].scrollHeight);
                if (!manualContent) input.val('');
            }

            $.ajax({
                url: tawasolVars.restUrl + '/messages',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                data: {
                    conversation_id: convId,
                    content: content,
                    content_type: type
                },
                success: function(res) {
                    const tempMsg = $(`[data-temp-id="${tempId}"]`);
                    if (tempMsg.length) {
                        tempMsg.removeClass('tawasol-msg-sending').attr('data-id', res.message_id);
                        tempMsg.find('.tawasol-msg-status').text('✓');
                    }
                    self.lastMessageId = Math.max(self.lastMessageId, res.message_id);

                    // Remove from outbox if it was there
                    self.outbox = self.outbox.filter(m => m.content !== content || m.convId !== convId);
                    self.saveOutbox();
                },
                error: function() {
                    if (retryCount < 5) {
                        const delay = Math.pow(2, retryCount) * 1000;
                        setTimeout(() => self.sendMessage(retryCount + 1, content, convId, type), delay);
                    } else {
                        const tempMsg = $(`[data-temp-id="${tempId}"]`);
                        if (tempMsg.length) {
                            tempMsg.removeClass('tawasol-msg-sending').addClass('tawasol-msg-failed');
                            tempMsg.find('.tawasol-msg-status').text('❌');
                        }

                        // Add to outbox for persistent retry (only for text/media URLs, not blobs)
                        if (typeof content === 'string' && !self.outbox.find(m => m.content === content && m.convId === convId)) {
                            self.outbox.push({ content, convId, type, timestamp: Date.now() });
                            self.saveOutbox();
                        }
                    }
                }
            });
        },

        saveOutbox: function() {
            localStorage.setItem('tawasol_outbox', JSON.stringify(this.outbox));
        },

        startOutboxProcessor: function() {
            const self = this;
            setInterval(() => {
                if (navigator.onLine && self.outbox.length > 0) {
                    const msg = self.outbox[0];
                    self.sendMessage(0, msg.content, msg.convId, msg.type || 'text');
                }
            }, 10000);
        },

        initSSE: function() {
            const self = this;
            if (typeof(EventSource) === "undefined") return;

            if (this.sseSource) this.sseSource.close();

            const url = new URL(tawasolVars.restUrl + '/realtime/stream');
            url.searchParams.append('_wpnonce', tawasolVars.nonce);
            url.searchParams.append('last_id', this.lastMessageId);

            this.sseSource = new EventSource(url.toString());

            this.sseSource.addEventListener('message', function(e) {
                const msg = JSON.parse(e.data);
                self.handleIncomingSSEMessage(msg);
            });

            this.sseSource.addEventListener('status_update', function(e) {
                const update = JSON.parse(e.data);
                self.handleStatusUpdate(update);
            });

            this.sseSource.onerror = function() {
                self.sseSource.close();
                setTimeout(() => self.initSSE(), 5000);
            };
        },

        handleIncomingSSEMessage: function(msg) {
            const list = $('.tawasol-messages-list');
            const isCurrentConv = this.currentConversation == msg.conversation_id;
            const isMe = msg.sender_id == tawasolVars.userId;

            if (isCurrentConv) {
                this.renderMessage(msg, list);
                list.scrollTop(list[0].scrollHeight);
                if (!isMe) this.markAsRead(msg.id); // Immediate read if open
            } else {
                this.renderMessage(msg, $('<div>')); // Just to process unread/lastId
                this.updateUnreadBadges();
            }

            if (!isMe) {
                this.playNotificationSound();
            }
        },

        handleStatusUpdate: function(update) {
            const msgEl = $(`.tawasol-message[data-id="${update.id}"]`);
            if (msgEl.length > 0) {
                let statusIcon = '✓';
                if (update.status === 'delivered') statusIcon = '✓✓';
                if (update.status === 'read') statusIcon = '<span class="read">✓✓</span>';
                if (update.status === 'played') statusIcon = '<span class="played" style="color:#34b7f1;">✓✓</span>';
                msgEl.find('.tawasol-msg-status').html(statusIcon);
            }
        },

        playNotificationSound: function() {
            // High-speed notification sound
            const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
            audio.play().catch(e => {}); // Silent fail if blocked
        },

        updateUnreadBadges: function() {
            const self = this;
            $('.tawasol-conversation-item').each(function() {
                const id = $(this).data('id');
                const count = self.unreadCounts[id] || 0;
                let badge = $(this).find('.tawasol-unread-badge');
                if (count > 0) {
                    if (badge.length === 0) {
                        $(this).find('.tawasol-conv-info').after(`<div class="tawasol-unread-badge">${count}</div>`);
                    } else {
                        badge.text(count);
                    }
                } else {
                    badge.remove();
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

        loadSettings: function(tab) {
            const self = this;
            const container = $('.tawasol-settings-content');
            container.html('<p>Loading...</p>');

            $.ajax({
                url: tawasolVars.restUrl + '/profile',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(profile) {
                    if (tab === 'profile') {
                        self.renderProfileSettings(profile);
                    } else {
                        self.renderGeneralSettings(profile);
                    }
                }
            });
        },

        renderProfileSettings: function(profile) {
            const self = this;
            const container = $('.tawasol-settings-content');
            const profileIcon = '<svg viewBox="0 0 24 24" width="48" height="48" fill="#8696a0"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
            const html = `
                <div class="tawasol-settings-group">
                    <h3>Profile</h3>
                    <div class="tawasol-profile-header" style="text-align:center; margin-bottom:20px;">
                        <div class="tawasol-profile-photo-edit" style="width:100px; height:100px; border-radius:50%; background:var(--tawasol-border); margin:0 auto 10px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            ${profile.photo ? `<img src="${profile.photo}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">` : profileIcon}
                        </div>
                        <button id="tawasol-change-photo" class="button">Change Photo</button>
                    </div>
                    <div class="tawasol-input-field">
                        <label>Display Name</label>
                        <input type="text" id="tawasol-set-name" value="${this.escapeHTML(profile.display_name)}">
                    </div>
                    <div class="tawasol-input-field">
                        <label>Status Message</label>
                        <input type="text" id="tawasol-set-status" value="${this.escapeHTML(profile.status_msg || '')}">
                    </div>
                    <div class="tawasol-input-field">
                        <label>Bio</label>
                        <textarea id="tawasol-set-bio">${this.escapeHTML(profile.bio || '')}</textarea>
                    </div>
                    <button id="tawasol-save-profile" class="button button-primary">Save Changes</button>
                </div>
            `;
            container.html(html);

            $('#tawasol-save-profile').click(() => {
                self.saveProfile({
                    display_name: $('#tawasol-set-name').val(),
                    status_msg: $('#tawasol-set-status').val(),
                    bio: $('#tawasol-set-bio').val()
                });
            });
        },

        loadBlockedUsers: function() {
            const self = this;
            const container = $('#tawasol-settings-sub-content');
            container.html('<p>Loading blocked users...</p>');

            $.ajax({
                url: tawasolVars.restUrl + '/blocks',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(users) {
                    let html = '<h4>Blocked Users</h4><ul style="list-style:none; padding:0;">';
                    if (users.length === 0) {
                        html += '<li>No blocked users.</li>';
                    } else {
                        users.forEach(user => {
                            html += `
                                <li style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid var(--tawasol-border);">
                                    <span>${self.escapeHTML(user.display_name)} (@${user.username})</span>
                                    <button class="tawasol-unblock-btn button" data-id="${user.id}">Unblock</button>
                                </li>
                            `;
                        });
                    }
                    html += '</ul><button id="tawasol-back-to-privacy" class="button" style="margin-top:20px;">Back</button>';
                    container.html(html);

                    $('#tawasol-back-to-privacy').click(() => self.loadSettings('settings'));
                    $('.tawasol-unblock-btn').click(function() {
                        const id = $(this).data('id');
                        self.unblockUser(id);
                    });
                }
            });
        },

        unblockUser: function(id) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/blocks/${id}`,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: () => self.loadBlockedUsers()
            });
        },

        createNewGroup: function(name) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/conversations',
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                data: {
                    type: 'group',
                    title: name,
                    participants: [] // In real app, we'd show a multi-select
                },
                success: (res) => {
                    self.loadConversations();
                    self.selectConversation(res.conversation_id);
                }
            });
        },

        loadChatMedia: function() {
            const self = this;
            const container = $('#tawasol-media-items');
            container.empty();

            const media = { images: [], files: [] };
            $('.tawasol-message').each(function() {
                const img = $(this).find('img');
                const link = $(this).find('a');
                if (img.length > 0) {
                    media.images.push(img.attr('src'));
                } else if (link.length > 0) {
                    media.files.push(link[0].outerHTML);
                }
            });

            if (media.images.length > 0) {
                container.append('<h5 style="margin-top:15px;">Images</h5><div class="tawasol-media-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;"></div>');
                media.images.forEach(src => {
                    container.find('.tawasol-media-grid').append(`<img src="${src}" style="width:100%; height:80px; object-fit:cover; border-radius:5px; cursor:pointer;" onclick="window.open('${src}')">`);
                });
            }

            if (media.files.length > 0) {
                container.append('<h5 style="margin-top:15px;">Files</h5>');
                media.files.forEach(html => {
                    container.append(`<div style="padding:8px; background:var(--tawasol-bg); border:1px solid var(--tawasol-border); border-radius:5px; margin-bottom:10px; font-size:0.75rem; overflow:hidden; text-overflow:ellipsis;">${html}</div>`);
                });
            }

            if (container.is(':empty')) {
                container.append('<p style="font-size:0.8rem; opacity:0.6;">No media shared in this chat.</p>');
            }
        },

        toggleArchive: function(id) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/conversations/${id}/archive`,
                method: 'POST',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                data: { archive: true },
                success: () => {
                    self.loadConversations();
                    $('#tawasol-chat-main').hide();
                    alert('Conversation archived.');
                }
            });
        },

        blockCurrentChatUser: function() {
            const self = this;
            // Simplified: we need the other user's ID.
            // In a real app, this would be stored in currentConversation details.
            // For now, we'll try to find it from the participants (this would usually be via a GET /conversations/:id)
            $.ajax({
                url: tawasolVars.restUrl + `/conversations`,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: function(conversations) {
                    const conv = conversations.find(c => c.id == self.currentConversation);
                    // For demo, we'll just assume there's another user.
                    // In real implementation, the API would return participants.
                    alert('Blocking feature triggered for conversation ' + self.currentConversation);
                }
            });
        },

        renderGeneralSettings: function(profile) {
            const self = this;
            const container = $('.tawasol-settings-content');
            const html = `
                <div class="tawasol-settings-tabs">
                    <button class="tawasol-tab-btn active" data-subtab="privacy">Privacy</button>
                    <button class="tawasol-tab-btn" data-subtab="security">Security</button>
                    <button class="tawasol-tab-btn" data-subtab="sessions">Sessions</button>
                    <button class="tawasol-tab-btn" data-subtab="notifications">Notifications</button>
                </div>
                <div id="tawasol-settings-sub-content" style="margin-top:20px;">
                    ${this.renderPrivacySettings(profile)}
                </div>
            `;
            container.html(html);

            $(document).on('click', '.tawasol-tab-btn', function() {
                $('.tawasol-tab-btn').removeClass('active');
                $(this).addClass('active');
                const subtab = $(this).data('subtab');
                let subHtml = '';
                if (subtab === 'privacy') subHtml = self.renderPrivacySettings(profile);
                if (subtab === 'security') subHtml = self.renderSecuritySettings(profile);
                if (subtab === 'sessions') {
                    subHtml = self.renderSessionsSettings(profile);
                    self.loadActiveSessions();
                }
                if (subtab === 'notifications') subHtml = self.renderNotificationSettings(profile);
                $('#tawasol-settings-sub-content').html(subHtml);
            });
        },

        renderPrivacySettings: function(profile) {
            const self = this;
            const p = profile.privacy;

            // Attach event listener after a short delay to ensure DOM is ready if called from renderGeneralSettings
            setTimeout(() => {
                $('#tawasol-view-blocked').off('click').on('click', () => self.loadBlockedUsers());

                $('.tawasol-privacy-toggle').off('change').on('change', function() {
                    const field = $(this).data('field');
                    const value = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
                    const data = {};
                    data[field] = value;
                    self.savePrivacy(data);
                });
            }, 10);

            return `
                <div class="tawasol-settings-group">
                    <h4>Privacy Controls</h4>
                    <div class="tawasol-input-field">
                        <label>Profile Photo</label>
                        <select class="tawasol-privacy-toggle" data-field="photo">
                            <option value="everyone" ${p.photo === 'everyone' ? 'selected' : ''}>Everyone</option>
                            <option value="contacts" ${p.photo === 'contacts' ? 'selected' : ''}>My Contacts</option>
                            <option value="nobody" ${p.photo === 'nobody' ? 'selected' : ''}>Nobody</option>
                        </select>
                    </div>
                    <div class="tawasol-input-field">
                        <label>Last Seen</label>
                        <select class="tawasol-privacy-toggle" data-field="last_seen">
                            <option value="everyone" ${p.last_seen === 'everyone' ? 'selected' : ''}>Everyone</option>
                            <option value="contacts" ${p.last_seen === 'contacts' ? 'selected' : ''}>My Contacts</option>
                            <option value="nobody" ${p.last_seen === 'nobody' ? 'selected' : ''}>Nobody</option>
                        </select>
                    </div>
                    <div class="tawasol-input-field">
                        <label><input type="checkbox" class="tawasol-privacy-toggle" data-field="read_receipts" ${p.read_receipts ? 'checked' : ''}> Enable Read Receipts</label>
                    </div>
                    <div style="margin-top:20px;">
                        <button id="tawasol-view-blocked" class="button">Manage Blocked Users</button>
                    </div>
                </div>
            `;
        },

        savePrivacy: function(data) {
            $.ajax({
                url: tawasolVars.restUrl + '/profile/privacy',
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: () => {
                    // Privacy updated silently or with a small toast
                }
            });
        },

        renderSecuritySettings: function(profile) {
            const self = this;
            setTimeout(() => {
                $('#tawasol-change-pin-btn').off('click').on('click', () => self.handleChangePinFlow());
            }, 10);

            return `
                <div class="tawasol-settings-group">
                    <h4>Security</h4>
                    <button id="tawasol-change-pin-btn" class="button">Change 6-digit PIN</button>
                    <div style="margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                        <button id="tawasol-delete-account-btn" class="button" style="color:red; border-color:red;">Delete Account</button>
                    </div>
                </div>
            `;
        },

        renderSessionsSettings: function(profile) {
            return `
                <div class="tawasol-settings-group">
                    <h4>Active Sessions</h4>
                    <p>Manage devices logged into your account.</p>
                    <div id="tawasol-active-sessions-list" style="margin-top:15px;">
                        Loading sessions...
                    </div>
                </div>
            `;
        },

        loadActiveSessions: function() {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/sessions',
                method: 'GET',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: function(sessions) {
                    const list = $('#tawasol-active-sessions-list');
                    list.empty();
                    if (sessions.length === 0) {
                        list.append('<p>No active sessions found.</p>');
                        return;
                    }
                    sessions.forEach(session => {
                        list.append(`
                            <div class="tawasol-session-item" style="padding:10px; border:1px solid var(--tawasol-border); border-radius:8px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight:bold;">${self.escapeHTML(session.user_agent.substring(0, 50))}...</div>
                                    <div style="font-size:0.8rem; opacity:0.7;">${session.ip_address} | Last active: ${session.last_activity}</div>
                                </div>
                                <button class="tawasol-terminate-session button" data-id="${session.id}" style="color:red;">Logout</button>
                            </div>
                        `);
                    });

                    $('.tawasol-terminate-session').click(function() {
                        const id = $(this).data('id');
                        if (confirm('Terminate this session?')) {
                            self.terminateSession(id);
                        }
                    });
                }
            });
        },

        terminateSession: function(id) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + `/sessions/${id}`,
                method: 'DELETE',
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: () => self.loadActiveSessions()
            });
        },

        handleChangePinFlow: function() {
            const self = this;
            const i18n = tawasolVars.i18n;

            if (confirm(i18n.otpSent)) {
                $.ajax({
                    url: tawasolVars.restUrl + '/auth/request-otp',
                    method: 'POST',
                    beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                    success: (res) => {
                        const otp = prompt(res.message + '\nEnter OTP:');
                        if (otp) {
                            self.verifyOtpAndChangePin(otp);
                        }
                    }
                });
            }
        },

        verifyOtpAndChangePin: function(otp) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/auth/verify-otp',
                method: 'POST',
                data: { otp: otp },
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: () => {
                    const oldPin = prompt('Enter old PIN:');
                    const newPin = prompt('Enter new 6-digit PIN:');
                    if (oldPin && newPin) {
                        self.updatePin(oldPin, newPin);
                    }
                },
                error: (xhr) => alert(xhr.responseJSON.message)
            });
        },

        updatePin: function(oldPin, newPin) {
            $.ajax({
                url: tawasolVars.restUrl + '/security/pin',
                method: 'POST',
                data: { old_pin: oldPin, new_pin: newPin },
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: () => alert('PIN changed successfully!'),
                error: (xhr) => alert(xhr.responseJSON.message)
            });
        },

        renderNotificationSettings: function(profile) {
            return `
                <div class="tawasol-settings-group">
                    <h4>Notifications</h4>
                    <div class="tawasol-input-field">
                        <label><input type="checkbox" checked> In-app Notifications</label>
                    </div>
                    <div class="tawasol-input-field">
                        <label><input type="checkbox" checked> Browser Push Notifications</label>
                    </div>
                    <div class="tawasol-input-field">
                        <label>Notification Tone</label>
                        <select>
                            <option>Default</option>
                            <option>Chime</option>
                            <option>Alert</option>
                        </select>
                    </div>
                </div>
            `;
        },

        saveProfile: function(data) {
            const self = this;
            $.ajax({
                url: tawasolVars.restUrl + '/profile',
                method: 'POST',
                data: data,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce);
                },
                success: (res) => {
                    // Update settings UI instantly
                    if (data.display_name) $('#tawasol-set-name').val(data.display_name);
                    if (data.status_msg) $('#tawasol-set-status').val(data.status_msg);
                    if (data.bio) $('#tawasol-set-bio').val(data.bio);

                    if (self.currentConversation) {
                        const convEl = $(`.tawasol-conversation-item[data-id="${self.currentConversation}"]`);
                        if (convEl.hasClass('tawasol-archives') && data.display_name) {
                             convEl.find('.tawasol-conv-title').text(data.display_name);
                        }
                    }
                    console.log('Profile updated successfully');
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
        },

        startVoiceRecording: function() {
            const self = this;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Your browser does not support voice recording.');
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                self.mediaRecorder = new MediaRecorder(stream);
                self.audioChunks = [];

                self.mediaRecorder.ondataavailable = event => {
                    self.audioChunks.push(event.data);
                };

                self.mediaRecorder.onstop = () => {
                    if (!self.cancelRecording) {
                        const audioBlob = new Blob(self.audioChunks, { type: 'audio/webm' });
                        self.handleVoiceUpload(audioBlob);
                    }
                    stream.getTracks().forEach(track => track.stop());
                };

                self.cancelRecording = false;
                self.mediaRecorder.start();

                $('#tawasol-send-message-form').hide();
                $('#tawasol-voice-recording-overlay').css('display', 'flex');

                let seconds = 0;
                clearInterval(self.recordingTimer);
                self.recordingTimer = setInterval(() => {
                    seconds++;
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    $('#tawasol-voice-timer').text(`${mins}:${secs < 10 ? '0' : ''}${secs}`);
                }, 1000);
            }).catch(err => {
                console.error('Recording error:', err);
                alert('Could not access microphone.');
            });
        },

        stopVoiceRecording: function(isCancel) {
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.cancelRecording = isCancel;
                this.mediaRecorder.stop();
            }
            clearInterval(this.recordingTimer);
            $('#tawasol-voice-timer').text('0:00');
            $('#tawasol-voice-recording-overlay').hide();
            $('#tawasol-send-message-form').css('display', 'flex');
        },

        handleVoiceUpload: function(blob) {
            const self = this;
            const formData = new FormData();
            const filename = `voice_${Date.now()}.webm`;
            formData.append('file', blob, filename);

            $.ajax({
                url: tawasolVars.restUrl + '/messages/upload',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: (xhr) => xhr.setRequestHeader('X-WP-Nonce', tawasolVars.nonce),
                success: (res) => {
                    self.sendMediaMessage(res.url, 'voice');
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
