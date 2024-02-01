<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use APP\submission\Submission;
use PKP\core\Core;

class ModerationStage
{
    public const SCIELO_MODERATION_STAGE_FORMAT = 1;
    public const SCIELO_MODERATION_STAGE_CONTENT = 2;
    public const SCIELO_MODERATION_STAGE_AREA = 3;

    public $submission;

    public function __construct($submission)
    {
        $this->submission = $submission;
    }

    private function getModerationStageName($stage)
    {
        $stageMap = [
            self::SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
            self::SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
            self::SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
        ];

        return __($stageMap[$stage]);
    }

    private function getNextModerationStage($stage)
    {
        $nextStageMap = [
            self::SCIELO_MODERATION_STAGE_FORMAT => self::SCIELO_MODERATION_STAGE_CONTENT,
            self::SCIELO_MODERATION_STAGE_CONTENT => self::SCIELO_MODERATION_STAGE_AREA,
        ];

        return $nextStageMap[$stage];
    }

    private function getModerationStageEntryConfig($stage)
    {
        $stageMap = [
            self::SCIELO_MODERATION_STAGE_FORMAT => 'formatStageEntryDate',
            self::SCIELO_MODERATION_STAGE_CONTENT => 'contentStageEntryDate',
            self::SCIELO_MODERATION_STAGE_AREA => 'areaStageEntryDate',
        ];

        return $stageMap[$stage];
    }

    public function getStageEntryDates(): array
    {
        $stageEntryDates = array();

        if ($this->submission->getData('formatStageEntryDate')) {
            $stageEntryDates['formatStageEntryDate'] = substr($this->submission->getData('formatStageEntryDate'), 0, 10);
        }

        if ($this->submission->getData('contentStageEntryDate')) {
            $stageEntryDates['contentStageEntryDate'] = substr($this->submission->getData('contentStageEntryDate'), 0, 10);
        }

        if ($this->submission->getData('areaStageEntryDate')) {
            $stageEntryDates['areaStageEntryDate'] = substr($this->submission->getData('areaStageEntryDate'), 0, 10);
        }

        return $stageEntryDates;
    }

    public function getCurrentStageName(): string
    {
        $currentStage = $this->submission->getData('currentModerationStage');

        return $this->getModerationStageName($currentStage);
    }

    public function getNextStageName(): string
    {
        $currentStage = $this->submission->getData('currentModerationStage');
        $nextStage = $this->getNextModerationStage($currentStage);

        return $this->getModerationStageName($nextStage);
    }

    public function canAdvanceStage(): bool
    {
        if ($this->submission->getData('status') == Submission::STATUS_DECLINED || $this->submission->getData('status') == Submission::STATUS_PUBLISHED) {
            return false;
        }

        $currentStage = $this->submission->getData('currentModerationStage');
        if (is_null($currentStage) || $currentStage == self::SCIELO_MODERATION_STAGE_AREA) {
            return false;
        }

        return true;
    }

    public function submissionStageExists(): bool
    {
        return !is_null($this->submission->getData('currentModerationStage'));
    }

    public function setToFirstStage()
    {
        $this->setSubmissionToStage(self::SCIELO_MODERATION_STAGE_FORMAT);
    }

    public function sendNextStage()
    {
        $currentStage = $this->submission->getData('currentModerationStage');
        $nextStage = $this->getNextModerationStage($currentStage);

        $this->setSubmissionToStage($nextStage);
    }

    private function setSubmissionToStage($stage)
    {
        $moderationStageEntryConfig = $this->getModerationStageEntryConfig($stage);

        $this->submission->setData('currentModerationStage', $stage);
        $this->submission->setData('lastModerationStageChange', Core::getCurrentDate());
        $this->submission->setData($moderationStageEntryConfig, Core::getCurrentDate());
    }
}
