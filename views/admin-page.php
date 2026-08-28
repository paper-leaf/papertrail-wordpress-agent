<?php
/**
 * Admin page view for the PaperTrail WordPress Agent.
 *
 * Included from papertrail_wordpress_agent_render_page().
 */

defined('ABSPATH') || exit;

$papertrail_token_applied = get_option(PAPERTRAIL_WP_AGENT_TOKEN_OPTION, '') !== '';
$papertrail_notice        = isset($_GET['papertrail-notice']) ? sanitize_key(wp_unslash($_GET['papertrail-notice'])) : '';
?>
<div class="wrap">
    <h1><?php echo esc_html__('PaperTrail WordPress Agent', 'papertrail-wordpress-agent'); ?></h1>

    <?php if ($papertrail_notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Connection token saved.', 'papertrail-wordpress-agent'); ?></p>
        </div>
    <?php elseif ($papertrail_notice === 'empty') : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('Enter a token before saving.', 'papertrail-wordpress-agent'); ?></p>
        </div>
    <?php elseif ($papertrail_notice === 'released') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Connection token released.', 'papertrail-wordpress-agent'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($papertrail_token_applied) : ?>

        <p><?php echo esc_html__('A connection token is applied. To use a different one, release the current token first.', 'papertrail-wordpress-agent'); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(PAPERTRAIL_WP_AGENT_RELEASE_ACTION); ?>" />
            <?php wp_nonce_field(PAPERTRAIL_WP_AGENT_RELEASE_ACTION); ?>
            <?php submit_button(__('Release token', 'papertrail-wordpress-agent'), 'delete'); ?>
        </form>

    <?php else : ?>

        <p><?php echo esc_html__('No connection token is applied. Paste the token PaperTrail generated for this environment.', 'papertrail-wordpress-agent'); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(PAPERTRAIL_WP_AGENT_SAVE_ACTION); ?>" />
            <?php wp_nonce_field(PAPERTRAIL_WP_AGENT_SAVE_ACTION); ?>

            <p>
                <label for="papertrail-wordpress-agent-token">
                    <?php echo esc_html__('Connection token', 'papertrail-wordpress-agent'); ?>
                </label>
                <input
                    type="password"
                    id="papertrail-wordpress-agent-token"
                    name="<?php echo esc_attr(PAPERTRAIL_WP_AGENT_TOKEN_OPTION); ?>"
                    autocomplete="off"
                />
            </p>

            <?php submit_button(__('Save token', 'papertrail-wordpress-agent')); ?>
        </form>

    <?php endif; ?>
</div>
