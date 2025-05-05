<?php

class DashboardDispatcher
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::display', [$this, 'addJavaScriptAndStylesheet']);
        HookRegistry::register('TemplateManager::display', [$this, 'addStagesFilterToSubmissionsPanels']);
        HookRegistry::register('Submission::getMany::queryBuilder', [$this, 'addFiltersToSubmissionQueryBuilder']);
    }

    public function addJavaScriptAndStylesheet($hookName, $params)
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
            return false;
        }

        $submissionsListPanels = $templateMgr->getState('components');
        $processedListPanels = array_map(function ($listPanel) {
            $moderationStagesFilters = [
                [
                    'param' => 'moderationStages',
                    'value' => SCIELO_MODERATION_STAGE_FORMAT,
                    'title' => __('plugins.generic.scieloModerationStages.stages.formatStage'),
                ],
                [
                    'param' => 'moderationStages',
                    'value' => SCIELO_MODERATION_STAGE_CONTENT,
                    'title' => __('plugins.generic.scieloModerationStages.stages.contentStage'),
                ],
                [
                    'param' => 'moderationStages',
                    'value' => SCIELO_MODERATION_STAGE_AREA,
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

        return false;
    }

    public function addFiltersToSubmissionQueryBuilder($hookName, $params)
    {
        $submissionQB = &$params[0];
        $requestArgs = $params[1];

        if (empty($requestArgs['moderationStages'])) {
            return;
        }

        $this->plugin->import('classes.services.queryBuilders.ModerationStageQueryBuilder');
        $submissionQB = new ModerationStageQueryBuilder();
        $submissionQB
            ->filterByContext($requestArgs['contextId'])
            ->orderBy($requestArgs['orderBy'], $requestArgs['orderDirection'])
            ->assignedTo($requestArgs['assignedTo'])
            ->filterByStatus($requestArgs['status'])
            ->filterByStageIds($requestArgs['stageIds'])
            ->filterByIncomplete($requestArgs['isIncomplete'])
            ->filterByOverdue($requestArgs['isOverdue'])
            ->filterByDaysInactive($requestArgs['daysInactive'])
            ->filterByCategories(isset($requestArgs['categoryIds']) ? $requestArgs['categoryIds'] : null)
            ->filterByModerationStages($requestArgs['moderationStages'])
            ->searchPhrase($requestArgs['searchPhrase']);

        if (isset($requestArgs['count'])) {
            $submissionQB->limitTo($requestArgs['count']);
        }

        if (isset($requestArgs['offset'])) {
            $submissionQB->offsetBy($requestArgs['count']);
        }
    }
}
