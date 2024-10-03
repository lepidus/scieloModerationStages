<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

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
        $responsiblesUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($contextId);
        $responsibleAssignments = $moderationReminderHelper->getResponsibleAssignments($responsiblesUserGroup, $contextId);

        if (empty($responsibleAssignments)) {
            return [];
        }

        $filteredAssignments = $moderationReminderHelper->filterAssignmentsOfSubmissionsOnPreModeration($responsibleAssignments);
        $usersFromAssignments = $moderationReminderHelper->getUsersFromAssignments($filteredAssignments);

        $mappedUsers = [null => null];
        foreach ($usersFromAssignments as $userId => $user) {
            $fullName = $user->getFullName();
            $mappedUsers[$userId] = $fullName;
        }

        asort($mappedUsers, SORT_STRING);

        return $mappedUsers;
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
        error_log($this->getData('responsible'));
        error_log($this->getData('reminderBody'));
    }
}
