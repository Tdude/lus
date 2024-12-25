<?php
/** lus-dashboard.php
 * Admin dashboard view
 */

if (!defined('WPINC')) {
    die;
}

$upload_dir = wp_upload_dir();

// Get passage filter if set
$passage_filter = isset($_GET['passage_filter']) ? intval($_GET['passage_filter']) : 0;
$passage_title = '';
if ($passage_filter) {
    $passage = $this->db->get_passage($passage_filter);
    if ($passage) {
        $passage_title = $passage->title;
    }
}
?>

<div class="wrap">
    <h1>
        <?php
        echo esc_html(get_admin_page_title());
        if ($passage_filter && $passage_title) {
            echo ' - ' . sprintf(
                __('Inspelningar för "%s"', 'lus'),
                esc_html($passage_title)
            );
        }
        ?>
    </h1>

    <button type="button" id="toggle-instructions" class="button button-secondary">
        <?php echo esc_html__('Visa/dölj instruktioner', 'lus'); ?>
    </button>

    <!-- Instructions Section -->
    <div id="instructions-content" class="instructions-content">
        <div class="two-cols">
            <div>
                <h2><?php _e('Så här använder du LUS', 'lus'); ?></h2>
                <p><?php _e('Instruktionerna sparas mellan sessioner.', 'lus'); ?></p>
                <?php
                    // Instruction text can be filtered by themes/other plugins
                    echo wp_kses_post(apply_filters('lus_dashboard_instructions',
                        '<p>' . __('Standardinstruktioner här...', 'lus') . '</p>'
                    ));
                ?>
            </div>
            <div>
                <h2><?php _e('Snabbstart', 'lus'); ?></h2>
                <p><?php _e('För att börja:', 'lus'); ?></p>
                <ol>
                    <li><?php _e('Lägg till texter under "Texter"', 'lus'); ?></li>
                    <li><?php _e('Skapa frågor för texterna', 'lus'); ?></li>
                    <li><?php _e('Tilldela texter till användare', 'lus'); ?></li>
                </ol>
                <p>Använd denna kortkod:</p>
                <pre>[lus_recorder]</pre>
            </div>
        </div>
    </div>

    <!-- Recordings Widget -->
    <div class="lus-dashboard-widgets">
        <div class="lus-widget lus-recordings-widget">
            <?php include PLUGIN_DIR . 'widgets/lus-recent-recordings.php'; ?>
        </div>

        <!-- Statistics Widget -->
        <div class="lus-widget lus-stats-widget">
            <?php include PLUGIN_DIR . 'widgets/lus-statistics.php'; ?>

            <?php if (get_option('lus_enable_tracking', true)): ?>
            <?php include PLUGIN_DIR . 'widgets/lus-admin-activity.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>