<?php

import('plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler');

class TestableModerationStagesHandler extends ScieloModerationStagesHandler
{
    public $userIsEditorial = false;
    public $accessibleWorkflowStages = [];
    public $userRoles = [];
    public $scopedSubmissionId = 1;
    public $submissionWasLoaded = false;

    public function getSubmission()
    {
        $this->submissionWasLoaded = true;
        $submission = new Submission();
        $submission->setId($this->scopedSubmissionId);
        return $submission;
    }

    protected function currentUserIsEditorial(): bool
    {
        return $this->userIsEditorial;
    }

    protected function getAccessibleWorkflowStages($submission): array
    {
        return $this->accessibleWorkflowStages;
    }

    protected function currentUserRoles(): array
    {
        return $this->userRoles;
    }

    protected function getSubmissionModerationStage($submissionId)
    {
        return ['submissionId' => $submissionId, 'ModerationStage' => 'Some stage'];
    }

    protected function getEditorialExhibitData($submissionId): array
    {
        return ['Responsibles' => 'Responsible: Jane Doe'];
    }
}
