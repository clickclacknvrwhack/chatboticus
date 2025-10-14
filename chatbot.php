<?php
/**
 * Plugin Name: Click Foundry ChatBoticus
 * Description: AI-powered chatbot with office hours and away mode
 * Version: 2.1
 * Author: clickfoundry.co
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Start session for visitor tracking
add_action('init', 'alc_init_session');
function alc_init_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}

// Add settings page to WordPress admin
add_action('admin_menu', 'alc_add_admin_menu');
function alc_add_admin_menu() {
    add_options_page(
        'Lead Chatbot Settings',
        'Lead Chatbot',
        'manage_options',
        'architect-chatbot',
        'alc_settings_page'
    );
}

// Settings page HTML with status toggle
function alc_settings_page() {
    $is_active = get_option('alc_chatbot_active', '1');
    $away_mode = get_option('alc_away_mode', '0');
    $current_status = alc_get_current_status();
    
    ?>
<div class="wrap">
    <h1>Lead Chatbot Settings</h1>

    <div style="background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1;">
        <h2 style="margin-top: 0;">⚡ Quick Controls</h2>

        <form method="post" action="">
            <?php wp_nonce_field('alc_toggle_active'); ?>

            <p>
                <strong>Current Status:</strong>
                <span style="color: <?php echo $current_status['color']; ?>; font-weight: bold; font-size: 16px;">
                    <?php echo $current_status['text']; ?>
                </span>
            </p>

            <p style="padding: 15px; background: #f0f0f1; border-radius: 4px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="alc_chatbot_active" value="1" <?php checked($is_active, '1'); ?>
                        style="width: 20px; height: 20px;">
                    <strong style="font-size: 15px;">Chatbot Enabled</strong>
                    <span style="color: #666;">- Uncheck to completely disable chatbot on site</span>
                </label>
            </p>

            <p style="padding: 15px; background: #fff8e1; border-radius: 4px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="alc_away_mode" value="1" <?php checked($away_mode, '1'); ?>
                        style="width: 20px; height: 20px;">
                    <strong style="font-size: 15px;">🟡 Away Mode</strong>
                    <span style="color: #666;">- Bot collects info but doesn't ping you (great for
                        calls/meetings)</span>
                </label>
            </p>

            <p style="margin-top: 20px;">
                <button type="submit" class="button button-primary button-large">💾 Save Quick Controls</button>
            </p>
        </form>

        <hr style="margin: 20px 0;">

        <div style="background: #e7f3ff; padding: 15px; border-radius: 4px;">
            <h3 style="margin-top: 0;">📋 How It Works:</h3>
            <ul style="margin: 0;">
                <li><strong>Office Hours (9am-5pm PT, Mon-Fri):</strong> Bot pings you for high-intent leads</li>
                <li><strong>Outside Office Hours:</strong> Bot collects info, creates Discord thread, but no ping</li>
                <li><strong>Away Mode:</strong> Bot works normally but never pings (useful during meetings)</li>
                <li><strong>Disabled:</strong> Chat widget completely hidden from site</li>
            </ul>
        </div>
    </div>

    <?php
        if (isset($_POST['alc_chatbot_active']) || isset($_POST['_wpnonce'])) {
            check_admin_referer('alc_toggle_active');
            update_option('alc_chatbot_active', isset($_POST['alc_chatbot_active']) ? '1' : '0');
            update_option('alc_away_mode', isset($_POST['alc_away_mode']) ? '1' : '0');
            echo '<div class="notice notice-success"><p><strong>✅ Settings saved!</strong></p></div>';
            $current_status = alc_get_current_status(); // Refresh status
        }
        ?>

    <form method="post" action="options.php">
        <?php
            settings_fields('alc_settings');
            do_settings_sections('architect-chatbot');
            submit_button('Save Configuration');
            ?>
    </form>

    <hr style="margin: 40px 0;">

    <div style="background: white; padding: 20px; border-left: 4px solid #00a32a;">
        <h2>📡 Discord Bot Setup Instructions</h2>
        <ol style="line-height: 1.8;">
            <li>Create a Discord server (your private workspace)</li>
            <li>Enable Developer Mode in Discord User Settings → Advanced</li>
            <li>Create a #leads channel, right-click → Copy Channel ID</li>
            <li>Go to <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a>
            </li>
            <li>Create New Application → Add Bot → Copy Bot Token</li>
            <li>Enable <strong>MESSAGE CONTENT INTENT</strong> and <strong>SERVER MEMBERS INTENT</strong> under Bot
                settings</li>
            <li>OAuth2 → URL Generator → Select 'bot' scope and these permissions:
                <ul>
                    <li>Send Messages</li>
                    <li>Create Public Threads</li>
                    <li>Send Messages in Threads</li>
                    <li>Read Message History</li>
                </ul>
            </li>
            <li>Use generated URL to invite bot to your server</li>
            <li>Deploy the Discord listener bot on Railway.app (see below)</li>
        </ol>

        <h3>🚀 Railway.app Bot Setup</h3>
        <p>Your webhook URL for the Discord bot: <code><?php echo rest_url('alc/v1/discord-message'); ?></code></p>
        <p><a href="https://railway.app" target="_blank" class="button">Deploy on Railway →</a></p>
    </div>
</div>
<?php
}

// Get current chatbot status
function alc_get_current_status() {
    $is_active = get_option('alc_chatbot_active', '1') === '1';
    $away_mode = get_option('alc_away_mode', '0') === '1';
    $in_hours = alc_is_office_hours();
    
    if (!$is_active) {
        return array('text' => '🔴 DISABLED', 'color' => '#dc3232');
    }
    
    if ($away_mode) {
        return array('text' => '🟡 AWAY MODE (Collecting Info Only)', 'color' => '#f0b849');
    }
    
    if (!$in_hours) {
        return array('text' => '🌙 OUTSIDE OFFICE HOURS (Collecting Info)', 'color' => '#72aee6');
    }
    
    return array('text' => '🟢 ACTIVE & READY', 'color' => '#00a32a');
}

// Check if currently in office hours
function alc_is_office_hours() {
    $timezone = new DateTimeZone('America/Los_Angeles');
    $now = new DateTime('now', $timezone);
    
    $hour = (int)$now->format('G'); // 0-23
    $day = (int)$now->format('N'); // 1=Monday, 7=Sunday
    
    // Monday-Friday, 9am-5pm
    $is_weekday = $day >= 1 && $day <= 5;
    $is_office_hours = $hour >= 9 && $hour < 17;
    
    return $is_weekday && $is_office_hours;
}

// Load knowledge base
function alc_load_knowledge_base() {
    // First try custom knowledge from settings
    $custom = get_option('alc_custom_knowledge', '');
    if (!empty($custom)) {
        return $custom;
    }
    
    // Fall back to knowledge-base.txt file
    $kb_file = plugin_dir_path(__FILE__) . 'knowledgebase.txt';
    if (file_exists($kb_file)) {
        return file_get_contents($kb_file);
    }
    
    return '';
}

// Register settings
add_action('admin_init', 'alc_register_settings');
function alc_register_settings() {
    register_setting('alc_settings', 'alc_openai_key');
    register_setting('alc_settings', 'alc_your_name');
    register_setting('alc_settings', 'alc_greeting_message');
    register_setting('alc_settings', 'alc_after_hours_message');
    register_setting('alc_settings', 'alc_custom_knowledge');
    register_setting('alc_settings', 'alc_discord_bot_token');
    register_setting('alc_settings', 'alc_discord_channel_id');
    register_setting('alc_settings', 'alc_chatbot_active');
    register_setting('alc_settings', 'alc_away_mode');
    
    add_settings_section(
        'alc_main_section',
        'Core Configuration',
        null,
        'architect-chatbot'
    );
    
    add_settings_field(
        'alc_your_name',
        'Your Name',
        'alc_your_name_field',
        'architect-chatbot',
        'alc_main_section'
    );
    
    add_settings_field(
        'alc_openai_key',
        'OpenAI API Key',
        'alc_openai_field',
        'architect-chatbot',
        'alc_main_section'
    );
    
    add_settings_field(
        'alc_greeting_message',
        'Greeting Message (Office Hours)',
        'alc_greeting_field',
        'architect-chatbot',
        'alc_main_section'
    );
    
    add_settings_field(
        'alc_after_hours_message',
        'After Hours Message',
        'alc_after_hours_field',
        'architect-chatbot',
        'alc_main_section'
    );
    
    add_settings_field(
        'alc_custom_knowledge',
        'Custom Knowledge Base',
        'alc_custom_knowledge_field',
        'architect-chatbot',
        'alc_main_section'
    );
    
    add_settings_field(
        'alc_discord_bot_token',
        'Discord Bot Token',
        'alc_discord_token_field',
        'architect-chatbot',
        'alc_main_section'
    );
    
    add_settings_field(
        'alc_discord_channel_id',
        'Discord Channel ID',
        'alc_discord_channel_field',
        'architect-chatbot',
        'alc_main_section'
    );
}

// Field callbacks
function alc_your_name_field() {
    $value = get_option('alc_your_name', '');
    echo '<input type="text" name="alc_your_name" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., John Smith">';
}

function alc_openai_field() {
    $value = get_option('alc_openai_key', '');
    echo '<input type="text" name="alc_openai_key" value="' . esc_attr($value) . '" class="regular-text" placeholder="sk-...">';
    echo '<p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a></p>';
}

function alc_greeting_field() {
    $value = get_option('alc_greeting_message', 'Hi! I help businesses get better results from their websites. What brings you here today?');
    echo '<textarea name="alc_greeting_message" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Shown during office hours (9am-5pm PT, Mon-Fri)</p>';
}

function alc_after_hours_field() {
    $value = get_option('alc_after_hours_message', 'Thanks for reaching out! Our office hours are 9am-5pm PT, Monday-Friday. I can still help gather some info about your project. What are you looking for?');
    echo '<textarea name="alc_after_hours_message" rows="4" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Shown outside office hours or in away mode</p>';
}

function alc_custom_knowledge_field() {
    $value = get_option('alc_custom_knowledge', '');
    ?>
<textarea name="alc_custom_knowledge" rows="15" class="large-text code" placeholder="Add your company info here...

Example:
Company: Your Company Name
Services: Web development, WordPress, etc.
Service Area: Los Angeles, CA
Typical Budget: $1 - $15
Specialties: Building Rubberband Guns that Can Knock Out an Aggressor from 300 feet away
"><?php echo esc_textarea($value); ?></textarea>
<p class="description">
    The AI will use this information when chatting with visitors. Include your services, pricing, specialties, etc.
    <br>Alternatively, create a <code>knowledge-base.txt</code> file in the plugin folder.
</p>
<?php
}

function alc_discord_token_field() {
    $value = get_option('alc_discord_bot_token', '');
    echo '<input type="text" name="alc_discord_bot_token" value="' . esc_attr($value) . '" class="large-text">';
    echo '<p class="description">From <a href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</a> → Your App → Bot → Reset Token</p>';
}

function alc_discord_channel_field() {
    $value = get_option('alc_discord_channel_id', '');
    echo '<input type="text" name="alc_discord_channel_id" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">Right-click your #leads channel in Discord → Copy Channel ID (enable Developer Mode first)</p>';
}

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', 'alc_enqueue_scripts');
function alc_enqueue_scripts() {
    // Only load if chatbot is enabled
    if (get_option('alc_chatbot_active', '1') !== '1') {
        return;
    }
    
    wp_enqueue_style('alc-chatbot-style', plugin_dir_url(__FILE__) . 'chatbot.css', array(), '2.1');
    wp_enqueue_script('alc-chatbot-script', plugin_dir_url(__FILE__) . 'chatbot.js', array(), '2.1', true);
    
    $in_office_hours = alc_is_office_hours();
    $away_mode = get_option('alc_away_mode', '0') === '1';
    
    // Pass settings to JavaScript
    wp_localize_script('alc-chatbot-script', 'alcSettings', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('alc_chat_nonce'),
        'yourName' => get_option('alc_your_name', 'us'),
        'greeting' => $in_office_hours && !$away_mode 
            ? get_option('alc_greeting_message', 'Hi! I help businesses get better results from their websites.')
            : get_option('alc_after_hours_message', 'Thanks for reaching out! Our office hours are 9am-5pm PT.'),
        'isOfficeHours' => $in_office_hours,
        'awayMode' => $away_mode
    ));
}

// Add chatbot HTML to footer
add_action('wp_footer', 'alc_add_chatbot_html');
function alc_add_chatbot_html() {
    // Only show if enabled
    if (get_option('alc_chatbot_active', '1') !== '1') {
        return;
    }
    
    ?>
<div id="alc-chat-widget">
    <div id="alc-chat-toggle">💬</div>
    <div id="alc-chat-box" style="display:none;">
        <div id="alc-chat-header">
            <span>Chat with us</span>
            <span id="alc-chat-close">×</span>
        </div>
        <div id="alc-chat-messages"></div>
        <div id="alc-chat-input-area">
            <input type="text" id="alc-chat-input" placeholder="Type a message..." />
            <button id="alc-chat-send">Send</button>
        </div>
    </div>
</div>
<?php
}

// Get or create session ID
function alc_get_session_id() {
    if (!isset($_SESSION['alc_session_id'])) {
        $_SESSION['alc_session_id'] = uniqid('visitor_', true);
    }
    return $_SESSION['alc_session_id'];
}

// Create Discord thread
function alc_create_discord_thread($bot_token, $channel_id, $session_id, $messages, $should_notify) {
    $first_user_msg = '';
    foreach ($messages as $msg) {
        if ($msg['role'] === 'user') {
            $first_user_msg = substr($msg['content'], 0, 80);
            break;
        }
    }
    
    $thread_name = $first_user_msg ?: 'New Visitor';
    
    $status = alc_get_current_status();
    $conversation = "🎯 **New Lead**\n\n";
    $conversation .= "**Status:** {$status['text']}\n";
    $conversation .= "**Session:** `{$session_id}`\n";
    $conversation .= "**Time:** " . current_time('mysql') . "\n";
    $conversation .= "**Page:** " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : home_url()) . "\n\n";
    
    if (!$should_notify) {
        $conversation .= "📝 **Info Collected (No Notification Sent)**\n\n";
    } else {
        $conversation .= "**💬 Live Chat - Reply here to respond!**\n\n";
    }
    
    $conversation .= "**Conversation:**\n";
    
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? '👤 Visitor' : '🤖 Bot';
        $conversation .= "{$role}: {$msg['content']}\n";
    }
    
    $response = wp_remote_post(
        "https://discord.com/api/v10/channels/{$channel_id}/messages",
        array(
            'headers' => array(
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'content' => $conversation
            )),
            'timeout' => 10
        )
    );
    
    if (is_wp_error($response)) {
        error_log('Discord create message error: ' . $response->get_error_message());
        return null;
    }
    
    $message_data = json_decode(wp_remote_retrieve_body($response), true);
    $message_id = $message_data['id'] ?? null;
    
    if (!$message_id) return null;
    
    $thread_response = wp_remote_post(
        "https://discord.com/api/v10/channels/{$channel_id}/messages/{$message_id}/threads",
        array(
            'headers' => array(
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'name' => $thread_name,
                'auto_archive_duration' => 1440
            )),
            'timeout' => 10
        )
    );
    
    if (is_wp_error($thread_response)) {
        error_log('Discord create thread error: ' . $thread_response->get_error_message());
        return null;
    }
    
    $thread_data = json_decode(wp_remote_retrieve_body($thread_response), true);
    $thread_id = $thread_data['id'] ?? null;
    
    if ($thread_id) {
        set_transient('alc_thread_' . $session_id, $thread_id, 24 * HOUR_IN_SECONDS);
        set_transient('alc_session_' . $thread_id, $session_id, 24 * HOUR_IN_SECONDS);
    }
    
    return $thread_id;
}

// Add message to Discord thread
function alc_add_to_discord_thread($bot_token, $thread_id, $message, $is_bot = false) {
    $role = $is_bot ? '🤖 Bot' : '👤 Visitor';
    $content = "{$role}: {$message}";
    
    wp_remote_post(
        "https://discord.com/api/v10/channels/{$thread_id}/messages",
        array(
            'headers' => array(
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'content' => $content
            )),
            'timeout' => 10
        )
    );
}

// Main Discord notification function
function alc_send_discord_notification($session_id, $message, $is_bot = false, $all_messages = array(), $should_notify = true) {
    $bot_token = get_option('alc_discord_bot_token');
    $channel_id = get_option('alc_discord_channel_id');
    
    if (!$bot_token || !$channel_id) return;
    
    $thread_id = get_transient('alc_thread_' . $session_id);
    
    if (!$thread_id && !empty($all_messages)) {
        $thread_id = alc_create_discord_thread($bot_token, $channel_id, $session_id, $all_messages, $should_notify);
    } elseif ($thread_id) {
        alc_add_to_discord_thread($bot_token, $thread_id, $message, $is_bot);
    }
}

// AJAX handler for chat messages
add_action('wp_ajax_alc_chat', 'alc_handle_chat');
add_action('wp_ajax_nopriv_alc_chat', 'alc_handle_chat');

function alc_handle_chat() {
    check_ajax_referer('alc_chat_nonce', 'nonce');
    
    $messages = json_decode(stripslashes($_POST['messages']), true);
    $should_notify_user = $_POST['shouldNotify'] === 'true';
    $session_id = alc_get_session_id();
    
    $last_user_message = '';
    foreach (array_reverse($messages) as $msg) {
        if ($msg['role'] === 'user') {
            $last_user_message = $msg['content'];
            break;
        }
    }
    
    // Check if we should actually notify (office hours + not away mode)
    $in_office_hours = alc_is_office_hours();
    $away_mode = get_option('alc_away_mode', '0') === '1';
    $should_ping = $should_notify_user && $in_office_hours && !$away_mode;
    
    $is_first = count($messages) <= 2;
    
    if ($is_first) {
        alc_send_discord_notification($session_id, '', false, $messages, $should_ping);
    } else {
        alc_send_discord_notification($session_id, $last_user_message, false, array(), $should_ping);
    }
    
    // Add notification in thread if high-intent but not pinging
    if ($should_notify_user && !$should_ping) {
        $thread_id = get_transient('alc_thread_' . $session_id);
        if ($thread_id) {
            $bot_token = get_option('alc_discord_bot_token');
            $reason = !$in_office_hours ? '(Outside office hours)' : '(Away mode)';
            wp_remote_post(
                "https://discord.com/api/v10/channels/{$thread_id}/messages",
                array(
                    'headers' => array(
                        'Authorization' => 'Bot ' . $bot_token,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode(array(
                        'content' => "📝 **High-intent lead - Info collected** {$reason}"
                    )),
                    'timeout' => 10
                )
            );
        }
    }
    
    // If should notify during office hours, tell user and ping Discord
    if ($should_notify_user && $should_ping) {
        $thread_id = get_transient('alc_thread_' . $session_id);
        if ($thread_id) {
            $bot_token = get_option('alc_discord_bot_token');
            wp_remote_post(
                "https://discord.com/api/v10/channels/{$thread_id}/messages",
                array(
                    'headers' => array(
                        'Authorization' => 'Bot ' . $bot_token,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode(array(
                        'content' => "🔔 **High-intent lead! Jump in now!**"
                    )),
                    'timeout' => 10
                )
            );
        }
        
        wp_send_json_success(array(
            'message' => "Great! I've notified " . get_option('alc_your_name', 'the team') . " - they'll be right with you!"
        ));
        return;
    }
    
    // Get AI response
    $api_key = get_option('alc_openai_key');
    if (!$api_key) {
        wp_send_json_error('API key not configured');
        return;
    }
    
    $your_name = get_option('alc_your_name', 'our team');
    $knowledge = alc_load_knowledge_base();
    
    $system_message = "You are a helpful AI assistant for a web development company.

COMPANY INFORMATION:
{$knowledge}

YOUR ROLE AS FIRST CONTACT:
You're qualifying potential clients. Your job is to:
1. Understand what industry they're in
2. Learn about their current website situation and pain points
3. Understand their goals
4. Gauge budget and timeline
5. Determine if they're the decision-maker

QUALIFICATION APPROACH:
Ask questions naturally, not like a form. Focus on:
- 'What brings you here today?' or 'What's not working with your current site?'
- 'What industry are you in?'
- 'What's your timeline?' and 'Do you have a budget in mind?'
- 'What would success look like for this project?'

HIGH-INTENT SIGNALS (connect them to {$your_name} faster):
- Has specific budget
- Timeline is reasonable (1-3 months)
- Decision-maker or can involve decision-maker
- Clear pain points with current site
- Mentions competitors' sites

RESPONSE STYLE:
- Conversational and friendly
- 2-3 sentences max per response
- After 2-3 exchanges with good signals, offer to connect with {$your_name}
- Be helpful even to low-intent leads

Remember: Qualify properly but stay helpful. Every interaction reflects the brand.";
    
    // Add context if outside office hours
    if (!$in_office_hours || $away_mode) {
        $system_message .= "\n\nNote: It's currently outside office hours (9am-5pm PT, Mon-Fri) or the team is away. Focus on collecting comprehensive project details so the team can follow up.";
    }
    
    $data = array(
        'model' => 'gpt-4o-mini',
        'messages' => array_merge(
            array(array('role' => 'system', 'content' => $system_message)),
            $messages
        ),
        'max_tokens' => 150,
        'temperature' => 0.7
    );
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        $bot_message = $result['choices'][0]['message']['content'];
        
        alc_send_discord_notification($session_id, $bot_message, true);
        
        wp_send_json_success(array(
            'message' => $bot_message
        ));
    } else {
        wp_send_json_error('Error getting response');
    }
}

// Check for human replies from Discord
add_action('wp_ajax_alc_check_replies', 'alc_check_discord_replies');
add_action('wp_ajax_nopriv_alc_check_replies', 'alc_check_discord_replies');

function alc_check_discord_replies() {
    check_ajax_referer('alc_chat_nonce', 'nonce');
    
    $session_id = alc_get_session_id();
    $reply = get_transient('alc_reply_' . $session_id);
    
    if ($reply) {
        delete_transient('alc_reply_' . $session_id);
        wp_send_json_success(array(
            'hasReply' => true,
            'message' => $reply
        ));
    } else {
        wp_send_json_success(array(
            'hasReply' => false
        ));
    }
}

// REST API endpoint for Discord bot
add_action('rest_api_init', function() {
    register_rest_route('alc/v1', '/discord-message', array(
        'methods' => 'POST',
        'callback' => 'alc_handle_discord_message',
        'permission_callback' => 'alc_verify_discord_request'
    ));
});

function alc_verify_discord_request($request) {
    $auth = $request->get_header('Authorization');
    $bot_token = get_option('alc_discord_bot_token');
    return $auth === 'Bot ' . $bot_token;
}

function alc_handle_discord_message($request) {
    $data = $request->get_json_params();
    
    $thread_id = $data['thread_id'] ?? '';
    $message = $data['message'] ?? '';
    $author_bot = $data['author_is_bot'] ?? false;
    
    if ($author_bot || empty($message) || empty($thread_id)) {
        return array('success' => true);
    }
    
    $session_id = get_transient('alc_session_' . $thread_id);
    
    if ($session_id) {
        set_transient('alc_reply_' . $session_id, $message, 5 * MINUTE_IN_SECONDS);
    }
    
    return array('success' => true, 'session_id' => $session_id);
}