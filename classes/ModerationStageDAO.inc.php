<?php

/**
 * @file plugins/reports/scieloModerationStages/classes/ModerationStageDAO.inc.php
 *
 * @class ModerationStageDAO
 * @ingroup plugins_generic_scieloModerationStages
 *
 * Operations for retrieving data to help identify submissions' moderation stage
 */

import('lib.pkp.classes.db.DAO');
import('classes.submission.Submission');

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

class ModerationStageDAO extends DAO
{
    public function getSubmissionModerationStage(int $submissionId): ?int
    {
        $result = Capsule::table('submission_settings')
            ->where('submission_id', $submissionId)
            ->where('setting_name', 'currentModerationStage')
            ->select('setting_value')
            ->first();

        return !is_null($result) ? get_object_vars($result)['setting_value'] : null;
    }

    public function getPreModerationIsOverdue(int $submissionId, int $timeLimit): bool
    {
        $result = Capsule::table('submissions')
            ->where('submission_id', '=', $submissionId)
            ->select('date_submitted')
            ->first();
        $dateSubmitted = get_object_vars($result)['date_submitted'];
        $dateSubmitted = new DateTime($dateSubmitted);

        $limitDaysAgo = new DateTime();
        $limitDaysAgo->modify("-$timeLimit days");

        return $dateSubmitted < $limitDaysAgo;
    }

    public function getAssignmentsByUserGroupAndModerationStage(int $userGroupId, int $moderationStage, ?int $userId = null): array
    {
        $submissionsSubQuery = Capsule::table('submissions AS s')
            ->leftJoin('publications AS p', 's.current_publication_id', '=', 'p.publication_id')
            ->where('s.status', STATUS_QUEUED)
            ->where('p.version', 1)
            ->select('s.submission_id');

        $query = Capsule::table('stage_assignments AS sa')
            ->leftJoin('submission_settings AS sub_s', 'sa.submission_id', '=', 'sub_s.submission_id')
            ->whereIn('sub_s.submission_id', $submissionsSubQuery)
            ->where('sub_s.setting_name', 'currentModerationStage')
            ->where('sub_s.setting_value', '=', $moderationStage)
            ->where('sa.user_group_id', '=', $userGroupId)
            ->select('sa.user_id', 'sa.submission_id', 'sa.date_assigned');

        if ($userId) {
            $query = $query->where('sa.user_id', '=', $userId);
        }

        $result = $query->get();
        $assignments = [];

        foreach ($result as $row) {
            $row = get_object_vars($row);
            $assignments[] = [
                'userId' => $row['user_id'],
                'submissionId' => $row['submission_id'],
                'dateAssigned' => $row['date_assigned']
            ];
        }

        return $assignments;
    }

    public function getDateOfUserAssignment(int $userId, int $submissionId): ?string
    {
        $result = Capsule::table('stage_assignments AS sa')
            ->where('user_id', $userId)
            ->where('submission_id', $submissionId)
            ->select('date_assigned')
            ->first();

        return $result ? get_object_vars($result)['date_assigned'] : null;
    }
}
