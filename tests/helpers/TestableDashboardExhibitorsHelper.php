<?php

namespace APP\plugins\generic\scieloModerationStages\tests\helpers;

use APP\plugins\generic\scieloModerationStages\classes\DashboardExhibitorsHelper;

class TestableDashboardExhibitorsHelper extends DashboardExhibitorsHelper
{
    public array $usersByGroup = [];

    protected function getAssignedUsersByGroupAbbrev(int $submissionId, string $abbrev): array
    {
        return $this->usersByGroup[$abbrev];
    }
}
