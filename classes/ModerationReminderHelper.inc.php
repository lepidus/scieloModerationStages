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

    public function getResponsibleAssignments($responsiblesUserGroup, $contextId): array
    {
        if (!$responsiblesUserGroup) {
            return [];
        }

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $responsiblesAssignments = $stageAssignmentDao->getByUserGroupId($responsiblesUserGroup->getId(), $contextId);

        return $responsiblesAssignments->toArray();
    }

    public function filterAssignmentsOfSubmissionsOnPreModeration(array $assignments): array
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

    public function getUsersFromAssignments(array $assignments): array
    {
        $users = [];
        $userDao = DAORegistry::getDAO('UserDAO');

        foreach ($assignments as $assignment) {
            $user = $userDao->getById($assignment->getUserId());

            if ($user and !isset($users[$user->getId()])) {
                $users[$user->getId()] = $user;
            }
        }

        return $users;
    }

    private function mapUsersAndSubmissions($users, $assignments)
    {
        $usersMap = [];
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');

        foreach ($users as $userId) {
            foreach ($assignments as $assignment) {
                if ($userId != $assignment->getData('userId')) {
                    continue;
                }

                $submission = $submissionDao->getById($assignment->getData('submissionId'));

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
