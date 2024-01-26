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

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

class ModerationStageDAO extends DAO
{
    public function getSubmissionModerationStage($submissionId): ?int
    {
        $result = Capsule::table('submission_settings')
            ->where('submission_id', $submissionId)
            ->where('setting_name', 'currentModerationStage')
            ->select('setting_value')
            ->first();

        return !is_null($result) ? get_object_vars($result)['setting_value'] : null;
    }
}
