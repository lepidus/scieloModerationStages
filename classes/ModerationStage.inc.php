<?php

define('SCIELO_MODERATION_STAGE_FORMAT', 1);
define('SCIELO_MODERATION_STAGE_CONTENT', 2);
define('SCIELO_MODERATION_STAGE_AREA', 3);

class ModerationStage {
    public $submission;

    public function __construct($submission) {
        $this->submission = $submission;
    }

    private function getModerationStageName($stage) {
        $stageMap = [
            SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
            SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
            SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
        ];

        return __($stageMap[$stage]);
    }

    private function getNextModerationStage($stage) {
		$nextStageMap = [
			SCIELO_MODERATION_STAGE_FORMAT => SCIELO_MODERATION_STAGE_CONTENT,
			SCIELO_MODERATION_STAGE_CONTENT => SCIELO_MODERATION_STAGE_AREA,
		];

		return $nextStageMap[$stage];
	}

    private function getModerationStageEntryConfig($stage) {
        $stageMap = [
            SCIELO_MODERATION_STAGE_FORMAT => 'formatStageEntryDate',
            SCIELO_MODERATION_STAGE_CONTENT => 'contentStageEntryDate',
            SCIELO_MODERATION_STAGE_AREA => 'areaStageEntryDate',
        ];

        return $stageMap[$stage];
    }

    public function getCurrentStageName(): string {
        $currentStage = $this->submission->getData('currentModerationStage');

        return $this->getModerationStageName($currentStage);
    }

    public function getNextStageName(): string {
        $currentStage = $this->submission->getData('currentModerationStage');
        $nextStage = $this->getNextModerationStage($currentStage);

        return $this->getModerationStageName($nextStage);
    }

    public function canAdvanceStage(): bool {
        if($this->submission->getData('status') == STATUS_DECLINED || $this->submission->getData('status') == STATUS_PUBLISHED)
            return false;

        $currentStage = $this->submission->getData('currentModerationStage');
		if(is_null($currentStage) || $currentStage == SCIELO_MODERATION_STAGE_AREA)
            return false;

        return true;
    }

    public function setToFirstStage() {
        $this->setSubmissionToStage(SCIELO_MODERATION_STAGE_FORMAT);
    }

    public function sendNextStage() {
        $currentStage = $this->submission->getData('currentModerationStage');
        $nextStage = $this->getNextModerationStage($currentStage);

        $this->setSubmissionToStage($nextStage);
	}

    private function setSubmissionToStage($stage) {
        $moderationStageEntryConfig = $this->getModerationStageEntryConfig($stage);
        
        $this->submission->setData('currentModerationStage', $stage);
        $this->submission->setData('lastModerationStageChange', Core::getCurrentDate());
        $this->submission->setData($moderationStageEntryConfig, Core::getCurrentDate());
    }
}