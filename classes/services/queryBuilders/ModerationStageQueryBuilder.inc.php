<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class ModerationStageQueryBuilder extends \APP\Services\QueryBuilders\SubmissionQueryBuilder
{
    protected $moderationStages = [];

    public function filterByModerationStages($moderationStages)
    {
        $this->moderationStages = is_array($moderationStages) ? $moderationStages : [$moderationStages];
        return $this;
    }

    public function appGet($q)
    {
        if (!empty($this->moderationStages)) {
            $q->leftJoin('submission_settings as sub_s', 's.submission_id', '=', 'sub_s.submission_id')
                ->where('sub_s.setting_name', 'currentModerationStage')
                ->whereIn('sub_s.setting_value', $this->moderationStages);
        }

        return $q;
    }

}
