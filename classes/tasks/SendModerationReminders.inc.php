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
        $locale = $context->getPrimaryLocale();
        $this->plugin->addLocaleData($locale);

        $preModerationTimeLimit = $this->plugin->getSetting($context->getId(), 'preModerationTimeLimit');
        $this->sendResponsiblesReminders($context, $preModerationTimeLimit, $locale);

        return true;
    }

    private function sendResponsiblesReminders($context, $preModerationTimeLimit, $locale)
    {
        $moderationReminderHelper = new ModerationReminderHelper();
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($context->getId());

        $moderationStageDao = new ModerationStageDAO();
        $responsibleAssignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $responsiblesUserGroup->getId(),
            SCIELO_MODERATION_STAGE_CONTENT
        );

        if (empty($responsibleAssignments)) {
            return;
        }

        $usersWithOverduePreModeration = $this->getUsersWithOverduePreModeration($context->getId(), $responsibleAssignments);
        $mapModeratorsAndOverdueSubmissions = $moderationReminderHelper->mapUsersAndSubmissions($usersWithOverduePreModeration, $responsibleAssignments);

        foreach ($mapModeratorsAndOverdueSubmissions as $userId => $submissions) {
            $moderator = DAORegistry::getDAO('UserDAO')->getById($userId);
            $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder(
                $context,
                $moderator,
                $submissions,
                $locale,
                REMINDER_TYPE_PRE_MODERATION,
                $preModerationTimeLimit
            );

            $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
            $reminderEmail->send();
        }
    }

    private function getUsersWithOverduePreModeration($contextId, $assignments): array
    {
        $usersIds = [];
        $preModerationTimeLimit = $this->plugin->getSetting($contextId, 'preModerationTimeLimit');
        $moderationStageDao = new ModerationStageDAO();

        foreach ($assignments as $assignment) {
            $submissionId = $assignment['submissionId'];
            $preModerationIsOverdue = $moderationStageDao->getPreModerationIsOverdue($submissionId, $preModerationTimeLimit);

            if ($preModerationIsOverdue and !isset($usersIds[$assignment['userId']])) {
                $usersIds[$assignment['userId']] = $assignment['userId'];
            }
        }

        return $usersIds;
    }
}
