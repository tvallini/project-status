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

function render_project_status_changes_report($projects, $columns, $totals, $report_data) {
    $is_pdf_export = array_var($report_data, 'is_pdf_export', false);
    $table_zoom = $is_pdf_export ? 'zoom: 0.8;' : '';
    $currency_sym = config_option('currency_code');

    // Start table
    echo '<table class="budgetReport" style="width: 100%;'.$table_zoom.'">';

    // Render header
    echo '<thead><tr>';
    foreach($columns as $col) {
        if (!is_array($col) || !isset($col['type']) || !isset($col['name'])) {
            continue;
        }
        $align_class = $col['type'] == 'currency' ? 'right' : 'left';
        echo '<th class="'.$align_class.' bold header_1 min-80">'.$col['name'].'</th>';
    }
    echo '</tr></thead>';

    // Render data rows
    echo '<tbody>';
    if (empty($projects)) {
        echo '<tr><td colspan="'.count($columns).'" class="center">'.lang('no results found').'</td></tr>';
    } else {
        foreach($projects as $project) {
            echo '<tr class="parentRow">';
            foreach($columns as $col) {
                if (!is_array($col) || !isset($col['field']) || !isset($col['type'])) {
                    continue;
                }
                $field = $col['field'];
                $value = array_var($project, $field, '');

                if ($col['type'] == 'date' && $value) {
                    $value = format_date(DateTimeValueLib::dateFromFormatAndString('Y-m-d H:i:s', $value));
                    $align_class = 'left';
                } elseif ($col['type'] == 'currency') {
                    $value = format_money_amount($value, $currency_sym);
                    $align_class = 'right';
                } else {
                    $align_class = 'left';
                }

                echo '<td class="'.$align_class.'">'.$value.'</td>';
            }
            echo '</tr>';
        }
    }
    echo '</tbody>';

    // Render totals
    if (!empty($projects)) {
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

    // Close table
    echo '</table>';
}

render_project_status_changes_report($projects, $columns, $totals, $report_data);