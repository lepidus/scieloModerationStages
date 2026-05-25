<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use PKP\components\forms\FieldOptions;
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
        Hook::add('TemplateManager::display', [$this, 'addModerationStagesFilter']);
        Hook::add('Submission::Collector', [$this, 'addFiltersToSubmissionCollector']);
    }

    public function addModerationStagesFilter($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template !== 'dashboard/editors.tpl') {
            return Hook::CONTINUE;
        }

        $pageInitConfig = $templateMgr->getState('pageInitConfig');
        if (!isset($pageInitConfig['filtersForm'])) {
            return Hook::CONTINUE;
        }

        $field = new FieldOptions('moderationStages', [
            'groupId' => 'default',
            'label' => __('plugins.generic.scieloModerationStages.displayNameWorkflow'),
            'options' => [
                [
                    'value' => ModerationStage::SCIELO_MODERATION_STAGE_FORMAT,
                    'label' => __('plugins.generic.scieloModerationStages.stages.formatStage'),
                ],
                [
                    'value' => ModerationStage::SCIELO_MODERATION_STAGE_CONTENT,
                    'label' => __('plugins.generic.scieloModerationStages.stages.contentStage'),
                ],
                [
                    'value' => ModerationStage::SCIELO_MODERATION_STAGE_AREA,
                    'label' => __('plugins.generic.scieloModerationStages.stages.areaStage'),
                ],
            ],
            'value' => [],
        ]);

        $pageInitConfig['filtersForm']['fields'][] = $field->getConfig();
        $templateMgr->setState(['pageInitConfig' => $pageInitConfig]);

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
