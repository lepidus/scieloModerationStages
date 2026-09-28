<?php

namespace APP\plugins\generic\scieloModerationStages\tests\helpers;

use APP\plugins\generic\scieloModerationStages\classes\DashboardExhibitorsHelper;

class TestableDashboardExhibitorsHelper extends DashboardExhibitorsHelper
{
    public array $usersByGroup = [];
    public array $lastAssignmentDateByGroup = [];
    public array $submissionFinalDate = [];

    protected function getAssignedUsersByGroupAbbrev(int $submissionId, string $abbrev): array
    {
        return $this->usersByGroup[$abbrev];
    }

    protected function getLastAssignmentDateByGroupAbbrev(int $submissionId, string $abbrev): string
    {
        return $this->lastAssignmentDateByGroup[$abbrev] ?? '';
    }

    protected function getSubmissionFinalDateParams($submission): array
    {
        return $this->submissionFinalDate;
    }
}
