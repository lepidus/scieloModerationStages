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

use PKP\db\DAO;
use Illuminate\Support\Facades\DB;

class ModerationStageDAO extends DAO
{
    public function getSubmissionModerationStage($submissionId): ?int
    {
        $result = DB::table('submission_settings')
            ->where('submission_id', $submissionId)
            ->where('setting_name', 'currentModerationStage')
            ->select('setting_value')
            ->first();

        return !is_null($result) ? get_object_vars($result)['setting_value'] : null;
    }
}
