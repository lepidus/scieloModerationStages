<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderEmailBuilder');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

class SendModerationReminders extends ScheduledTask
{
    private $plugin;

    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $this->plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');

        $context = Application::get()->getRequest()->getContext();
        $moderationReminderHelper = new ModerationReminderHelper();
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($context->getId());
        $responsibleAssignments = $moderationReminderHelper->getResponsibleAssignments($responsiblesUserGroup, $context->getId());
        $preModerationAssignments = $moderationReminderHelper->filterAssignmentsOfSubmissionsOnPreModeration($responsibleAssignments);

        if (empty($preModerationAssignments)) {
            return true;
        }

        $usersWithOverduePreModeration = $this->getUsersWithOverduePreModeration($context->getId(), $preModerationAssignments);
        $mapModeratorsAndOverdueSubmissions = $moderationReminderHelper->mapUsersAndSubmissions($usersWithOverduePreModeration, $preModerationAssignments);

        foreach ($mapModeratorsAndOverdueSubmissions as $userId => $submissions) {
            $moderator = DAORegistry::getDAO('UserDAO')->getById($userId);
            $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder($context, $moderator, $submissions);

            $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
            $reminderEmail->send();
        }

        return true;
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
}
