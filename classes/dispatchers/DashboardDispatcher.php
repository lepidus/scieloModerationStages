<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class DashboardDispatcher
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        Hook::add('TemplateManager::display', [$this, 'addDashboardJavaScriptAndStylesheet']);
        Hook::add('TemplateManager::display', [$this, 'addStagesFilterToSubmissionsPanels']);
        Hook::add('Submission::Collector', [$this, 'addFiltersToSubmissionCollector']);
    }

    public function addDashboardJavaScriptAndStylesheet($hookName, $params)
    {
        if ($params[1] == 'dashboard/index.tpl') {
            $templateMgr = $params[0];
            $request = Application::get()->getRequest();

            $jsUrl = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/js/load.js';
            $styleUrl = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/styles/stageExhibitor.css';

            $templateMgr->addJavascript('ModerationStagesPlugin', $jsUrl, ['contexts' => 'backend']);
            $templateMgr->addStyleSheet('ModerationStagesExhibitor', $styleUrl, ['contexts' => 'backend']);
        }
        return false;
    }

    public function addStagesFilterToSubmissionsPanels($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template !== 'dashboard/index.tpl') {
            return Hook::CONTINUE;
        }

        $submissionsListPanels = $templateMgr->getState('components');
        $processedListPanels = array_map(function ($listPanel) {
            $moderationStagesFilters = [
                [
                    'param' => 'moderationStages',
                    'value' => ModerationStage::SCIELO_MODERATION_STAGE_FORMAT,
                    'title' => __('plugins.generic.scieloModerationStages.stages.formatStage'),
                ],
                [
                    'param' => 'moderationStages',
                    'value' => ModerationStage::SCIELO_MODERATION_STAGE_CONTENT,
                    'title' => __('plugins.generic.scieloModerationStages.stages.contentStage'),
                ],
                [
                    'param' => 'moderationStages',
                    'value' => ModerationStage::SCIELO_MODERATION_STAGE_AREA,
                    'title' => __('plugins.generic.scieloModerationStages.stages.areaStage'),
                ]
            ];
            $listPanel['filters'][] = [
                'heading' => __('plugins.generic.scieloModerationStages.displayNameWorkflow'),
                'filters' => $moderationStagesFilters
            ];
            return $listPanel;
        }, $submissionsListPanels);

        $templateMgr->setState(['components' => $processedListPanels]);

        return Hook::CONTINUE;
    }

    public function addFiltersToSubmissionCollector($hookName, $params)
    {
        $query = &$params[0];
        $request = Application::get()->getRequest();
        $moderationStages = $request->getUserVar('moderationStages');

        if ($moderationStages) {
            $query->leftJoin('submission_settings as sub_s', 's.submission_id', '=', 'sub_s.submission_id')
                ->where('sub_s.setting_name', 'currentModerationStage')
                ->whereIn('sub_s.setting_value', $moderationStages);
        }
    }
}
