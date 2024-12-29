<?php
/** partials/widgets/lus-recent-recordings.php
 * Recent recordings widget for dashboard
 */

if (!defined('WPINC')) {
    die;
}

// Pagination variables
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count (with caching)
$total_count = wp_cache_get('lus_recordings_count');
if (false === $total_count) {
    $total_count = $this->db->get_recordings_count();
    wp_cache_set('lus_recordings_count', $total_count, '', 300); // Cache for 5 minutes
}


$total_pages = ceil($total_count / $per_page);

// Build query conditions
$where_conditions = ['1=1'];
$where_args = [];

if ($passage_filter) {
    $where_conditions[] = 'r.passage_id = %d';
    $where_args[] = $passage_filter;
}

// Get recordings for current page
$recent_recordings = $this->db->get_recordings([
    'conditions' => $where_conditions,
    'args' => $where_args,
    'limit' => $per_page,
    'offset' => $offset,
    'with_user' => true,
    'with_assessments' => true
]);
?>

<h2><?php _e('Senaste inspelningar', 'lus'); ?></h2>

<?php if ($passage_filter): ?>
<p>
    <a href="<?php echo esc_url(admin_url('admin.php?page=lus')); ?>" class="button">
        <?php _e('Visa alla inspelningar', 'lus'); ?>
    </a>
</p>
<?php endif; ?>

<?php if ($recent_recordings): ?>
<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th><?php _e('Användare', 'lus'); ?></th>
            <th><?php _e('Inspelning', 'lus'); ?></th>
            <th><?php _e('Längd', 'lus'); ?></th>
            <th><?php _e('Datum', 'lus'); ?></th>
            <th><?php _e('Bedömningar', 'lus'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recent_recordings as $recording):
                $file_path = LUS_Constants::UPLOAD_URL . $recording->audio_file_path;
                $check_path = LUS_Constants::UPLOAD_DIR . $recording->audio_file_path;
            ?>
        <tr>
            <td><?php echo esc_html($recording->display_name); ?></td>
            <td>
                <?php if (file_exists($check_path)): ?>
                <audio controls style="max-width: 250px;">
                    <source src="<?php echo esc_url($file_path); ?>" type="audio/webm">
                    <?php _e('Din webbläsare stöder inte ljuduppspelning.', 'lus'); ?>
                </audio>
                <?php else: ?>
                <span class="error"><?php _e('Ljudfil saknas', 'lus'); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($recording->duration ? round($recording->duration, 1) . 's' : 'N/A'); ?></td>
            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($recording->created_at))); ?>
            </td>
            <td>
                <?php
                        echo sprintf(
                            _n('%d bedömning', '%d bedömningar', $recording->assessment_count, 'lus'),
                            $recording->assessment_count
                        );
                        if ($recording->assessment_count > 0) {
                            echo ' (' . round($recording->avg_assessment_score, 1) . ')';
                        }
                        ?>
                <div class="button-container">
                    <button class="button add-assessment" data-recording-id="<?php echo esc_attr($recording->id); ?>">
                        <?php _e('LUSa', 'lus'); ?>
                    </button>
                    <button class="button delete-recording" data-recording-id="<?php echo esc_attr($recording->id); ?>">
                        <?php _e('Radera', 'lus'); ?>
                    </button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include LUS_Constants::PLUGIN_DIR . 'admin/partials/lus-pagination.php'; ?>
<?php else: ?>
<p><?php _e('Inga inspelningar registrerade än.', 'lus'); ?></p>
<?php endif; ?>

<!-- Assessment Modal -->
<?php include LUS_Constants::PLUGIN_DIR . 'admin/partials/widgets/lus-assessment-modal.php'; ?>