<?php
/**
 * File: config/admin-strings.php
 * Admin UI strings configuration
 *
 * @package    LUS
 * @subpackage LUS/includes/config
 */

return [
    // Common strings
    'loading' => __('Laddar...', 'lus'),
    'saving' => __('Sparar...', 'lus'),
    'deleting' => __('Raderar...', 'lus'),
    'error' => __('Ett fel uppstod', 'lus'),
    'success' => __('Åtgärden lyckades', 'lus'),
    'confirm' => __('Är du säker?', 'lus'),
    'cancel' => __('Avbryt', 'lus'),
    'save' => __('Spara', 'lus'),
    'edit' => __('Ändra', 'lus'),
    'delete' => __('Radera', 'lus'),
    'back' => __('Tillbaka', 'lus'),

    // Passages
    'passages' => [
        'title' => __('Texter', 'lus'),
        'add_new' => __('Lägg till ny text', 'lus'),
        'edit' => __('Ändra text', 'lus'),
        'update' => __('Uppdatera text', 'lus'),
        'save' => __('Spara text', 'lus'),
        'confirm_delete' => __('Är du säker på att du vill radera texten "%s"?', 'lus'),
        'no_passages' => __('Inga texter hittades.', 'lus'),
        'saved' => __('Texten har sparats.', 'lus'),
        'deleted' => __('Texten har raderats.', 'lus'),
        'fields' => [
            'title' => __('Titel', 'lus'),
            'content' => __('Textinnehåll', 'lus'),
            'time_limit' => __('Tidsgräns (sekunder)', 'lus'),
            'difficulty_level' => __('Svårighetsgrad', 'lus'),
        ],
    ],

    // Recordings
    'recordings' => [
        'title' => __('Inspelningar', 'lus'),
        'view' => __('Visa inspelning', 'lus'),
        'delete' => __('Radera inspelning', 'lus'),
        'confirm_delete' => __('Är du säker på att du vill radera denna inspelning?', 'lus'),
        'no_recordings' => __('Inga inspelningar hittades.', 'lus'),
        'deleted' => __('Inspelningen har raderats.', 'lus'),
        'browser_not_supported' => __('Din webbläsare stöder inte ljuduppspelning.', 'lus'),
        'file_missing' => __('Ljudfil saknas', 'lus'),
    ],

    // Questions
    'questions' => [
        'title' => __('Frågor', 'lus'),
        'add_new' => __('Lägg till ny fråga', 'lus'),
        'edit' => __('Ändra fråga', 'lus'),
        'update' => __('Uppdatera fråga', 'lus'),
        'save' => __('Spara fråga', 'lus'),
        'confirm_delete' => __('Är du säker på att du vill radera denna fråga?', 'lus'),
        'no_questions' => __('Inga frågor hittades.', 'lus'),
        'saved' => __('Frågan har sparats.', 'lus'),
        'deleted' => __('Frågan har raderats.', 'lus'),
        'fields' => [
            'question_text' => __('Fråga', 'lus'),
            'correct_answer' => __('Korrekt svar', 'lus'),
            'weight' => __('Svårighetsgrad', 'lus'),
        ],
    ],

    // Assignments
    'assignments' => [
        'title' => __('Tilldelningar', 'lus'),
        'add_new' => __('Lägg till ny tilldelning', 'lus'),
        'edit' => __('Ändra tilldelning', 'lus'),
        'save' => __('Spara tilldelning', 'lus'),
        'confirm_delete' => __('Är du säker på att du vill radera denna tilldelning?', 'lus'),
        'no_assignments' => __('Inga tilldelningar hittades.', 'lus'),
        'saved' => __('Tilldelningen har sparats.', 'lus'),
        'deleted' => __('Tilldelningen har raderats.', 'lus'),
        'fields' => [
            'user' => __('Användare', 'lus'),
            'passage' => __('Text', 'lus'),
            'due_date' => __('Slutdatum', 'lus'),
        ],
    ],

    // Results
    'results' => [
        'title' => __('Resultat', 'lus'),
        'export_csv' => __('Exportera CSV', 'lus'),
        'export_pdf' => __('Exportera PDF', 'lus'),
        'no_results' => __('Inga resultat hittades.', 'lus'),
        'stats' => [
            'total_recordings' => __('Antal inspelningar', 'lus'),
            'unique_students' => __('Antal elever', 'lus'),
            'average_score' => __('Medelresultat', 'lus'),
            'questions_answered' => __('Besvarade frågor', 'lus'),
        ],
    ],

    // Assessment
    'assessment' => [
        'title' => __('Bedömning', 'lus'),
        'save' => __('Spara bedömning', 'lus'),
        'saved' => __('Bedömningen har sparats.', 'lus'),
        'no_responses' => __('Inga svar att bedöma.', 'lus'),
        'fields' => [
            'score' => __('Poäng', 'lus'),
            'feedback' => __('Återkoppling', 'lus'),
        ],
    ],

    // Errors
    'errors' => [
        'permission_denied' => __('Behörighet saknas', 'lus'),
        'invalid_nonce' => __('Säkerhetskontroll misslyckades', 'lus'),
        'missing_data' => __('Data saknas', 'lus'),
        'db_error' => __('Databasfel', 'lus'),
        'file_upload' => __('Fel vid filuppladdning', 'lus'),
        'invalid_file_type' => __('Ogiltig filtyp', 'lus'),
        'file_too_large' => __('Filen är för stor', 'lus'),
    ],
];