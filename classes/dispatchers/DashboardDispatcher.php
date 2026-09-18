<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use Illuminate\Support\Facades\DB;

class DashboardDispatcher
{
    private const PRE_MODERATION_REQUIRED_TITLE = '[SciELO Preprints] Pré-Moderação necessária';

    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        Hook::add('TemplateManager::display', [$this, 'addDashboardJavaScriptAndStylesheet']);
        Hook::add('TemplateManager::display', [$this, 'addFiltersToSubmissionsPanels']);
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

    public function addFiltersToSubmissionsPanels($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template !== 'dashboard/index.tpl') {
            return Hook::CONTINUE;
        }

        $submissionsListPanels = $templateMgr->getState('components');
        $submissionsListPanels = array_map([$this, 'addPendingActionFilterToListPanel'], $submissionsListPanels);
        $submissionsListPanels = array_map([$this, 'addModerationStagesFilterToListPanel'], $submissionsListPanels);

        $templateMgr->setState(['components' => $submissionsListPanels]);

        return Hook::CONTINUE;
    }

    private function addModerationStagesFilterToListPanel($listPanel)
    {
        $moderationStagesFilter = [
            'heading' => __('plugins.generic.scieloModerationStages.displayNameWorkflow'),
            'filters' => [
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
            ]
        ];

        $listPanel['filters'] = $this->insertNewFilterOnListPanel($listPanel['filters'], $moderationStagesFilter, 2);
        return $listPanel;
    }

    private function addPendingActionFilterToListPanel($listPanel)
    {
        $pendingActionFilter = [
            'heading' => __('plugins.generic.scieloModerationStages.moderation'),
            'filters' => [
                [
                    'param' => 'pendingModerationAction',
                    'value' => true,
                    'title' => __('plugins.generic.scieloModerationStages.filter.pendingAction'),
                ]
            ]
        ];

        $listPanel['filters'] = $this->insertNewFilterOnListPanel($listPanel['filters'], $pendingActionFilter, 1);

        return $listPanel;
    }

    private function insertNewFilterOnListPanel(array $filters, array $newFilter, int $position): array
    {
        return array_merge(
            array_slice($filters, 0, $position),
            [$newFilter],
            array_slice($filters, $position),
        );
    }

    public function addFiltersToSubmissionCollector($hookName, $params)
    {
        $query = &$params[0];
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $moderationStages = $request->getUserVar('moderationStages');
        $pendingModerationAction = $request->getUserVar('pendingModerationAction');

        if ($moderationStages) {
            $query->leftJoin('submission_settings as sub_s', 's.submission_id', '=', 'sub_s.submission_id')
                ->where('sub_s.setting_name', 'currentModerationStage')
                ->whereIn('sub_s.setting_value', $moderationStages);
        }

        if ($pendingModerationAction) {
            $query->whereExists(function ($pendingActionQuery) use ($user) {
                $pendingActionQuery
                    ->select(DB::raw(1))
                    ->from('queries as q')
                    ->join('query_participants as qp', function ($join) use ($user) {
                        $join->on('qp.query_id', '=', 'q.query_id')
                            ->where('qp.user_id', '=', $user->getId());
                    })
                    ->join('notes as first_note', function ($join) {
                        $join->on('first_note.assoc_id', '=', 'q.query_id')
                            ->where('first_note.assoc_type', '=', Application::ASSOC_TYPE_QUERY);
                    })
                    ->join('notes as last_note', function ($join) {
                        $join->on('last_note.assoc_id', '=', 'q.query_id')
                            ->where('last_note.assoc_type', '=', Application::ASSOC_TYPE_QUERY);
                    })
                    ->where('q.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
                    ->whereColumn('q.assoc_id', 's.submission_id')
                    ->where('first_note.title', 'like', '%' . self::PRE_MODERATION_REQUIRED_TITLE . '%')
                    ->where('last_note.user_id', '<>', $user->getId())
                    ->whereNotExists(function ($earlierNoteQuery) {
                        $earlierNoteQuery
                            ->select(DB::raw(1))
                            ->from('notes as earlier_note')
                            ->whereColumn('earlier_note.assoc_id', 'q.query_id')
                            ->where('earlier_note.assoc_type', Application::ASSOC_TYPE_QUERY)
                            ->whereColumn('earlier_note.date_created', '<', 'first_note.date_created');
                    })
                    ->whereNotExists(function ($laterNoteQuery) {
                        $laterNoteQuery
                            ->select(DB::raw(1))
                            ->from('notes as later_note')
                            ->whereColumn('later_note.assoc_id', 'q.query_id')
                            ->where('later_note.assoc_type', Application::ASSOC_TYPE_QUERY)
                            ->whereColumn('later_note.date_created', '>', 'last_note.date_created');
                    });
            });
        }
    }
}
