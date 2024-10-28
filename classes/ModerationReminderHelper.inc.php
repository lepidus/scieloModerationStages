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
        return $this->getUserGroupByAbbrev($contextId, 'resp');
    }

    public function getAreaModeratorsUserGroup(int $contextId)
    {
        return $this->getUserGroupByAbbrev($contextId, 'am');
    }

    private function getUserGroupByAbbrev(int $contextId, string $abbrev)
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $contextUserGroups = $userGroupDao->getByContextId($contextId)->toArray();

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = strtolower($userGroupDao->getSetting($userGroup->getId(), 'abbrev', 'en_US'));

            if ($userGroupAbbrev === $abbrev) {
                return $userGroup;
            }
        }

        return null;
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
