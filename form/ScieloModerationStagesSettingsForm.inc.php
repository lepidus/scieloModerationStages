<?php

import('lib.pkp.classes.form.Form');

class ScieloModerationStagesSettingsForm extends Form
{
    public const CONFIG_VARS = array(
        'preModerationTimeLimit' => 'int',
        'areaModerationTimeLimit' => 'int'
    );

    public $contextId;
    public $plugin;

    public function __construct($plugin, $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorCustom($this, 'preModerationTimeLimit', 'required', 'plugins.generic.scieloModerationStages.settings.timeLimitError', function ($timeLimit) {
            return is_numeric($timeLimit) && intval($timeLimit) > 0;
        }));
        $this->addCheck(new FormValidatorCustom($this, 'areaModerationTimeLimit', 'required', 'plugins.generic.scieloModerationStages.settings.timeLimitError', function ($timeLimit) {
            return is_numeric($timeLimit) && intval($timeLimit) > 0;
        }));
    }

    public function initData()
    {
        $contextId = $this->contextId;
        $plugin = &$this->plugin;

        foreach (self::CONFIG_VARS as $configVar => $type) {
            $this->setData($configVar, $plugin->getSetting($contextId, $configVar));
        }
    }

    public function readInputData()
    {
        $this->readUserVars(array_keys(self::CONFIG_VARS));
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('applicationName', Application::get()->getName());
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $plugin = &$this->plugin;
        $contextId = $this->contextId;

        foreach (self::CONFIG_VARS as $configVar => $type) {
            $plugin->updateSetting($contextId, $configVar, $this->getData($configVar), $type);
        }
    }
}
