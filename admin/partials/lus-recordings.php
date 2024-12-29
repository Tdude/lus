<?php
/**
 * lus-recordings.php
 * management page
 */

if (!defined('WPINC')) {
    die;
}

// Handle bulk assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_bulk_assign_nonce'])) {
    if (!wp_verify_nonce($_POST['lus_bulk_assign_nonce'], 'lus_bulk_assign_action')) {
        wp_die(__('Security check failed', 'lus'));
    }

    if (isset($_POST['recording_passages']) && is_array($_POST['recording_passages'])) {
        $success_count = 0;
        $error_count = 0;

        foreach ($_POST['recording_passages'] as $recording_id => $passage_id) {
            if ($passage_id > 0) {
                $result = $this->db->update_recording_passage($recording_id, $passage_id);
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $success_message = sprintf(
                _n(
                    '%d inspelning uppdaterad.',
                    '%d inspelningar uppdaterade.',
                    $success_count,
                    'lus'
                ),
                $success_count
            );
        }

        if ($error_count > 0) {
            $error_message = sprintf(
                _n(
                    '%d inspelning kunde inte uppdateras.',
                    '%d inspelningar kunde inte uppdateras.',
                    $error_count,
                    'lus'
                ),
                $error_count
            );
        }
    }
}

// Get pagination parameters
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get unassigned recordings
$unassigned_recordings = $this->db->get_orphaned_recordings($per_page, $offset);
$total_unassigned = $this->db->get_total_orphaned_recordings();
$total_pages = ceil($total_unassigned / $per_page);

// Get all passages for assignment dropdown
$passages = $this->db->get_all_passages(['orderby' => 'title', 'order' => 'ASC']);
?>

<div class="wrap">
    <h1><?php echo esc_html__('Hantera inspelningar', 'lus'); ?></h1>

    <?php if (isset($success_message)): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($unassigned_recordings): ?>
    <form method="post" id="lus-recordings-form">
        <?php wp_nonce_field('lus_bulk_assign_action', 'lus_bulk_assign_nonce'); ?>

        <!-- Bulk Actions -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="bulk_passage_id" id="bulk-passage-id">
                    <option value=""><?php _e('Välj text...', 'lus'); ?></option>
                    <?php foreach ($passages as $passage): ?>
                    <option value="<?php echo esc_attr($passage->id); ?>">
                        <?php echo esc_html($passage->title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button" id="bulk-assign">
                    <?php _e('Tilldela markerade', 'lus'); ?>
                </button>
            </div>
        </div>

        <!-- Recordings Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </td>
                    <th><?php _e('Användare', 'lus'); ?></th>
                    <th><?php _e('Inspelning', 'lus'); ?></th>
                    <th><?php _e('Längd', 'lus'); ?></th>
                    <th><?php _e('Inspelad', 'lus'); ?></th>
                    <th><?php _e('Tilldela till text', 'lus'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unassigned_recordings as $recording):
                        $file_path = LUS_Constants::UPLOAD_URL . $recording->audio_file_path;
                    ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="recording_ids[]" value="<?php echo esc_attr($recording->id); ?>">
                    </th>
                    <td><?php echo esc_html($recording->display_name); ?></td>
                    <td>
                        <?php if (file_exists(LUS_Constants::UPLOAD_URL . $recording->audio_file_path)): ?>
                        <audio controls style="max-width: 250px;">
                            <source src="<?php echo esc_url($file_path); ?>" type="audio/webm">
                            <?php _e('Din webbläsare stöder inte ljuduppspelning.', 'lus'); ?>
                        </audio>
                        <?php else: ?>
                        <span class="error"><?php _e('Ljudfil saknas', 'lus'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($recording->duration ? round($recording->duration, 1) . 's' : 'N/A'); ?>
                    </td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($recording->created_at))); ?>
                    </td>
                    <td>
                        <select name="recording_passages[<?php echo esc_attr($recording->id); ?>]"
                            class="recording-passage-select">
                            <option value=""><?php _e('Välj text...', 'lus'); ?></option>
                            <?php foreach ($passages as $passage): ?>
                            <option value="<?php echo esc_attr($passage->id); ?>">
                                <?php echo esc_html($passage->title); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Bottom Navigation -->
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <button type="submit" class="button button-primary">
                    <?php _e('Spara tilldelningar', 'lus'); ?>
                </button>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        ?>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <?php else: ?>
    <p><?php _e('Inga otilldelade inspelningar hittades.', 'lus'); ?></p>
    <?php endif; ?>
</div>