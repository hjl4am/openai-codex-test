<?php
/**
 * Plugin Name: WordPress AI Rewriter
 * Description: 使用统一界面管理大模型 API，并支持在后台一键改写 WordPress 文章。
 * Version: 0.1.0
 * Author: Codex
 */

if (!defined('ABSPATH')) {
    exit;
}

class WordPress_AI_Rewriter {
    private const OPTION_KEY = 'wpair_settings';
    private const MENU_SLUG = 'wpair-settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'register_post_metabox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wpair_rewrite_post', [$this, 'handle_rewrite_request']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
    }

    public function register_admin_page(): void {
        add_menu_page(
            'AI 改写设置',
            'AI 改写',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_settings_page'],
            'dashicons-edit-page',
            58
        );

        add_submenu_page(
            'options-general.php',
            'AI 改写设置',
            'AI 改写设置',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            'wpair_settings_group',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]
        );

        add_settings_section(
            'wpair_section_api',
            '大模型 API 设置',
            function () {
                echo '<p>请在插件后台统一管理和配置 api_endpoint、api_key、model、system_prompt。</p>';
            },
            self::MENU_SLUG
        );

        $fields = [
            'provider_name' => '提供商名称',
            'api_endpoint' => 'API Endpoint',
            'api_key' => 'API Key',
            'model' => '模型名称',
            'system_prompt' => '系统提示词',
        ];

        foreach ($fields as $field_key => $label) {
            add_settings_field(
                'wpair_' . $field_key,
                $label,
                [$this, 'render_field'],
                self::MENU_SLUG,
                'wpair_section_api',
                ['field_key' => $field_key]
            );
        }
    }

    public function add_plugin_action_links(array $links): array {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)) . '">设置</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_admin_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php', 'toplevel_page_wpair-settings'], true)) {
            return;
        }

        wp_enqueue_script(
            'wpair-admin-js',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            ['jquery'],
            '0.1.0',
            true
        );

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            $post = get_post();
            if (!$post || $post->post_type !== 'post') {
                return;
            }

            wp_localize_script(
                'wpair-admin-js',
                'wpairData',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wpair_rewrite_nonce'),
                    'postId' => $post->ID,
                ]
            );
        }
    }

    public function register_post_metabox(): void {
        add_meta_box(
            'wpair_rewriter_metabox',
            'AI 文章改写',
            [$this, 'render_post_metabox'],
            'post',
            'side'
        );
    }

    public function render_post_metabox(): void {
        echo '<p>点击后将调用已配置的大模型接口，改写当前文章标题与正文。</p>';
        echo '<button type="button" class="button button-primary" id="wpair-rewrite-btn">AI 改写文章</button>';
        echo '<p id="wpair-rewrite-status" style="margin-top:8px;"></p>';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>WordPress AI 改写设置</h1>
            <?php $this->render_settings_summary(); ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('wpair_settings_group');
                do_settings_sections(self::MENU_SLUG);
                submit_button('保存设置');
                ?>
            </form>
        </div>
        <?php
    }

    private function render_settings_summary(): void {
        $settings = $this->get_settings();
        $status = $this->is_ready($settings) ? '已配置，可用于改写文章。' : '未完成，请补全 api_endpoint、api_key、model、system_prompt。';
        $api_key_preview = empty($settings['api_key']) ? '未设置' : str_repeat('*', 8);

        echo '<div class="notice notice-info" style="padding:12px 16px; margin: 12px 0 18px;">';
        echo '<p><strong>当前配置状态：</strong>' . esc_html($status) . '</p>';
        echo '<p><strong>api_endpoint:</strong> ' . esc_html($settings['api_endpoint']) . '<br/>';
        echo '<strong>api_key:</strong> ' . esc_html($api_key_preview) . '<br/>';
        echo '<strong>model:</strong> ' . esc_html($settings['model']) . '<br/>';
        echo '<strong>system_prompt:</strong> ' . esc_html(wp_trim_words($settings['system_prompt'], 20, '...')) . '</p>';
        echo '</div>';
    }

    public function render_field(array $args): void {
        $field_key = $args['field_key'];
        $settings = $this->get_settings();
        $value = $settings[$field_key] ?? '';
        $name = self::OPTION_KEY . '[' . $field_key . ']';

        if ($field_key === 'system_prompt') {
            printf(
                '<textarea name="%s" rows="5" class="large-text">%s</textarea>',
                esc_attr($name),
                esc_textarea($value)
            );
            return;
        }

        $type = $field_key === 'api_key' ? 'password' : 'text';
        printf(
            '<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off"/>',
            esc_attr($type),
            esc_attr($name),
            esc_attr($value)
        );

        if ($field_key === 'api_key') {
            echo '<p class="description">留空则保持当前 API Key 不变。</p>';
        }
    }

    public function sanitize_settings(array $settings): array {
        $defaults = $this->default_settings();
        $current = $this->get_settings();
        $api_key = sanitize_text_field($settings['api_key'] ?? '');

        if ($api_key === '' && !empty($current['api_key'])) {
            $api_key = $current['api_key'];
        }

        return [
            'provider_name' => sanitize_text_field($settings['provider_name'] ?? $defaults['provider_name']),
            'api_endpoint' => esc_url_raw($settings['api_endpoint'] ?? $defaults['api_endpoint']),
            'api_key' => $api_key,
            'model' => sanitize_text_field($settings['model'] ?? $defaults['model']),
            'system_prompt' => sanitize_textarea_field($settings['system_prompt'] ?? $defaults['system_prompt']),
        ];
    }

    public function handle_rewrite_request(): void {
        check_ajax_referer('wpair_rewrite_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '权限不足。'], 403);
        }

        $post_id = intval($_POST['postId'] ?? 0);
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            wp_send_json_error(['message' => '未找到文章。'], 404);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => '无权改写该文章。'], 403);
        }

        $settings = $this->get_settings();
        if (!$this->is_ready($settings)) {
            wp_send_json_error(['message' => '请先在插件设置中完成 API 配置。'], 400);
        }

        $payload = [
            'model' => $settings['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $settings['system_prompt'],
                ],
                [
                    'role' => 'user',
                    'content' => "请改写这篇文章，并返回 JSON，格式为 {\"title\":\"\",\"content\":\"\"}。\n标题：{$post->post_title}\n内容：{$post->post_content}",
                ],
            ],
            'temperature' => 0.7,
        ];

        $response = wp_remote_post(
            $settings['api_endpoint'],
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $settings['api_key'],
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 60,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => '请求大模型失败：' . $response->get_error_message()], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        $content = $json['choices'][0]['message']['content'] ?? '';
        $rewritten = json_decode($content, true);

        if (!is_array($rewritten) || empty($rewritten['title']) || empty($rewritten['content'])) {
            wp_send_json_error(['message' => '模型返回格式异常，请检查提示词和模型输出。'], 422);
        }

        wp_update_post(
            [
                'ID' => $post_id,
                'post_title' => wp_kses_post($rewritten['title']),
                'post_content' => wp_kses_post($rewritten['content']),
            ]
        );

        wp_send_json_success(['message' => '文章已改写并保存。']);
    }

    private function default_settings(): array {
        return [
            'provider_name' => 'OpenAI Compatible',
            'api_endpoint' => 'https://api.openai.com/v1/chat/completions',
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'system_prompt' => '你是一位专业中文编辑。请在保留原意前提下提升文章可读性、逻辑性和表达质量。',
        ];
    }

    private function get_settings(): array {
        $stored = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($stored) ? $stored : [], $this->default_settings());
    }

    private function is_ready(array $settings): bool {
        return !empty($settings['api_endpoint'])
            && !empty($settings['api_key'])
            && !empty($settings['model'])
            && !empty($settings['system_prompt']);
    }
}

new WordPress_AI_Rewriter();
