<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');

class SendModerationReminders extends ScheduledTask
{
    private $plugin;

    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $this->plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');

        $context = Application::get()->getRequest()->getContext();
        $responsiblesAssignments = $this->getResponsiblesAssignments($context->getId());
        $preModerationAssignments = $this->filterPreModerationAssignments($responsiblesAssignments);

        if (empty($preModerationAssignments)) {
            return true;
        }

        $usersWithOverduePreModeration = $this->getUsersWithOverduePreModeration($context->getId(), $preModerationAssignments);
        $mapModeratorsAndOverdueSubmissions = $this->mapModeratorsAndOverdueSubmissions($usersWithOverduePreModeration, $preModerationAssignments);

        foreach ($usersWithOverduePreModeration as $userId => $submissions) {
            $moderator = DAORegistry::getDAO('UserDAO')->getById($userId);
            $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder($context, $moderator, $submissions);

            $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
            $reminderEmail->send();
        }

        return true;
    }

    private function getResponsiblesAssignments(int $contextId)
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $contextUserGroups = $userGroupDao->getByContextId($contextId);

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = $userGroupDao->getSetting($userGroup->getId(), 'abbrev', 'en_US');

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

    private function filterPreModerationAssignments($responsiblesAssignments): array
    {
        $moderationStageDao = new ModerationStageDAO();
        $preModerationAssignments = [];

        foreach ($responsiblesAssignments as $assignment) {
            $submissionId = $assignment->getData('submissionId');
            $submissionModerationStage = $moderationStageDao->getSubmissionModerationStage($submissionId);

            if ($submissionModerationStage === SCIELO_MODERATION_STAGE_CONTENT) {
                $preModerationAssignments[] = $assignment;
            }
        }

        return $preModerationAssignments;
    }

    private function getUsersWithOverduePreModeration($contextId, $preModerationAssignments): array
    {
        $usersIds = [];
        $preModerationTimeLimit = $this->plugin->getSetting($contextId, 'preModerationTimeLimit');
        $moderationStageDao = new ModerationStageDAO();

        foreach ($preModerationAssignments as $assignment) {
            $submissionId = $assignment->getData('submissionId');
            $preModerationIsOverdue = $moderationStageDao->getPreModerationIsOverdue($submissionId, $preModerationTimeLimit);

            if ($preModerationIsOverdue) {
                $usersIds[] = $assignment->getData('userId');
            }
        }

        return $usersIds;
    }

    private function mapModeratorsAndOverdueSubmissions($moderators, $preModerationAssignments)
    {
        $moderatorsMap = [];
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');

        foreach ($moderators as $moderatorId) {
            foreach ($preModerationAssignments as $assignment) {
                if ($moderatorId != $assignment->getData('userId')) {
                    continue;
                }

                $submission = $submissionDao->getById($assignment->getData('submissionId'));

                if (isset($moderatorsMap[$moderatorId])) {
                    $moderatorsMap[$moderatorId] = array_merge($moderatorsMap[$moderatorId], [$submission]);
                } else {
                    $moderatorsMap[$moderatorId] = [$submission];
                }
            }
        }

        return $moderatorsMap;
    }
}
