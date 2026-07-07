<?php

namespace APP\plugins\generic\scieloModerationStages\tests\helpers;

require_once __DIR__ . '/../../controllers/ScieloModerationStagesHandler.php';

class TestableModerationStagesHandler extends \ScieloModerationStagesHandler
{
    public bool $isAuthor = true;
    public int $scopedSubmissionId = 1;

    protected function currentUserIsAuthor(): bool
    {
        return $this->isAuthor;
    }

    public function getSubmission()
    {
        $submissionId = $this->scopedSubmissionId;
        return new class ($submissionId) {
            public function __construct(private int $submissionId)
            {
            }

            public function getId(): int
            {
                return $this->submissionId;
            }
        };
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
