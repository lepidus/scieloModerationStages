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

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $contextId = $request->getContext()->getId();
        $moderationReminderHelper = new ModerationReminderHelper();

        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('applicationName', Application::get()->getName());
        return parent::fetch($request, $template, $display);
    }
}
