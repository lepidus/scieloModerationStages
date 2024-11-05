<?php

namespace APP\plugins\generic\scieloModerationStages\classes\tasks;

use DateTime;
use PKP\scheduledTask\ScheduledTask;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderHelper;

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

        $areaModerationTimeLimit = $this->plugin->getSetting($context->getId(), 'areaModerationTimeLimit');
        $this->sendAreaModeratorsReminders($context, $areaModerationTimeLimit, $locale);

        return true;
    }

    private function sendResponsiblesReminders($context, $preModerationTimeLimit, $locale)
    {
        $moderationReminderHelper = new ModerationReminderHelper();
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($context->getId());

        $moderationStageDao = new ModerationStageDAO();
        $responsibleAssignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $responsiblesUserGroup->getId(),
            ModerationStage::SCIELO_MODERATION_STAGE_CONTENT
        );

        if (empty($responsibleAssignments)) {
            return;
        }

        $usersWithOverduePreModeration = $this->getUsersWithOverduePreModeration($responsibleAssignments, $preModerationTimeLimit);
        $mapModeratorsAndOverdueSubmissions = $moderationReminderHelper->mapUsersAndSubmissions($usersWithOverduePreModeration, $responsibleAssignments);

        foreach ($mapModeratorsAndOverdueSubmissions as $userId => $submissions) {
            $moderator = Repo::user()->get($userId);
            $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder(
                $context,
                $moderator,
                $submissions,
                $locale,
                ModerationReminderEmailBuilder::REMINDER_TYPE_PRE_MODERATION,
                $preModerationTimeLimit
            );

            $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
            Mail::send($reminderEmail);
        }
    }

    private function sendAreaModeratorsReminders($context, $areaModerationTimeLimit, $locale)
    {
        $moderationReminderHelper = new ModerationReminderHelper();
        $areaModeratorsUserGroup = $moderationReminderHelper->getAreaModeratorsUserGroup($context->getId());

        $moderationStageDao = new ModerationStageDAO();
        $areaModeratorAssignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $areaModeratorsUserGroup->getId(),
            ModerationStage::SCIELO_MODERATION_STAGE_AREA
        );

        if (empty($areaModeratorAssignments)) {
            return;
        }

        $usersWithOverdueAreaModeration = $this->getUsersWithOverdueAreaModeration($areaModeratorAssignments, $areaModerationTimeLimit);
        $mapModeratorsAndOverdueSubmissions = $moderationReminderHelper->mapUsersAndSubmissions($usersWithOverdueAreaModeration, $areaModeratorAssignments);

        foreach ($mapModeratorsAndOverdueSubmissions as $userId => $submissions) {
            $moderator = Repo::user()->get($userId);
            $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder(
                $context,
                $moderator,
                $submissions,
                $locale,
                ModerationReminderEmailBuilder::REMINDER_TYPE_AREA_MODERATION,
                $areaModerationTimeLimit
            );

            $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
            Mail::send($reminderEmail);
        }
    }

    private function getUsersWithOverduePreModeration($assignments, $preModerationTimeLimit): array
    {
        $usersIds = [];
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

    private function getUsersWithOverdueAreaModeration($assignments, $areaModerationTimeLimit): array
    {
        $usersIds = [];
        $limitDaysAgo = (new DateTime())->modify("-$areaModerationTimeLimit days");

        foreach ($assignments as $assignment) {
            $dateAssigned = new DateTime($assignment['dateAssigned']);

            if ($dateAssigned < $limitDaysAgo and !isset($usersIds[$assignment['userId']])) {
                $usersIds[$assignment['userId']] = $assignment['userId'];
            }
        }

        return $usersIds;
    }
}
