<?php

/**
 * Helper function to get all status member IDs including children
 * @param int $status_id The parent status member ID
 * @return array Array of status IDs (parent + all children)
 */
function get_status_ids_including_children($status_id) {
    if (!$status_id) {
        return array();
    }

    $status_member = Members::getMemberById($status_id);
    if (!$status_member instanceof Member) {
        return array();
    }

    $status_ids = array($status_id); // Always include the parent ID itself
    $child_ids = $status_member->getAllChildrenIds();

    if (!empty($child_ids)) {
        $status_ids = array_merge($status_ids, $child_ids);
    }

    return $status_ids;
}

/**
 * Fetch parent project current data
 * @param int $parent_id The parent project member ID
 * @return array|null Parent project data or null if not found
 */
function fetch_parent_project_data($parent_id) {
    if (!$parent_id) {
        return null;
    }

    $proj_proj_manager_assoc = DimensionMemberAssociations::getAssociationByCode('project_project_manager');
    if (!$proj_proj_manager_assoc instanceof DimensionMemberAssociation) {
        return null;
    }
    $proj_manager_association_id = $proj_proj_manager_assoc->getId();

    $contract_stage_assoc = DimensionMemberAssociations::getAssociationByCode('project_project_phase');
    if (!$contract_stage_assoc instanceof DimensionMemberAssociation) {
        return null;
    }
    $association_id = $contract_stage_assoc->getId();

    // Fetch current parent data
    $sql = "
        SELECT
            m.id AS member_id,
            m.parent_member_id AS parent_id,
            m.display_name AS project_name,
            pm_member.display_name AS project_manager_name,
            mt.estimated_overall_total_price AS estimated_price,
            current_status.display_name AS current_phase_name,
            current_status.id AS current_stage_id
        FROM
            " . TABLE_PREFIX . "members m
        LEFT JOIN
            " . TABLE_PREFIX . "member_property_members AS pm_prop ON pm_prop.association_id = " . DB::escape($proj_manager_association_id) . "
            AND pm_prop.member_id = m.id
        LEFT JOIN
            " . TABLE_PREFIX . "members AS pm_member ON pm_member.id = pm_prop.property_member_id
        LEFT JOIN
            " . TABLE_PREFIX . "member_totals mt ON m.id = mt.member_id
        LEFT JOIN
            " . TABLE_PREFIX . "member_property_members AS status_prop ON status_prop.association_id = " . DB::escape($association_id) . "
            AND status_prop.member_id = m.id
        LEFT JOIN
            " . TABLE_PREFIX . "members AS current_status ON current_status.id = status_prop.property_member_id
        WHERE
            m.id = " . DB::escape($parent_id) . "
        LIMIT 1
    ";

    $row = DB::executeOne($sql);

    if (!$row) {
        return null;
    }

    $member_id = $row['member_id'];
    $estimated_price = floatval(array_var($row, 'estimated_price', 0));

    // Check if this project is a parent (has children) and subtract ALL children's prices
    $parent_member = Members::getMemberById($member_id);
    if ($parent_member instanceof Member) {
        $all_children_ids = $parent_member->getAllChildrenIds();
        if (!empty($all_children_ids)) {
            // Get the estimated prices for all children
            $children_sql = "
                SELECT SUM(mt.estimated_overall_total_price) as children_total
                FROM " . TABLE_PREFIX . "member_totals mt
                WHERE mt.member_id IN (" . implode(',', $all_children_ids) . ")
            ";
            $children_result = DB::executeOne($children_sql);
            if ($children_result && !empty($children_result['children_total'])) {
                $children_total = floatval($children_result['children_total']);
                // Subtract children's total from parent's price to avoid duplication
                $estimated_price = max(0, $estimated_price - $children_total);
            }
        }
    }

    return array(
        'member_id' => $member_id,
        'parent_id' => $row['parent_id'],
        'change_date' => '',  // No change date for parents shown for context
        'project_name' => $row['project_name'],
        'project_manager' => !empty($row['project_manager_name']) ? $row['project_manager_name'] : '--',
        'previous_stage' => '--',  // No previous stage for context parents
        'current_stage' => !empty($row['current_phase_name']) ? $row['current_phase_name'] : '--',
        'estimated_price' => $estimated_price,
        'is_child' => false,
        'is_context_parent' => true  // Flag to indicate this parent is shown for context
    );
}

