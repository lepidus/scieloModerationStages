<?php

import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');

class ModerationReminderHelper
{
    private $moderationStageDao;

    public function __construct()
    {
        $this->moderationStageDao = new ModerationStageDAO();
    }

    public function setModerationStageDao($moderationStageDao)
    {
        $this->moderationStageDao = $moderationStageDao;
    }

    public function getResponsiblesAssignments(int $contextId): array
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $contextUserGroups = $userGroupDao->getByContextId($contextId)->toArray();

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = strtolower($userGroupDao->getSetting($userGroup->getId(), 'abbrev', 'en_US'));

            if ($userGroupAbbrev === 'resp') {
                $responsiblesUserGroup = $userGroup;
                break;
            }
        }

        if (!$responsiblesUserGroup) {
            return [];
        }

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $responsiblesAssignments = $stageAssignmentDao->getByUserGroupId($responsiblesUserGroup->getId(), $contextId);

        return $responsiblesAssignments->toArray();
    }

    public function filterPreModerationAssignments(array $assignments): array
    {
        $preModerationAssignments = [];

        foreach ($assignments as $assignment) {
            $submissionId = $assignment->getData('submissionId');
            $submissionModerationStage = $this->moderationStageDao->getSubmissionModerationStage($submissionId);

            if ($submissionModerationStage === SCIELO_MODERATION_STAGE_CONTENT) {
                $preModerationAssignments[] = $assignment;
            }
        }

        return $preModerationAssignments;
    }
}
