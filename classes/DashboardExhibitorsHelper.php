<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use APP\facades\Repo;
use PKP\db\DAORegistry;

class DashboardExhibitorsHelper
{
    public const RESPONSIBLES_GROUP_ABBREV = 'resp';
    public const AREA_MODERATORS_GROUP_ABBREV = 'am';

    // get submission moderation stage

    public function getResponsiblesText(int $submissionId): string
    {
        $responsibleUsers = $this->getAssignedUsersByGroupAbbrev($submissionId, self::RESPONSIBLES_GROUP_ABBREV);

        $responsiblesText = "";

        if (count($responsibleUsers) > 1) {
            unset($responsibleUsers['scielo-brasil']);
        }

        if (count($responsibleUsers) == 1) {
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsible', ['responsible' =>  array_pop($responsibleUsers)]);
        } elseif (count($responsibleUsers) > 1) {
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsibles', ['responsibles' => implode(", ", $responsibleUsers)]);
        }

        return $responsiblesText;
    }

    public function getAreaModeratorsText(int $submissionId)
    {
        $areaModeratorUsers = $this->getAssignedUsersByGroupAbbrev($submissionId, self::AREA_MODERATORS_GROUP_ABBREV);

        $areaModeratorsText = "";
        if (count($areaModeratorUsers) == 1) {
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerator', ['areaModerator' => array_pop($areaModeratorUsers)]);
        } elseif (count($areaModeratorUsers) > 1) {
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerators', ['areaModerators' => implode(", ", $areaModeratorUsers)]);
        }

        return $areaModeratorsText;
    }

    protected function getAssignedUsersByGroupAbbrev(int $submissionId, string $abbrev): array
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
        $assignedUsers = [];

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en'));

            if ($userGroupAbbrev == $abbrev) {
                $user = Repo::user()->get($stageAssignment->getUserId(), false);
                $assignedUsers[$user->getData('username')] = $user->getFullName();
            }
        }

        return $assignedUsers;
    }
}
