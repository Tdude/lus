<?php
/**
 * Admin view for managing text assignments
 */

if (!defined('WPINC')) {
    die;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_assignment_nonce'])) {
    if (!wp_verify_nonce($_POST['lus_assignment_nonce'], 'lus_assignment_action')) {
        wp_die(__('Security check failed', 'lus'));
    }

    $user_id = intval($_POST['user_id']);
    $passage_id = intval($_POST['passage_id']);
    $due_date = !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;

    $result = $this->db->assign_passage_to_user(
        $passage_id,
        $user_id,
        get_current_user_id(),
        $due_date
    );

    if ($result) {
        $success_message = __('Texten blev tilldelad eleven.', 'lus');
    } else {
        $error_message = __('Kunde inte tilldela text.', 'lus');
    }
}

// Get all users and passages for the form
$users = get_users(['role__not_in' => ['administrator']]);
$passages = $this->db->get_all_passages();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($error_message)): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Assignment Form -->
    <div class="lus-assignment-form-container">
        <h2><?php _e('Tilldela text', 'lus'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('lus_assignment_action', 'lus_assignment_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="user_id"><?php _e('Användare', 'lus'); ?></label>
                    </th>
                    <td>
                        <select name="user_id" id="user_id" required>
                            <option value=""><?php _e('Välj användare', 'lus'); ?></option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="passage_id"><?php _e('Text', 'lus'); ?></label>
                    </th>
                    <td>
                        <select name="passage_id" id="passage_id" required>
                            <option value=""><?php _e('Välj text', 'lus'); ?></option>
                            <?php foreach ($passages as $passage): ?>
                            <option value="<?php echo esc_attr($passage->id); ?>">
                                <?php echo esc_html($passage->title); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="due_date"><?php _e('Slutdatum (valfritt)', 'lus'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="due_date" name="due_date">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php _e('Tilldela text', 'lus'); ?>">
            </p>
        </form>
    </div>

    <!-- List of current assignments -->
    <div class="lus-assignments-list">
        <h2><?php _e('Aktuella tilldelningar', 'lus'); ?></h2>
        <?php
        // Get all assignments with user and passage details
        $assignments = $this->db->get_all_assignments();
        ?>

        <?php if ($assignments): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Användare', 'lus'); ?></th>
                    <th><?php _e('Text', 'lus'); ?></th>
                    <th><?php _e('Tilldelad', 'lus'); ?></th>
                    <th><?php _e('Slutdatum', 'lus'); ?></th>
                    <th><?php _e('Status', 'lus'); ?></th>
                    <th><?php _e('Aktivitet', 'lus'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td><?php echo esc_html($assignment->user_name); ?></td>
                    <td><?php echo esc_html($assignment->passage_title); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($assignment->assigned_at))); ?>
                    </td>
                    <td>
                        <?php
                        echo $assignment->due_date
                            ? esc_html(date_i18n(get_option('date_format'), strtotime($assignment->due_date)))
                            : __('Inget slutdatum', 'lus');
                        ?>
                    </td>
                    <td>
                        <?php
                        $status_labels = [
                            'pending' => __('Väntar', 'lus'),
                            'completed' => __('Slutförd', 'lus'),
                            'overdue' => __('Försenad', 'lus')
                        ];
                        echo esc_html($status_labels[$assignment->status] ?? $assignment->status);
                        ?>
                    </td>
                    <td>
                        <button class="button lus-delete-assignment" data-id="<?php echo esc_attr($assignment->id); ?>"
                            data-user="<?php echo esc_attr($assignment->user_name); ?>"
                            data-passage="<?php echo esc_attr($assignment->passage_title); ?>">
                            <?php _e('Ta bort', 'lus'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Inga aktiva tilldelningar hittades.', 'lus'); ?></p>
        <?php endif; ?>
    </div>
</div>