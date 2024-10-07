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

    public function getResponsiblesUserGroup(int $contextId)
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

        return $responsiblesUserGroup;
    }

    public function mapUsersAndSubmissions($users, $assignments)
    {
        $usersMap = [];
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');

        foreach ($users as $userId) {
            foreach ($assignments as $assignment) {
                if ($userId != $assignment['userId']) {
                    continue;
                }

                $submission = $submissionDao->getById($assignment['submissionId']);

                if (isset($usersMap[$userId])) {
                    $usersMap[$userId] = array_merge($usersMap[$userId], [$submission]);
                } else {
                    $usersMap[$userId] = [$submission];
                }
            }
        }

        return $usersMap;
    }
}
