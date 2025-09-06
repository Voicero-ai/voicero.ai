<?php
/**
 * Voicero AI - Chats Admin Page
 * Displays and manages chat conversations
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Render the chats admin page
 */
function voicero_render_chats_page() {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'voicero-ai'));
    }

    // Get access key and website data
    $access_key = voicero_get_access_key();
    $website_data = null;
    $error_message = null;

    if (empty($access_key)) {
        $error_message = esc_html__('No access key found. Please connect your website first.', 'voicero-ai');
    } else {
        // Try to get website data
        $website_data = voicero_get_website_data($access_key);
        if (!$website_data) {
            $error_message = esc_html__('Unable to retrieve website information. Please check your connection.', 'voicero-ai');
        }
    }

    ?>
    <div class="wrap voicero-chats-page">
        <h1 class="wp-heading-inline"><?php esc_html_e('Chats', 'voicero-ai'); ?></h1>
        <p class="description"><?php esc_html_e('View and manage conversations from your AI assistant.', 'voicero-ai'); ?></p>

        <?php if ($error_message): ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html($error_message); ?></p>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=voicero-ai-admin')); ?>" class="button"><?php esc_html_e('Go to Main Page', 'voicero-ai'); ?></a></p>
            </div>
        <?php else: ?>
            
            <!-- Error Container -->
            <div id="voicero-error-container" style="display: none;"></div>

            <!-- Filters Card -->
            <div class="card">
                <h2><?php esc_html_e('Filters', 'voicero-ai'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="voicero-website-input"><?php esc_html_e('Connected Website', 'voicero-ai'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="voicero-website-input" 
                                   class="regular-text" 
                                   value="<?php echo esc_attr(($website_data['name'] ?? $website_data['url'] ?? 'Unknown') . ' (ID: ' . ($website_data['id'] ?? 'Unknown') . ')'); ?>" 
                                   disabled>
                            <p class="description"><?php esc_html_e('This is your connected website', 'voicero-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="voicero-search-input"><?php esc_html_e('Search Conversations', 'voicero-ai'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="voicero-search-input" 
                                   class="regular-text" 
                                   placeholder="<?php esc_attr_e('Enter at least 3 characters to search...', 'voicero-ai'); ?>">
                            <p class="description" id="voicero-search-help"><?php esc_html_e('Enter at least 3 characters to search...', 'voicero-ai'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="voicero-sort-select"><?php esc_html_e('Sort by', 'voicero-ai'); ?></label>
                        </th>
                        <td>
                            <select id="voicero-sort-select" class="regular-text">
                                <!-- Options populated by JavaScript -->
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="voicero-search-btn" class="button button-primary">
                                <?php esc_html_e('Load All', 'voicero-ai'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Chats Container -->
            <div id="voicero-chats-container">
                <!-- Chats will be loaded here by JavaScript -->
            </div>

            <!-- Load More Button -->
            <div class="voicero-load-more-container" style="text-align: center; margin: 20px 0;">
                <button type="button" id="voicero-load-more-btn" class="button button-secondary" style="display: none;">
                    <?php esc_html_e('Load More', 'voicero-ai'); ?>
                </button>
            </div>

            <!-- Pagination Info -->
            <div class="voicero-pagination-info" style="text-align: center; margin: 10px 0;">
                <p id="voicero-pagination-info" class="description"></p>
            </div>

        <?php endif; ?>
    </div>

    <style>
    .voicero-chats-page .card {
        margin: 20px 0;
        padding: 20px;
    }

    .voicero-loading {
        text-align: center;
        padding: 40px;
    }

    .voicero-loading .spinner {
        float: none;
        margin: 0 auto 20px;
    }

    .voicero-no-chats {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .voicero-no-chats .dashicons {
        font-size: 48px;
        width: 48px;
        height: 48px;
        margin-bottom: 20px;
    }

    .voicero-chat-card {
        margin: 15px 0;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }

    .voicero-chat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 20px;
        border-bottom: 1px solid #f0f0f1;
    }

    .voicero-chat-info {
        flex: 1;
    }

    .voicero-chat-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        font-size: 13px;
        color: #646970;
    }

    .voicero-chat-meta .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    .voicero-chat-title {
        margin: 0 0 10px 0;
        font-size: 16px;
        font-weight: 600;
        line-height: 1.4;
    }

    .voicero-chat-title mark {
        background-color: #fff2cc;
        padding: 1px 2px;
        border-radius: 2px;
    }

    .voicero-chat-details {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: #646970;
    }

    .voicero-chat-toggle {
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .voicero-chat-toggle .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        vertical-align: middle;
        margin-top: 0.5px;
    }

    .voicero-chat-messages {
        padding: 20px;
        background-color: #f9f9f9;
    }

    .voicero-messages-separator {
        border-top: 1px solid #ddd;
        margin: 0 -20px 20px -20px;
    }

    .voicero-chat-messages h4 {
        margin: 0 0 15px 0;
        font-size: 14px;
        font-weight: 600;
    }

    .voicero-message {
        margin-bottom: 15px;
        padding: 12px 15px;
        border-radius: 5px;
        border-left: 3px solid;
    }

    .voicero-user-message {
        background-color: #e3f2fd;
        border-left-color: #2196f3;
    }

    .voicero-assistant-message {
        background-color: #f1f8e9;
        border-left-color: #4caf50;
    }

    .voicero-message-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 600;
    }

    .voicero-message-content {
        line-height: 1.5;
    }

    .voicero-message-content mark {
        background-color: #fff2cc;
        padding: 1px 2px;
        border-radius: 2px;
    }

    /* Markdown styling */
    .voicero-message-content strong,
    .voicero-chat-title strong {
        font-weight: 600;
    }

    .voicero-message-content em,
    .voicero-chat-title em {
        font-style: italic;
    }

    .voicero-message-content code,
    .voicero-chat-title code {
        background-color: #f1f1f1;
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 2px 4px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 13px;
    }

    .voicero-message-content pre {
        background-color: #f8f8f8;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 10px;
        margin: 10px 0;
        overflow-x: auto;
    }

    .voicero-message-content pre code {
        background: none;
        border: none;
        padding: 0;
        font-size: 12px;
        line-height: 1.4;
    }

    .voicero-message-content a,
    .voicero-chat-title a {
        color: #0073aa;
        text-decoration: none;
    }

    .voicero-message-content a:hover,
    .voicero-chat-title a:hover {
        color: #005177;
        text-decoration: underline;
    }

    .voicero-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        line-height: 1.2;
    }

    .voicero-badge-voice {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .voicero-badge-text {
        background-color: #e8f5e8;
        color: #2e7d32;
    }

    .voicero-badge-ai {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }

    .voicero-badge-action {
        background-color: #fff3e0;
        color: #f57c00;
    }

    .voicero-badge-scroll {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .voicero-badge-purchase {
        background-color: #e8f5e8;
        color: #2e7d32;
    }

    .voicero-badge-redirect {
        background-color: #ffebee;
        color: #c62828;
    }

    .voicero-role-badge {
        background-color: #646970;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .voicero-voice-badge {
        background-color: #ff9800;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .voicero-message-date {
        color: #646970;
        font-weight: normal;
    }

    .voicero-pagination-info {
        color: #646970;
        font-style: italic;
    }

    @media (max-width: 782px) {
        .voicero-chat-header {
            flex-direction: column;
            gap: 15px;
        }

        .voicero-chat-toggle {
            align-self: flex-start;
        }

        .voicero-chat-meta {
            flex-wrap: wrap;
        }
    }
    </style>
    <?php
}

/**
 * Get website data from Voicero API
 */
function voicero_get_website_data($access_key) {
    if (empty($access_key)) {
        return null;
    }

    $response = wp_remote_get(VOICERO_API_URL . '/connect', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ],
        'timeout' => 15,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        voicero_debug_log('Error fetching website data', [
            'error' => $response->get_error_message()
        ]);
        return null;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        voicero_debug_log('API returned error code', [
            'code' => $response_code,
            'body' => $body
        ]);
        return null;
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        voicero_debug_log('Invalid JSON response from API', [
            'body' => $body
        ]);
        return null;
    }

    return $data['website'] ?? null;
}

/**
 * AJAX handler to get chats data
 */
function voicero_get_chats() {
    // Verify nonce for security
    if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'voicero_ajax_nonce')) {
        wp_send_json_error(['message' => esc_html__('Security check failed', 'voicero-ai')]);
        return;
    }

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('You do not have permission to perform this action.', 'voicero-ai')]);
        return;
    }

    // Get access key
    $access_key = voicero_get_access_key();
    if (empty($access_key)) {
        wp_send_json_error(['message' => esc_html__('No access key found', 'voicero-ai')]);
        return;
    }

    // Get parameters
    $website_id = isset($_GET['websiteId']) ? sanitize_text_field(wp_unslash($_GET['websiteId'])) : '';
    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $sort = isset($_GET['sort']) ? sanitize_text_field(wp_unslash($_GET['sort'])) : 'recent';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

    // Validate parameters
    $page = max(1, $page);
    $limit = max(1, min($limit, 50)); // Limit between 1 and 50

    // Build query parameters for external API
    $query_params = [
        'page' => $page,
        'limit' => $limit,
        'sort' => $sort,
    ];

    if (!empty($website_id)) {
        $query_params['websiteId'] = $website_id;
    }

    if (!empty($search) && strlen($search) >= 3) {
        $query_params['search'] = $search;
    }

    // Build URL
    $api_url = 'https://www.voicero.ai/api/websites/chats?' . http_build_query($query_params);

    // Call external API with access key
    $response = wp_remote_get($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_key,
        ],
        'timeout' => 30,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        voicero_debug_log('Error calling chats API', [
            'error' => $response->get_error_message(),
            'url' => $api_url
        ]);
        wp_send_json_error(['message' => esc_html__('Failed to fetch chats: ', 'voicero-ai') . esc_html($response->get_error_message())]);
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        voicero_debug_log('Chats API returned error', [
            'code' => $response_code,
            'body' => $body,
            'url' => $api_url
        ]);
        wp_send_json_error(['message' => esc_html__('API call failed: ', 'voicero-ai') . esc_html($response_code)]);
        return;
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        voicero_debug_log('Invalid JSON response from chats API', [
            'body' => $body
        ]);
        wp_send_json_error(['message' => esc_html__('Invalid response from server', 'voicero-ai')]);
        return;
    }

    wp_send_json_success($data);
}

// Register AJAX handlers
add_action('wp_ajax_voicero_get_chats', 'voicero_get_chats');

/**
 * Enqueue scripts for chats page
 */
function voicero_enqueue_chats_scripts($hook_suffix) {
    // Only load on the chats page
    if ($hook_suffix !== 'voicero-ai_page_voicero-ai-chats') {
        return;
    }

    // Enqueue the chats JavaScript
    wp_enqueue_script(
        'voicero-chats-js',
        plugin_dir_url(__FILE__) . '../assets/js/admin/voicero-chats.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Get access key and website ID for JavaScript
    $access_key = get_option('voicero_access_key', '');
    $website_id = get_option('voicero_website_id', '');

    // Localize script with configuration
    wp_localize_script(
        'voicero-chats-js',
        'voiceroAdminConfig',
        [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('voicero_ajax_nonce'),
            'accessKey' => $access_key,
            'apiUrl'    => defined('VOICERO_API_URL') ? VOICERO_API_URL : 'https://www.voicero.ai/api',
            'websiteId' => $website_id
        ]
    );
}
add_action('admin_enqueue_scripts', 'voicero_enqueue_chats_scripts');
