<?php

namespace APP\plugins\generic\scieloModerationStages\classes\tasks;

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
        $moderationReminderHelper = new ModerationReminderHelper();
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($context->getId());

        $moderationStageDao = new ModerationStageDAO();
        $responsibleAssignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $responsiblesUserGroup->getId(),
            ModerationStage::SCIELO_MODERATION_STAGE_CONTENT
        );

        if (empty($responsibleAssignments)) {
            return true;
        }

        $usersWithOverduePreModeration = $this->getUsersWithOverduePreModeration($context->getId(), $responsibleAssignments);
        $mapModeratorsAndOverdueSubmissions = $moderationReminderHelper->mapUsersAndSubmissions($usersWithOverduePreModeration, $responsibleAssignments);

        $locale = $context->getPrimaryLocale();
        $this->plugin->addLocaleData($locale);
        $preModerationTimeLimit = $this->plugin->getSetting($context->getId(), 'preModerationTimeLimit');

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

        return true;
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
