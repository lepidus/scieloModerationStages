<?php

namespace APP\plugins\generic\scieloModerationStages\tests\helpers;

require_once __DIR__ . '/../../controllers/ScieloModerationStagesHandler.php';

class TestableModerationStagesHandler extends \ScieloModerationStagesHandler
{
    public bool $isAuthor = true;

    protected function currentUserIsAuthor(): bool
    {
        return $this->isAuthor;
    }

    protected function getSubmissionModerationStage($submissionId): array
    {
        return ['submissionId' => $submissionId, 'ModerationStage' => 'Some stage'];
    }

    protected function getEditorialExhibitData($submissionId): array
    {
        return ['Responsibles' => 'Responsible: Jane Doe'];
    }
}
