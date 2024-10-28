<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderEmailBuilder');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');

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
        $userDao = DAORegistry::getDAO('UserDAO');
        foreach ($userAssignments as $assignment) {
            $user = $userDao->getById($assignment['userId']);
            $usersAssigned[$user->getId()] = $user->getFullName();
        }

        asort($usersAssigned, SORT_STRING);

        return $usersAssigned;
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $contextId = $request->getContext()->getId();

        $responsiblesUserGroupId = $this->getResponsiblesUserGroupId($contextId);
        if (!is_null($responsiblesUserGroupId)) {
            $responsibles = $this->getUsersAssignedByGroupAndModerationStage($responsiblesUserGroupId, SCIELO_MODERATION_STAGE_CONTENT);
            $templateMgr->assign([
                'responsiblesUserGroupId' => $responsiblesUserGroupId,
                'responsibles' => $responsibles
            ]);
        }

        $areaModeratorsUserGroupId = $this->getAreaModeratorsUserGroupId($contextId);
        if (!is_null($areaModeratorsUserGroupId)) {
            $areaModerators = $this->getUsersAssignedByGroupAndModerationStage($areaModeratorsUserGroupId, SCIELO_MODERATION_STAGE_AREA);
            $templateMgr->assign([
                'areaModeratorsUserGroupId' => $areaModeratorsUserGroupId,
                'areaModerators' => $areaModerators
            ]);
        }

        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'applicationName' => Application::get()->getName()
        ]);

        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
        $this->readUserVars(['responsible', 'reminderBody']);
    }

    public function execute(...$functionArgs)
    {
        $responsibleUserId = $this->getData('responsible');
        $reminderBody = $this->getData('reminderBody');

        $responsible = DAORegistry::getDAO('UserDAO')->getById($responsibleUserId);
        $context = Application::get()->getRequest()->getContext();

        $locale = AppLocale::getLocale();
        $preModerationTimeLimit = $this->plugin->getSetting($this->contextId, 'preModerationTimeLimit');

        $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder($context, $responsible, [], $locale, $preModerationTimeLimit);
        $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
        $reminderEmail->setBody($reminderBody);

        $reminderEmail->send();
    }
}
