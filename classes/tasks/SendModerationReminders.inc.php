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
        $responsibleAssignments = $this->getResponsiblesAssignments($context->getId());
        $overduePreModerationAssignments = $this->filterOverduePreModerationAssignments($context->getId(), $responsibleAssignments);

        if (empty($overduePreModerationAssignments)) {
            return true;
        }

        $usersWithOverduePreModeration = $this->getUsersWithOverduePreModeration($overduePreModerationAssignments);

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

    private function filterOverduePreModerationAssignments($contextId, $assignments): array
    {
        $overdueAssignments = [];
        $preModerationTimeLimit = $this->plugin->getSetting($contextId, 'preModerationTimeLimit');
        $moderationStageDao = new ModerationStageDAO();

        foreach ($assignments as $assignment) {
            $submissionId = $assignment->getData('submissionId');
            $submissionModerationStage = $moderationStageDao->getSubmissionModerationStage($submissionId);
            $preModerationIsOverdue = $moderationStageDao->getPreModerationIsOverdue($submissionId, $preModerationTimeLimit);

            if ($submissionModerationStage === SCIELO_MODERATION_STAGE_CONTENT and $preModerationIsOverdue) {
                $overdueAssignments[] = $assignment;
            }
        }

        return $overdueAssignments;
    }

    private function getUsersWithOverduePreModeration($overduePreModerationAssignments)
    {
        $usersMap = [];
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');

        foreach ($overduePreModerationAssignments as $assignment) {
            $userId = $assignment->getData('userId');
            $submission = $submissionDao->getById($assignment->getData('submissionId'));

            if (isset($usersMap[$userId])) {
                $usersMap[$userId] = array_merge($usersMap[$userId], [$submission]);
            } else {
                $usersMap[$userId] = [$submission];
            }
        }

        return $usersMap;
    }
}
