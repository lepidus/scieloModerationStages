<?php

/**
 * @file plugins/reports/scieloModerationStages/classes/ModerationStageDAO.inc.php
 *
 * @class ModerationStageDAO
 * @ingroup plugins_generic_scieloModerationStages
 *
 * Operations for retrieving data to help identify submissions' moderation stage
 */

namespace APP\plugins\generic\scieloModerationStages\classes;

use DateTime;
use PKP\db\DAO;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;

class ModerationStageDAO extends DAO
{
    public function getSubmissionModerationStage(int $submissionId): ?int
    {
        $result = DB::table('submission_settings')
            ->where('submission_id', $submissionId)
            ->where('setting_name', 'currentModerationStage')
            ->select('setting_value')
            ->first();

        return !is_null($result) ? get_object_vars($result)['setting_value'] : null;
    }

    /**
     * Counts active (queued) submissions grouped by their current moderation stage.
     * When $userId is provided, only submissions assigned to that user are counted.
     *
     * @return array<int,int> map of moderation stage => submissions count
     */
    public function countSubmissionsByModerationStage(int $contextId, ?int $userId = null): array
    {
        $query = DB::table('submissions AS s')
            ->join('submission_settings AS ss', function ($join) {
                $join->on('s.submission_id', '=', 'ss.submission_id')
                    ->where('ss.setting_name', '=', 'currentModerationStage');
            })
            ->where('s.context_id', '=', $contextId)
            ->where('s.status', '=', Submission::STATUS_QUEUED);

        if (!is_null($userId)) {
            $query->whereIn('s.submission_id', function ($subQuery) use ($userId) {
                $subQuery->select('submission_id')
                    ->from('stage_assignments')
                    ->where('user_id', '=', $userId);
            });
        }

        $rows = $query->groupBy('ss.setting_value')
            ->selectRaw('ss.setting_value AS stage, COUNT(DISTINCT s.submission_id) AS submissions_count')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->stage] = (int) $row->submissions_count;
        }

        return $counts;
    }

    public function getPreModerationIsOverdue(int $submissionId, int $timeLimit): bool
    {
        $result = DB::table('submissions')
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
        $submissionsSubQuery = DB::table('submissions AS s')
            ->leftJoin('publications AS p', 's.current_publication_id', '=', 'p.publication_id')
            ->where('s.status', Submission::STATUS_QUEUED)
            ->where('p.version', 1)
            ->select('s.submission_id');

        $query = DB::table('stage_assignments AS sa')
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
        $result = DB::table('stage_assignments AS sa')
            ->where('user_id', $userId)
            ->where('submission_id', $submissionId)
            ->select('date_assigned')
            ->first();

        return $result ? get_object_vars($result)['date_assigned'] : null;
    }
}
