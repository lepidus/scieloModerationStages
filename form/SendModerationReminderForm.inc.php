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

    private function getResponsibles(int $contextId): array
    {
        $moderationReminderHelper = new ModerationReminderHelper();
        $moderationStageDao = new ModerationStageDAO();
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($contextId);
        $responsibleAssignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage($responsiblesUserGroup->getId(), SCIELO_MODERATION_STAGE_CONTENT);

        if (empty($responsibleAssignments)) {
            return [];
        }

        $responsibles = [null => null];
        $userDao = DAORegistry::getDAO('UserDAO');
        foreach ($responsibleAssignments as $assignment) {
            $user = $userDao->getById($assignment['userId']);
            $fullName = $user->getFullName();
            $responsibles[$user->getId()] = $fullName;
        }

        asort($responsibles, SORT_STRING);

        return $responsibles;
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $contextId = $request->getContext()->getId();

        $templateMgr->assign([
            'responsibles' => $this->getResponsibles($contextId),
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

        $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder($context, $responsible, []);
        $reminderEmail = $moderationReminderEmailBuilder->buildEmail();
        $reminderEmail->setBody($reminderBody);

        $reminderEmail->send();
    }
}
