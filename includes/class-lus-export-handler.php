<?php
/**
 * File: includes/class-lus-export-handler.php
 */

class LUS_Export_Handler {
    private static $instance = null;
    private $db;

    public function __construct($db) {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = $this;
        $this->db = $db;
    }

    /**
     * Export results based on format and filters
     */
    public function export_results($format, $filters = []) {
        $data = $this->gather_export_data($filters);

        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data);
            case 'json':
                return $this->export_to_json($data);
            case 'pdf':
                return $this->export_to_pdf($data);
            default:
                throw new Exception(__('Okänt exportformat', 'lus'));
        }
    }

    /**
     * Gather all relevant data for export
     */
    private function gather_export_data($filters) {
        return [
            'overall_stats' => $this->db->get_overall_statistics($filters),
            'passage_stats' => $this->db->get_passage_statistics($filters),
            'question_stats' => $this->db->get_question_statistics($filters),
            'student_progress' => $this->db->get_student_progress($filters),
            'export_date' => current_time('mysql'),
            'filters' => $filters
        ];
    }

    /**
     * Export to CSV format
     */
    private function export_to_csv($data) {
        $output = fopen('php://temp', 'r+');

        // Overall Stats
        fputcsv($output, [__('Övergripande statistik', 'lus')]);
        fputcsv($output, [
            __('Antal inspelningar', 'lus'),
            __('Antal elever', 'lus'),
            __('Medelresultat', 'lus')
        ]);
        fputcsv($output, [
            $data['overall_stats']['total_recordings'],
            $data['overall_stats']['unique_students'],
            $data['overall_stats']['avg_normalized_score']
        ]);
        fputcsv($output, []); // Empty line

        // Passage Stats
        fputcsv($output, [__('Statistik per text', 'lus')]);
        fputcsv($output, [
            __('Text', 'lus'),
            __('Inspelningar', 'lus'),
            __('Medelresultat', 'lus'),
            __('Korrekta svar', 'lus'),
            __('Medeltid', 'lus')
        ]);
        foreach ($data['passage_stats'] as $stat) {
            fputcsv($output, [
                $stat['title'],
                $stat['recording_count'],
                $stat['avg_score'],
                $stat['correct_answer_rate'],
                $stat['avg_duration']
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return [
            'content' => $csv,
            'filename' => 'lus-export-' . date('Y-m-d') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }

    /**
     * Export to JSON format
     */
    private function export_to_json($data) {
        return [
            'content' => json_encode($data, JSON_PRETTY_PRINT),
            'filename' => 'lus-export-' . date('Y-m-d') . '.json',
            'mime_type' => 'application/json'
        ];
    }

    /**
     * Export to PDF format (requires TCPDF)
     */
    private function export_to_pdf($data) {
        if (!class_exists('TCPDF')) {
            throw new Exception(__('PDF export kräver TCPDF biblioteket', 'lus'));
        }

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('LUS');
        $pdf->SetAuthor('LUS Admin');
        $pdf->SetTitle('LUS Statistik Export');

        // Add a page
        $pdf->AddPage();

        // Add content
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, __('LUS Statistik Export', 'lus'), '', 0, 'L', true, 0, false, false, 0);
        $pdf->Ln();

        // Add charts as images if available
        if (isset($data['charts'])) {
            foreach ($data['charts'] as $chart) {
                $pdf->Image($chart['data'], '', '', 160, 80);
                $pdf->Ln();
            }
        }

        return [
            'content' => $pdf->Output('', 'S'),
            'filename' => 'lus-export-' . date('Y-m-d') . '.pdf',
            'mime_type' => 'application/pdf'
        ];
    }
}