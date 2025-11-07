<?php
echo stylesheet_tag("og/reporting.css");
require_javascript("og/ReportingFunctions.js");
$genid = gen_id();
if (!isset($conditions)) $conditions = array();

$report_data = array_var($_SESSION, 'project_status_changes_report_data');
$report_key = 'project_status_changes_report'.Timeslots::instance()->getObjectTypeId();
$report = Reports::instance()->findOne(array("conditions" => "`code` = 'project_status_changes_report'"));
$report_title = $report ? $report->getObjectName() : 'Project Status Changes Report';
$report_id = $report ? $report->getId() : 0;
?>

<form style='height:100%;background-color:white' class="internalForm" action="<?php echo get_url('project_reports', 'project_status_changes_report') ?>" method="post" enctype="multipart/form-data">
<input type="hidden" id="<?php echo $genid?>report[report_object_type_id]" name="report[report_object_type_id]" value="<?php echo Timeslots::instance()->getObjectTypeId()?>"/>
<input type="hidden" id="<?php echo $genid?>report[report_id]" name="report[report_id]" value="<?php echo $report_id ?>"/>
<input type="hidden" id="<?php echo $genid?>report[report_title]" name="report[report_title]" value="<?php echo $report_title ?>"/>

<div class="coInputHeader">
	<div class="coInputHeaderUpperRow">
		<div class="coInputTitle"><?php echo $title; ?></div>
		<div class="coInputName" style="margin-top: 5px;"><?php echo $report ? $report->getColumnValue('description') : ''; ?></div>
	</div>
</div>
<div class="coInputSeparator"></div>
<div class="coInputMainBlock">
	<?php
	// Load dimension helper to render plain member selectors
	Env::useHelper('dimension');
	?>

	<div class="dataBlock">
		<label for="report[from_status]" class="bold"><?php echo lang("from phase") ?></label>
		<?php
			if ($contract_stage_dim_id > 0) {
				$from_phase_config = array(
					'dim_id' => $contract_stage_dim_id,
					'genid' => $genid,
					'hf_name' => 'report[from_status]',
					'selector_id' => $genid . '-from-phase-selector',
					'container' => $genid . '-from-phase-container',
					'selected_id' => array_var($report_data, 'from_status', 0),
					'onchange' => '',
					'value_zero_text' => lang('Any')
				);
				echo render_plain_member_selector($from_phase_config);
			} else {
				echo '<span style="color: red;">'.lang('dimension not configured').'</span>';
			}
		?>
	</div>
	<div class="dataBlock">
		<label for="report[to_status]" class="bold"><?php echo lang("to phase") ?></label>
		<?php
			if ($contract_stage_dim_id > 0) {
				$to_phase_config = array(
					'dim_id' => $contract_stage_dim_id,
					'genid' => $genid,
					'hf_name' => 'report[to_status]',
					'selector_id' => $genid . '-to-phase-selector',
					'container' => $genid . '-to-phase-container',
					'selected_id' => array_var($report_data, 'to_status', 0),
					'onchange' => ''
				);
				echo render_plain_member_selector($to_phase_config);
			} else {
				echo '<span style="color: red;">'.lang('dimension not configured').'</span>';
			}
		?>
	</div>
	<div class="dataBlock">
		<table>
			<tr>
				<td class="label" style="min-width: 150px; padding-right: 20px;"><span class="bold"><?php echo lang("date range") ?>:&nbsp;</span></td>
				<td align='left'><?php
					echo select_box('report[date_type]', array(
						option_tag(lang('today'), 1, array_var($report_data, "date_type") == 1 ? array('selected' => 'selected'):null),
						option_tag(lang('this week'), 2, array_var($report_data, "date_type") == 2 ? array('selected' => 'selected'):null),
						option_tag(lang('last week'), 3, array_var($report_data, "date_type") == 3 ? array('selected' => 'selected'):null),
						option_tag(lang('this month'), 4, array_var($report_data, "date_type") == 4 ? array('selected' => 'selected'):null),
						option_tag(lang('last month'), 5, array_var($report_data, "date_type") == 5 ? array('selected' => 'selected'):null),
						option_tag(lang('select dates...'), 6, array_var($report_data, "date_type") == 6 ? array('selected' => 'selected'):null)
					), array('onchange' => 'og.dateselectchange(this)', 'style' => 'min-width: 150px;'));
				?></td>
			</tr>
			<?php
				if (array_var($report_data, "date_type") == 6) {
					$style = "";
					$st = DateTimeValueLib::dateFromFormatAndString(user_config_option('date_format'), array_var($report_data, 'start_value'));
					$et = DateTimeValueLib::dateFromFormatAndString(user_config_option('date_format'), array_var($report_data, 'end_value'));
				} else {
					$style = 'display:none;';
					$st = DateTimeValueLib::now();
					$et = $st;
				}
			?>
			<tr class="dateTr" style="<?php echo $style ?>">
				<td class="label"><span class="bold"><?php echo lang("start date") ?>:&nbsp;</span></td>
				<td align='left'><?php echo pick_date_widget2('report[start_value]', $st, $genid);?></td>
			</tr>
			<tr class="dateTr" style="<?php echo $style ?>">
				<td class="label"><span class="bold"><?php echo lang("end date") ?>:&nbsp;</span></td>
				<td align='left'><?php echo pick_date_widget2('report[end_value]', $et, $genid);?></td>
			</tr>
		</table>
	</div>

	<?php echo submit_button(lang('generate report'),'s',array('style'=>'margin-top:0px;')) ?>
</div>

</form>