/**
 * Organize projects into hierarchical structure
 * @param array $projects Flat array of projects with parent_id
 * @return array Hierarchically organized projects (parents with children nested)
 */
function organize_projects_hierarchy($projects) {
    $organized = array();
    $projects_by_id = array();
    $children_by_parent_id = array();
    $processed_ids = array();

    // Index all projects by ID and group children
    foreach ($projects as $project) {
        $project_id = $project['member_id'];
        $parent_id = $project['parent_id'];

        $projects_by_id[$project_id] = $project;

        if ($parent_id) {
            if (!isset($children_by_parent_id[$parent_id])) {
                $children_by_parent_id[$parent_id] = array();
            }
            $children_by_parent_id[$parent_id][] = $project_id;
        }
    }

    // Build hierarchical structure: parents first, then their children
    foreach ($projects as $project) {
        $project_id = $project['member_id'];
        $parent_id = $project['parent_id'];

        // Skip if already processed
        if (isset($processed_ids[$project_id])) {
            continue;
        }

        // If this project is a child, ensure parent is displayed first
        if ($parent_id) {
            // Check if parent is already in organized list or processed
            if (!isset($processed_ids[$parent_id])) {
                // Parent not yet displayed
                if (isset($projects_by_id[$parent_id])) {
                    // Parent is in results, add it
                    $organized[] = $projects_by_id[$parent_id];
                } else {
                    // Parent is NOT in results, fetch it for context
                    $parent_data = fetch_parent_project_data($parent_id);
                    if ($parent_data) {
                        $organized[] = $parent_data;
                    }
                }
                $processed_ids[$parent_id] = true;
            }

            // Now add the child
            $project['is_child'] = true;
            $organized[] = $project;
            $processed_ids[$project_id] = true;

            // Also add any other children of the same parent
            if (isset($children_by_parent_id[$parent_id])) {
                foreach ($children_by_parent_id[$parent_id] as $sibling_id) {
                    if ($sibling_id != $project_id && !isset($processed_ids[$sibling_id])) {
                        $sibling = $projects_by_id[$sibling_id];
                        $sibling['is_child'] = true;
                        $organized[] = $sibling;
                        $processed_ids[$sibling_id] = true;
                    }
                }
            }
        } else {
            // Root-level project (no parent)
            $organized[] = $project;
            $processed_ids[$project_id] = true;

            // Add its children immediately after
            if (isset($children_by_parent_id[$project_id])) {
                foreach ($children_by_parent_id[$project_id] as $child_id) {
                    if (!isset($processed_ids[$child_id])) {
                        $child = $projects_by_id[$child_id];
                        $child['is_child'] = true;
                        $organized[] = $child;
                        $processed_ids[$child_id] = true;
                    }
                }
            }
        }
    }

    return $organized;
}

