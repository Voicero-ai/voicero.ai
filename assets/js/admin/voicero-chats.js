/**
 * Voicero AI - Chats Admin Interface
 * Handles chat conversations display and management
 */

(function ($) {
  "use strict";

  // Check if we have the necessary configuration
  if (typeof voiceroAdminConfig === "undefined") {
    console.error("Voicero Admin Config not found");
    return;
  }

  const VoiceroChats = {
    // Configuration
    config: voiceroAdminConfig,

    // State management
    state: {
      conversations: [],
      loading: true,
      loadingMore: false,
      error: null,
      pagination: null,
      expandedChats: new Set(),
      currentPage: 1,

      // Filter states
      websiteId: "",
      searchQuery: "",
      sortOption: "recent",
    },

    // Sort options
    sortOptions: [
      { label: "Most Recent", value: "recent" },
      { label: "Oldest", value: "oldest" },
      { label: "Longest", value: "longest" },
      { label: "Shortest", value: "shortest" },
    ],

    /**
     * Initialize the chats interface
     */
    init: function () {
      console.log("Initializing Voicero Chats");

      this.bindEvents();
      this.initializeFilters();
      this.fetchChats(1, false);
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Search input handler
      $(document).on("input", "#voicero-search-input", function () {
        const query = $(this).val();
        self.state.searchQuery = query;
        self.updateSearchStatus();
      });

      // Sort change handler
      $(document).on("change", "#voicero-sort-select", function () {
        self.state.sortOption = $(this).val();
        self.fetchChats(1, false);
      });

      // Search/load button
      $(document).on("click", "#voicero-search-btn", function () {
        self.fetchChats(1, false);
      });

      // Load more button
      $(document).on("click", "#voicero-load-more-btn", function () {
        self.handleLoadMore();
      });

      // Chat expansion toggle
      $(document).on("click", ".voicero-chat-toggle", function () {
        const chatId = $(this).data("chat-id");
        self.toggleChatExpansion(chatId);
      });
    },

    /**
     * Initialize filters
     */
    initializeFilters: function () {
      // Set website ID if available
      if (this.config.websiteId) {
        this.state.websiteId = this.config.websiteId;
      }

      // Populate sort select
      this.populateSortSelect();
    },

    /**
     * Populate sort select dropdown
     */
    populateSortSelect: function () {
      const $select = $("#voicero-sort-select");
      if ($select.length) {
        $select.empty();
        this.sortOptions.forEach((option) => {
          $select.append(
            `<option value="${option.value}">${option.label}</option>`
          );
        });
        $select.val(this.state.sortOption);
      }
    },

    /**
     * Update search status message
     */
    updateSearchStatus: function () {
      const $helpText = $("#voicero-search-help");
      const query = this.state.searchQuery;

      if (query.length > 0 && query.length < 3) {
        $helpText.text(`${3 - query.length} more characters needed`);
        $("#voicero-search-btn").prop("disabled", true).text("Search");
      } else if (query.length >= 3) {
        $helpText.text("Search in conversation content");
        $("#voicero-search-btn").prop("disabled", false).text("Search");
      } else {
        $helpText.text("Enter at least 3 characters to search...");
        $("#voicero-search-btn").prop("disabled", false).text("Load All");
      }
    },

    /**
     * Fetch chats from API
     */
    fetchChats: function (page = 1, append = false) {
      const self = this;

      if (page === 1) {
        this.state.loading = true;
        this.showLoading();
      } else {
        this.state.loadingMore = true;
        this.showLoadingMore();
      }

      this.state.error = null;

      // Build query parameters
      const params = new URLSearchParams({
        websiteId: this.state.websiteId || "",
        page: page.toString(),
        limit: "10",
        sort: this.state.sortOption,
      });

      if (this.state.searchQuery && this.state.searchQuery.length >= 3) {
        params.append("search", this.state.searchQuery);
      }

      // Make AJAX request
      $.ajax({
        url: `${
          this.config.ajaxUrl
        }?action=voicero_get_chats&${params.toString()}`,
        method: "GET",
        data: {
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success) {
            if (append) {
              self.state.conversations = [
                ...self.state.conversations,
                ...response.data.conversations,
              ];
            } else {
              self.state.conversations = response.data.conversations;
              self.state.currentPage = 1;
            }
            self.state.pagination = response.data.pagination;
            self.renderChats();
          } else {
            self.showError(response.data?.message || "Failed to fetch chats");
          }
        },
        error: function (xhr, status, error) {
          self.showError("Failed to fetch chats: " + error);
        },
        complete: function () {
          self.state.loading = false;
          self.state.loadingMore = false;
          self.hideLoading();
          self.hideLoadingMore();
        },
      });
    },

    /**
     * Handle load more button click
     */
    handleLoadMore: function () {
      if (this.state.pagination?.hasMore) {
        this.fetchChats(this.state.currentPage + 1, true);
        this.state.currentPage++;
      }
    },

    /**
     * Toggle chat expansion
     */
    toggleChatExpansion: function (chatId) {
      const isExpanded = this.state.expandedChats.has(chatId);

      if (isExpanded) {
        this.state.expandedChats.delete(chatId);
      } else {
        this.state.expandedChats.add(chatId);
      }

      this.updateChatExpansion(chatId, !isExpanded);
    },

    /**
     * Update chat expansion UI
     */
    updateChatExpansion: function (chatId, isExpanded) {
      const $chatCard = $(`.voicero-chat-card[data-chat-id="${chatId}"]`);
      const $toggle = $chatCard.find(".voicero-chat-toggle");
      const $messages = $chatCard.find(".voicero-chat-messages");

      if (isExpanded) {
        $messages.slideDown();
        $toggle.find(".voicero-toggle-text").text("Collapse");
        $toggle
          .find(".voicero-toggle-icon")
          .removeClass("dashicons-arrow-down")
          .addClass("dashicons-arrow-up");
      } else {
        $messages.slideUp();
        $toggle.find(".voicero-toggle-text").text("Expand");
        $toggle
          .find(".voicero-toggle-icon")
          .removeClass("dashicons-arrow-up")
          .addClass("dashicons-arrow-down");
      }
    },

    /**
     * Show loading state
     */
    showLoading: function () {
      $("#voicero-chats-container").html(`
                <div class="voicero-loading">
                    <div class="spinner is-active"></div>
                    <p>Loading conversations...</p>
                </div>
            `);
    },

    /**
     * Show loading more state
     */
    showLoadingMore: function () {
      $("#voicero-load-more-btn").prop("disabled", true).html(`
                <span class="spinner is-active"></span> Loading...
            `);
    },

    /**
     * Hide loading state
     */
    hideLoading: function () {
      $(".voicero-loading").remove();
    },

    /**
     * Hide loading more state
     */
    hideLoadingMore: function () {
      const $btn = $("#voicero-load-more-btn");
      $btn.prop("disabled", false);

      if (this.state.pagination?.hasMore) {
        const remaining =
          this.state.pagination.totalCount - this.state.conversations.length;
        $btn.text(`Load More (${remaining} remaining)`);
      } else {
        $btn.hide();
      }
    },

    /**
     * Show error message
     */
    showError: function (message) {
      $("#voicero-error-container")
        .html(
          `
                <div class="notice notice-error">
                    <p>${message}</p>
                </div>
            `
        )
        .show();
    },

    /**
     * Hide error message
     */
    hideError: function () {
      $("#voicero-error-container").hide();
    },

    /**
     * Render chats list
     */
    renderChats: function () {
      const $container = $("#voicero-chats-container");

      if (this.state.conversations.length === 0) {
        $container.html(`
                    <div class="voicero-no-chats">
                        <div class="dashicons dashicons-format-chat"></div>
                        <p>No conversations found</p>
                    </div>
                `);
        return;
      }

      let html = "";
      this.state.conversations.forEach((conversation) => {
        html += this.renderChatCard(conversation);
      });

      $container.html(html);

      // Update pagination info
      this.updatePaginationInfo();

      // Show/hide load more button
      this.updateLoadMoreButton();
    },

    /**
     * Render individual chat card
     */
    renderChatCard: function (conversation) {
      const isExpanded = this.state.expandedChats.has(conversation.id);
      const typeIcon = this.getTypeIcon(conversation.type);
      const typeBadge = this.getTypeBadge(
        conversation.type,
        conversation.source_type
      );
      const actionBadges = this.renderActionBadges(conversation.hasAction);
      const expandIcon = isExpanded
        ? "dashicons-arrow-up"
        : "dashicons-arrow-down";
      const expandText = isExpanded ? "Collapse" : "Expand";
      const messagesStyle = isExpanded ? "" : 'style="display: none;"';

      return `
                <div class="voicero-chat-card card" data-chat-id="${
                  conversation.id
                }">
                    <div class="voicero-chat-header">
                        <div class="voicero-chat-info">
                            <div class="voicero-chat-meta">
                                <span class="dashicons ${typeIcon}"></span>
                                ${typeBadge}
                                <span class="voicero-message-count">${
                                  conversation.messageCount
                                } messages</span>
                                <span class="voicero-chat-date">${this.formatDate(
                                  conversation.startedAt
                                )}</span>
                            </div>
                             <h3 class="voicero-chat-title">${this.highlightSearchText(
                               this.parseMarkdown(conversation.initialQuery),
                               this.state.searchQuery
                             )}</h3>
                            <div class="voicero-chat-details">
                                <span class="voicero-website-name">${
                                  conversation.website?.name ||
                                  conversation.website?.domain ||
                                  "Unknown"
                                }</span>
                                ${actionBadges}
                            </div>
                        </div>
                        <button class="button voicero-chat-toggle" data-chat-id="${
                          conversation.id
                        }">
                            <span class="dashicons ${expandIcon} voicero-toggle-icon"></span>
                            <span class="voicero-toggle-text">${expandText}</span>
                        </button>
                    </div>
                    
                    <div class="voicero-chat-messages" ${messagesStyle}>
                        <div class="voicero-messages-separator"></div>
                        <h4>Full Conversation</h4>
                        ${this.renderMessages(conversation.messages || [])}
                    </div>
                </div>
            `;
    },

    /**
     * Render chat messages
     */
    renderMessages: function (messages) {
      if (!messages || messages.length === 0) {
        return "<p>No messages available</p>";
      }

      let html = "";
      messages.forEach((message) => {
        const roleClass =
          message.role === "user"
            ? "voicero-user-message"
            : "voicero-assistant-message";
        const roleBadge = message.role === "user" ? "User" : "Assistant";
        const typeBadge =
          message.type === "voice"
            ? '<span class="voicero-voice-badge">Voice</span>'
            : "";

        html += `
                    <div class="voicero-message ${roleClass}">
                        <div class="voicero-message-header">
                            <span class="voicero-role-badge">${roleBadge}</span>
                            ${typeBadge}
                            <span class="voicero-message-date">${this.formatDate(
                              message.createdAt
                            )}</span>
                         </div>
                         <div class="voicero-message-content">
                             ${this.highlightSearchText(
                               this.parseMarkdown(message.content),
                               this.state.searchQuery
                             )}
                         </div>
                    </div>
                `;
      });

      return html;
    },

    /**
     * Get type icon class
     */
    getTypeIcon: function (type) {
      switch (type) {
        case "voice":
          return "dashicons-microphone";
        default:
          return "dashicons-format-chat";
      }
    },

    /**
     * Get type badge HTML
     */
    getTypeBadge: function (type, sourceType) {
      if (type === "voice" || sourceType === "voiceconversation") {
        return '<span class="voicero-badge voicero-badge-voice">Voice</span>';
      } else if (sourceType === "textconversation") {
        return '<span class="voicero-badge voicero-badge-text">Text</span>';
      } else {
        return '<span class="voicero-badge voicero-badge-ai">AI Thread</span>';
      }
    },

    /**
     * Render action badges
     */
    renderActionBadges: function (hasAction) {
      if (!hasAction) return "";

      let badges = "";
      if (hasAction.click)
        badges +=
          '<span class="voicero-badge voicero-badge-action">Click</span>';
      if (hasAction.scroll)
        badges +=
          '<span class="voicero-badge voicero-badge-scroll">Scroll</span>';
      if (hasAction.purchase)
        badges +=
          '<span class="voicero-badge voicero-badge-purchase">Purchase</span>';
      if (hasAction.redirect)
        badges +=
          '<span class="voicero-badge voicero-badge-redirect">Redirect</span>';

      return badges;
    },

    /**
     * Format date for display
     */
    formatDate: function (dateString) {
      if (!dateString) return "Unknown";

      const date = new Date(dateString);
      return date.toLocaleString();
    },

    /**
     * Convert markdown to HTML
     */
    parseMarkdown: function (text) {
      if (!text) return "";

      // Basic markdown parsing
      return (
        text
          // Bold **text** or __text__
          .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
          .replace(/__(.*?)__/g, "<strong>$1</strong>")
          // Italic *text* or _text_
          .replace(/\*(.*?)\*/g, "<em>$1</em>")
          .replace(/_(.*?)_/g, "<em>$1</em>")
          // Code `code`
          .replace(/`(.*?)`/g, "<code>$1</code>")
          // Code blocks ```code```
          .replace(/```([\s\S]*?)```/g, "<pre><code>$1</code></pre>")
          // Links [text](url)
          .replace(
            /\[([^\]]+)\]\(([^)]+)\)/g,
            '<a href="$2" target="_blank" rel="noopener">$1</a>'
          )
          // Line breaks
          .replace(/\n/g, "<br>")
      );
    },

    /**
     * Highlight search text in content
     */
    highlightSearchText: function (text, searchTerm) {
      if (!searchTerm || searchTerm.length < 3 || !text) {
        return text;
      }

      const regex = new RegExp(
        `(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`,
        "gi"
      );
      return text.replace(regex, "<mark>$1</mark>");
    },

    /**
     * Update pagination info
     */
    updatePaginationInfo: function () {
      const $info = $("#voicero-pagination-info");

      if (this.state.pagination) {
        const { totalCount, page, totalPages } = this.state.pagination;
        const showing = this.state.conversations.length;

        let text = `Showing ${showing} of ${totalCount} conversations`;
        if (totalPages > 1) {
          text += ` (Page ${page} of ${totalPages})`;
        }

        $info.text(text);
      }
    },

    /**
     * Update load more button
     */
    updateLoadMoreButton: function () {
      const $btn = $("#voicero-load-more-btn");

      if (this.state.pagination?.hasMore) {
        const remaining =
          this.state.pagination.totalCount - this.state.conversations.length;
        $btn.text(`Load More (${remaining} remaining)`).show();
      } else {
        $btn.hide();
      }
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    VoiceroChats.init();
  });

  // Make it globally available for debugging
  window.VoiceroChats = VoiceroChats;
})(jQuery);
