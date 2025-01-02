<?php
/**
 * File: partials/lus-settings.php
 */
?>
<div class="lus-settings">
    <h2><?php esc_html_e('LUS Plugin inställningar', LUS_Constants::TEXT_DOMAIN); ?></h2>
    <form method="post" action="options.php">
        <?php
                settings_fields('lus_settings_group'); // Use a settings group to handle options
                do_settings_sections('lus_settings_group');

                // AI vs Manual Weight
                $weight = get_option('lus_ai_weight', LUS_Constants::DEFAULT_WEIGHT);
                ?>
        <label for="lus_ai_weight"><?php esc_html_e('AI mot manuell viktning', LUS_Constants::TEXT_DOMAIN); ?></label>
        <input type="number" id="lus_ai_weight" name="lus_ai_weight" min="0.0" max="1.0" step="0.1"
            value="<?php echo esc_attr($weight); ?>">
        <p class="description">
            <?php esc_html_e('Vikta mellan AI-driven bedömning och manuell (0.0 för helt manuell, 1.0 för bara AI).', LUS_Constants::TEXT_DOMAIN); ?>
        </p>

        <?php
                // Confidence Threshold
                $confidence = get_option('lus_confidence_threshold', LUS_Constants::MIN_CONFIDENCE_THRESHOLD);
                ?>
        <label for="lus_confidence_threshold"><?php esc_html_e('Konfidens', LUS_Constants::TEXT_DOMAIN); ?></label>
        <input type="number" id="lus_confidence_threshold" name="lus_confidence_threshold" min="0.0" max="1.0"
            step="0.01" value="<?php echo esc_attr($confidence); ?>">
        <p class="description">
            <?php esc_html_e('Minimum nivå (Konfidenströskel) för att en AI-bedömning på inläst ljudfil ska anses giltig. Högre är bättre. Vid 0 får du göra allt manuellt.', LUS_Constants::TEXT_DOMAIN); ?>
        </p>

        <?php
                // Difficulty Level
                $difficulty = get_option('lus_difficulty_level', LUS_Constants::DEFAULT_DIFFICULTY_LEVEL);
                ?>
        <label for="lus_difficulty_level"><?php esc_html_e('Svårighetsnivå', LUS_Constants::TEXT_DOMAIN); ?></label>
        <input type="number" id="lus_difficulty_level" name="lus_difficulty_level" min="1"
            max="<?php echo esc_attr(LUS_Constants::MAX_DIFFICULTY_LEVEL); ?>"
            value="<?php echo esc_attr($difficulty); ?>">
        <p class="description">
            <?php esc_html_e('Sätt en förinställd svårighetsnivå på texter att läsa in (1-20).', LUS_Constants::TEXT_DOMAIN); ?>
        </p>

        <?php submit_button(); ?>
    </form>
</div>