<?php

namespace APP\plugins\generic\scieloModerationStages\form;

use PKP\form\Form;
use PKP\facades\Locale;
use APP\facades\Repo;
use APP\template\TemplateManager;
use APP\core\Application;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderHelper;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class SendModerationReminderForm extends Form
{
    public $contextId;
    public $plugin;

    public function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('sendModerationReminderForm.tpl'));
    }

    private function getResponsiblesUserGroupId($contextId)
    {
        $moderationReminderHelper = new ModerationReminderHelper();
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($contextId);

        return ($responsiblesUserGroup ? $responsiblesUserGroup->getId() : null);
    }

    private function getAreaModeratorsUserGroupId($contextId)
    {
        $moderationReminderHelper = new ModerationReminderHelper();
        $areaModeratorsUserGroup = $moderationReminderHelper->getAreaModeratorsUserGroup($contextId);

        return ($areaModeratorsUserGroup ? $areaModeratorsUserGroup->getId() : null);
    }

    private function getUsersAssignedByGroupAndModerationStage(int $userGroupId, int $moderationStage): array
    {
        $moderationStageDao = new ModerationStageDAO();
        $userAssignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $userGroupId,
            $moderationStage
        );

        if (empty($userAssignments)) {
            return [];
        }

        $usersAssigned = [null => null];
        foreach ($userAssignments as $assignment) {
            $user = Repo::user()->get($assignment['userId']);
            $usersAssigned[$user->getId()] = $user->getFullName();
        }

        asort($usersAssigned, SORT_STRING);

        return $usersAssigned;
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $contextId = $request->getContext()->getId();

        $roles = [
            ModerationReminderEmailBuilder::REMINDER_TYPE_PRE_MODERATION => __('plugins.generic.scieloModerationStages.sendModerationReminder.responsible.title'),
            ModerationReminderEmailBuilder::REMINDER_TYPE_AREA_MODERATION => __('plugins.generic.scieloModerationStages.sendModerationReminder.areaModerator.title')
        ];

        $responsiblesUserGroupId = $this->getResponsiblesUserGroupId($contextId);
        if (!is_null($responsiblesUserGroupId)) {
            $responsibles = $this->getUsersAssignedByGroupAndModerationStage($responsiblesUserGroupId, ModerationStage::SCIELO_MODERATION_STAGE_CONTENT);
            $templateMgr->assign([
                'responsiblesUserGroupId' => $responsiblesUserGroupId,
                'responsibles' => $responsibles
            ]);
        }

        $areaModeratorsUserGroupId = $this->getAreaModeratorsUserGroupId($contextId);
        if (!is_null($areaModeratorsUserGroupId)) {
            $areaModerators = $this->getUsersAssignedByGroupAndModerationStage($areaModeratorsUserGroupId, ModerationStage::SCIELO_MODERATION_STAGE_AREA);
            $templateMgr->assign([
                'areaModeratorsUserGroupId' => $areaModeratorsUserGroupId,
                'areaModerators' => $areaModerators
            ]);
        }

        $templateMgr->assign([
            'roles' => $roles,
            'pluginName' => $this->plugin->getName(),
            'applicationName' => Application::get()->getName()
        ]);

        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
        $this->readUserVars(['reminderRole', 'responsible', 'areaModerator', 'reminderBody']);
    }

    public function execute(...$functionArgs)
    {
        $reminderRole = $this->getData('reminderRole');
        $locale = Locale::getLocale();
        $context = Application::get()->getRequest()->getContext();
        $reminderBody = $this->getData('reminderBody');

        if ($reminderRole == ModerationReminderEmailBuilder::REMINDER_TYPE_PRE_MODERATION) {
            $userId = $this->getData('responsible');
            $moderationTimeLimit = $this->plugin->getSetting($this->contextId, 'preModerationTimeLimit');
        } elseif ($reminderRole == ModerationReminderEmailBuilder::REMINDER_TYPE_AREA_MODERATION) {
            $userId = $this->getData('areaModerator');
            $moderationTimeLimit = $this->plugin->getSetting($this->contextId, 'areaModerationTimeLimit');
        }

        $user = Repo::user()->get($userId);
        $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder(
            $context,
            $user,
            [],
            $locale,
            $reminderRole,
            $moderationTimeLimit
        );
        $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
        $reminderEmail->body($reminderBody);

        Mail::send($reminderEmail);
    }
}
