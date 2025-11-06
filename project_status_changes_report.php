<?php

Env::useHelper('view_functions', 'project_reports');
Env::useHelper('helper_view_functions', 'project_reports');

$report_data = array_var($_SESSION, 'project_status_changes_report_data');
$report_data['is_pdf_export'] = isset($is_pdf_export) ? $is_pdf_export : false;

$from_date_str = format_date($report_data['start_date']);
$to_date_str = format_date($report_data['end_date']);

build_title_and_date_for_report($title);

echo '<div class="project-report-info" style="margin-bottom: 20px;">' . lang('showing data from') . ' <b>' . $from_date_str . '</b> ' . lang('to') . ' <b>' . $to_date_str . '</b></div>';
if ($from_status_name && $to_status_name) {
    echo '<div class="project-report-info" style="margin-bottom: 20px;">' . lang('from') . ' <b>' . $from_status_name . '</b> ' . lang('to') . ' <b>' . $to_status_name . '</b></div>';
} elseif ($to_status_name) {
    echo '<div class="project-report-info" style="margin-bottom: 20px;"><b>' . $to_status_name . '</b></div>';
}
echo '<div class="clear"></div>';

/**
 * Format a cell value based on its type
 * @param mixed $value The raw value
 * @param string $type The column type (date, currency, string)
 * @param string $currency_sym The currency symbol
 * @return array Array with 'value' and 'align_class'
 */
function format_report_cell_value($value, $type, $currency_sym) {
    $formatted = array(
        'value' => $value,
        'align_class' => 'left'
    );

    if ($type == 'date' && $value) {
        $formatted['value'] = format_date(DateTimeValueLib::dateFromFormatAndString('Y-m-d H:i:s', $value));
    } elseif ($type == 'currency') {
        $formatted['value'] = format_money_amount($value, $currency_sym);
        $formatted['align_class'] = 'right';
    }

    return $formatted;
}

/**
 * Render the project status changes report table
 * @param array $projects Hierarchically organized projects
 * @param array $columns Column definitions
 * @param array $totals Total counts
 * @param array $report_data Report configuration
 */
function render_project_status_changes_report($projects, $columns, $totals, $report_data) {
    $is_pdf_export = array_var($report_data, 'is_pdf_export', false);
    $table_zoom = $is_pdf_export ? 'zoom: 0.8;' : '';
    $currency_sym = config_option('currency_code');

    // Start table
    echo '<table class="budgetReport" style="width: 100%;'.$table_zoom.'">';

    // Render header
    render_table_header($columns);

    // Render data rows
    echo '<tbody>';
    if (empty($projects)) {
        echo '<tr><td colspan="'.count($columns).'" class="center">'.lang('no results found').'</td></tr>';
    } else {
        render_project_rows($projects, $columns, $currency_sym);
    }
    echo '</tbody>';

    // Render totals
    if (!empty($projects)) {
        render_table_totals($columns, $totals);
    }

    // Close table
    echo '</table>';
}

/**
 * Render table header row
 * @param array $columns Column definitions
 */
function render_table_header($columns) {
    echo '<thead><tr>';
    foreach($columns as $col) {
        if (!is_array($col) || !isset($col['type']) || !isset($col['name'])) {
            continue;
        }
        $align_class = $col['type'] == 'currency' ? 'right' : 'left';
        echo '<th class="'.$align_class.' bold header_1 min-80">'.$col['name'].'</th>';
    }
    echo '</tr></thead>';
}

/**
 * Render project data rows with hierarchy
 * @param array $projects Projects to render
 * @param array $columns Column definitions
 * @param string $currency_sym Currency symbol
 */
function render_project_rows($projects, $columns, $currency_sym) {
    foreach($projects as $project) {
        $is_child = array_var($project, 'is_child', false);
        $is_context_parent = array_var($project, 'is_context_parent', false);
        $row_class = $is_child ? 'childRow' : 'parentRow';

        // Add special styling for context parents (shown for reference)
        if ($is_context_parent) {
            $row_class .= ' contextParent';
        }

        echo '<tr class="'.$row_class.'">';

        $is_first_column = true;
        foreach($columns as $col) {
            if (!is_array($col) || !isset($col['field']) || !isset($col['type'])) {
                continue;
            }

            $field = $col['field'];
            $value = array_var($project, $field, '');

            // For context parents, show empty change_date as '--'
            if ($is_context_parent && $field == 'change_date' && empty($value)) {
                $cell_content = '--';
                $align_class = 'left';
            } else {
                // Format the value
                $formatted = format_report_cell_value($value, $col['type'], $currency_sym);
                $cell_content = $formatted['value'];
                $align_class = $formatted['align_class'];
            }

            // Add indentation to first column for child projects
            if ($is_first_column && $is_child) {
                $cell_content = '<span style="margin-left: 20px; display: inline-block;">└─ ' . $cell_content . '</span>';
            }

            echo '<td class="'.$align_class.'">'.$cell_content.'</td>';
            $is_first_column = false;
        }
        echo '</tr>';
    }
}

/**
 * Render totals row
 * @param array $columns Column definitions
 * @param array $totals Totals data
 */
function render_table_totals($columns, $totals) {
    echo '<tbody><tr class="titles">';
    $total_count = array_var($totals, 'total_count', 0);

    foreach($columns as $col) {
        if (!is_array($col) || !isset($col['field']) || !isset($col['type'])) {
            continue;
        }
        $field = $col['field'];
        if ($field == 'change_date') {
            echo '<td class="left header_0"><strong>'.lang('total count').': '.$total_count.'</strong></td>';
        } else {
            echo '<td class="left header_0"></td>';
        }
    }
    echo '</tr></tbody>';
}

render_project_status_changes_report($projects, $columns, $totals, $report_data);