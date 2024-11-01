<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use APP\facades\Repo;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;

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
        $contextUserGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en'));

            if ($userGroupAbbrev === $abbrev) {
                return $userGroup;
            }
        }

        return null;
    }

    public function mapUsersAndSubmissions($users, $assignments)
    {
        $usersMap = [];
        $submissionRepo = Repo::submission();

        foreach ($users as $userId) {
            foreach ($assignments as $assignment) {
                if ($userId != $assignment['userId']) {
                    continue;
                }

                $submission = $submissionRepo->get($assignment['submissionId']);

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