function get_main_project_status_changes_report_data($report_data) {
    $project_ot = ObjectTypes::instance()->findOne(array('conditions' => "`name` = 'project'"));
    $project_ot_id = $project_ot->getId();

    $result_data = array(
        'projects' => array(),
        'totals' => array(
            'total_count' => 0,
            'total_estimated_price' => 0
        )
    );

    // Get filter parameters
    $start_date = array_var($report_data, 'start_date');
    $end_date = array_var($report_data, 'end_date');
    $from_status = array_var($report_data, 'from_status');
    $to_status = array_var($report_data, 'to_status');

    // Get project phase dimension and association (contract stages)
    $contract_stage_dim = Dimensions::findByCode('project_phases');
    if (!$contract_stage_dim instanceof Dimension) {
        return $result_data;
    }

    $contract_stage_assoc = DimensionMemberAssociations::getAssociationByCode('project_project_phase');
    if (!$contract_stage_assoc instanceof DimensionMemberAssociation) {
        return $result_data;
    }

    $association_id = $contract_stage_assoc->getId();

    $sql_st_date = $start_date->toMySQL();
    $sql_end_date = $end_date->toMySQL();

    // Get all status IDs including children for to_status
    $to_status_ids = get_status_ids_including_children($to_status);

    // from status is empty (0 or null), it means 'any'
    $from_status_condition = "1=1"; // Means 'ANY' previous status

    if ($from_status) {
        $from_status_ids = get_status_ids_including_children($from_status);

        if (!empty($from_status_ids)) {
            // Only include transitions that came FROM the specified status
            // Note: If we want to include new projects (NULL -> Status),
            // the from_status filter should not be set (meaning "from ANY status")
            $from_status_condition = " sc.previous_status_id IN (" . implode(',', $from_status_ids) . ")";
        } else {
            $from_status_condition = "1=0"; // No rows will match
        }
    }
    if (empty($to_status_ids)) {
        return $result_data;
    }

    $proj_proj_manager_assoc = DimensionMemberAssociations::getAssociationByCode('project_project_manager');
    if (!$proj_proj_manager_assoc instanceof DimensionMemberAssociation) {
        return $result_data;
    }
    $proj_manager_association_id = $proj_proj_manager_assoc->getId();

    $sql = "
        WITH StatusChangesWithPrevious AS (
            SELECT
                mh.member_id,
                mh.value AS current_status_id,
                mh.created_on,
                LAG(mh.value) OVER (
                    PARTITION BY mh.member_id, mh.property
                    ORDER BY mh.created_on ASC
                ) AS previous_status_id
            FROM
                " . TABLE_PREFIX . "member_history mh
            WHERE
                mh.property = " . DB::escape($association_id) . "
                AND mh.created_on <= " . DB::escape($sql_end_date) . "
        ),

        MatchingTransitions AS (
            SELECT
                sc.member_id,
                sc.current_status_id,
                sc.created_on,
                sc.previous_status_id
            FROM
                StatusChangesWithPrevious sc
            WHERE
                sc.created_on BETWEEN " . DB::escape($sql_st_date) . " AND " . DB::escape($sql_end_date) . "
                AND sc.current_status_id IN (" . implode(',', $to_status_ids) . ")
                AND " . $from_status_condition . "
        ),

        RankedTransitions AS (
            SELECT
                mt.member_id,
                mt.current_status_id,
                mt.created_on,
                mt.previous_status_id,
                ROW_NUMBER() OVER (
                    PARTITION BY mt.member_id
                    ORDER BY mt.created_on DESC
                ) as rn
            FROM
                MatchingTransitions mt
        ),

        AbsoluteLastStatus AS (
            SELECT
                sc.member_id,
                sc.current_status_id,
                ROW_NUMBER() OVER (
                    PARTITION BY sc.member_id
                    ORDER BY sc.created_on DESC
                ) as rn
            FROM
                StatusChangesWithPrevious sc
        )

        SELECT
            m.id AS member_id,
            m.parent_member_id AS parent_id,
            m.display_name AS project_name,
            rt.created_on AS change_date,

            rt.current_status_id AS current_stage_id,
            status_name_current.display_name AS current_phase_name,

            rt.previous_status_id AS previous_stage_id,
            status_name_prev.display_name AS previous_phase_name,
            mt.estimated_overall_total_price AS estimated_price,
            pm_member.display_name AS project_manager_name
        FROM
            RankedTransitions rt

        JOIN
            AbsoluteLastStatus als ON rt.member_id = als.member_id

        JOIN
            " . TABLE_PREFIX . "members m ON rt.member_id = m.id
        LEFT JOIN
            " . TABLE_PREFIX . "member_property_members AS pm_prop ON pm_prop.association_id = " . DB::escape($proj_manager_association_id) . "
            AND pm_prop.member_id = m.id
        LEFT JOIN
            " . TABLE_PREFIX . "members AS pm_member ON pm_member.id = pm_prop.property_member_id
        LEFT JOIN
            " . TABLE_PREFIX . "member_totals mt ON m.id = mt.member_id
        JOIN
            " . TABLE_PREFIX . "members status_name_current ON rt.current_status_id = status_name_current.id
        LEFT JOIN
            " . TABLE_PREFIX . "members status_name_prev ON rt.previous_status_id = status_name_prev.id
        WHERE
            rt.rn = 1  -- Last transition
            AND als.rn = 1 -- Absolute final status
            AND als.current_status_id IN (" . implode(',', $to_status_ids) . ")

        GROUP BY
            m.id,
            m.parent_member_id,
            m.display_name,
            rt.created_on,
            rt.current_status_id,
            status_name_current.display_name,
            rt.previous_status_id,
            status_name_prev.display_name,
            pm_member.display_name
        ORDER BY
            change_date, project_name DESC;
    ";

    $rows = DB::executeAll($sql);

    if (empty($rows)) {
        return $result_data;
    }

    // Process each project and build flat array with hierarchy info
    $projects = array();
    foreach ($rows as $row) {
        $member_id = $row['member_id'];
        $estimated_price = floatval(array_var($row, 'estimated_price', 0));

        // Check if this project is a parent (has children) and subtract ALL children's prices
        $parent_member = Members::getMemberById($member_id);
        if ($parent_member instanceof Member) {
            $all_children_ids = $parent_member->getAllChildrenIds();
            if (!empty($all_children_ids)) {
                // Get the estimated prices for all children
                $children_sql = "
                    SELECT SUM(mt.estimated_overall_total_price) as children_total
                    FROM " . TABLE_PREFIX . "member_totals mt
                    WHERE mt.member_id IN (" . implode(',', $all_children_ids) . ")
                ";
                $children_result = DB::executeOne($children_sql);
                if ($children_result && !empty($children_result['children_total'])) {
                    $children_total = floatval($children_result['children_total']);
                    // Subtract children's total from parent's price to avoid duplication
                    $estimated_price = max(0, $estimated_price - $children_total);
                }
            }
        }

        $project_data = array(
            'member_id' => $member_id,
            'parent_id' => $row['parent_id'],
            'change_date' => $row['change_date'],
            'project_name' => $row['project_name'],
            'project_manager' => !empty($row['project_manager_name']) ? $row['project_manager_name'] : '--',
            'previous_stage' => !empty($row['previous_phase_name']) ? $row['previous_phase_name'] : '--',
            'current_stage' => !empty($row['current_phase_name']) ? $row['current_phase_name'] : '--',
            'estimated_price' => $estimated_price,
            'is_child' => false
        );

        $projects[] = $project_data;
        $result_data['totals']['total_count']++;
        $result_data['totals']['total_estimated_price'] += $estimated_price;
    }

    // Organize projects hierarchically
    $result_data['projects'] = organize_projects_hierarchy($projects);

    return $result_data;
}

function get_project_status_changes_columns($report_data) {
    return array(
        array(
            'name' => lang('date'),
            'field' => 'change_date',
            'type' => 'date'
        ),
        array(
            'name' => lang('project name'),
            'field' => 'project_name',
            'type' => 'string'
        ),
        array(
            'name' => lang('project manager'),
            'field' => 'project_manager',
            'type' => 'string'
        ),
        array(
            'name' => lang('previous contract stage'),
            'field' => 'previous_stage',
            'type' => 'string'
        ),
        array(
            'name' => lang('current contract stage'),
            'field' => 'current_stage',
            'type' => 'string'
        ),
        array(
            'name' => lang('estimated price'),
            'field' => 'estimated_price',
            'type' => 'currency'
        )
    );
}