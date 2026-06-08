<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use PKP\security\Role;
use APP\core\Application;
use APP\submission\Submission;
use APP\template\TemplateManager;
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
        Hook::add('Dashboard::views', [$this, 'addModerationStagesViews']);
        Hook::add('TemplateManager::setupBackendPage', [$this, 'addModerationStagesToMenu']);
        Hook::add('Submission::Collector', [$this, 'addFiltersToSubmissionCollector']);
    }

    /**
     * Adds one entry per moderation stage to the editor dashboard menu (SideNav),
     * so moderation stages show up as tabs under "Editorial dashboard".
     *
     * Each item carries the same placeholder badge as the core views; the actual
     * count is merged into the _submissions/viewsCount response on the client side
     * (see resources/js/main.js), so the native badge is rendered identically.
     */
    public function addModerationStagesToMenu($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        $router = $request->getRouter();

        if (!$context || !$user || !$router->getHandler()) {
            return Hook::CONTINUE;
        }

        $userRoles = (array) $router->getHandler()->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $editorialRoles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
        if (empty(array_intersect($editorialRoles, $userRoles))) {
            return Hook::CONTINUE;
        }

        $templateMgr = TemplateManager::getManager($request);
        $menu = $templateMgr->getState('menu');
        if (!isset($menu['dashboards']['submenu'])) {
            return Hook::CONTINUE;
        }

        $requestedPage = $router->getRequestedPage($request);
        $requestedOp = $router->getRequestedOp($request);
        $requestedViewId = $request->getUserVar('currentViewId');

        $stages = [
            ModerationStage::SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
            ModerationStage::SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
            ModerationStage::SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
        ];

        $moderationItems = [];
        foreach ($stages as $stage => $localeKey) {
            $viewId = 'moderation-stage-' . $stage;
            $moderationItems[$viewId] = [
                'id' => $viewId,
                'name' => __($localeKey),
                'isCurrent' => $requestedPage === 'dashboard' && $requestedOp === 'editorial' && $requestedViewId === $viewId,
                'url' => $router->url($request, null, 'dashboard', 'editorial', null, ['currentViewId' => $viewId]),
                'badge' => ['slot' => '-'],
            ];
        }

        // Keep the "start new submission" entry (when present) as the last item.
        $submenu = collect($menu['dashboards']['submenu'])->all();
        $newSubmission = $submenu['newSubmission'] ?? null;
        unset($submenu['newSubmission']);

        $submenu = array_merge($submenu, $moderationItems);
        if (!is_null($newSubmission)) {
            $submenu['newSubmission'] = $newSubmission;
        }

        $menu['dashboards']['submenu'] = $submenu;
        $templateMgr->setState(['menu' => $menu]);

        return Hook::CONTINUE;
    }

    /**
     * Adds one dashboard view (tab) per moderation stage to the editorial dashboard,
     * so submissions can be browsed by moderation stage instead of using a filter.
     */
    public function addModerationStagesViews($hookName, $params)
    {
        $viewsData = &$params[0];
        $userRoles = $params[1] ?? [];

        $request = Application::get()->getRequest();
        if ($request->getRequestedOp() !== 'editorial') {
            return Hook::CONTINUE;
        }

        $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));
        $assignedWithRoles = $canAccessUnassignedSubmission
            ? null
            : [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];

        $stages = [
            ModerationStage::SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
            ModerationStage::SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
            ModerationStage::SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
        ];

        foreach ($stages as $stage => $localeKey) {
            $view = [
                'id' => 'moderation-stage-' . $stage,
                'name' => __($localeKey),
                'count' => 0,
                'queryParams' => [
                    'moderationStages' => [$stage],
                    'status' => [Submission::STATUS_QUEUED],
                    'assignedWithRoles' => $assignedWithRoles,
                ],
            ];

            if (!$canAccessUnassignedSubmission) {
                $view['op'] = 'assigned';
            }

            $viewsData[] = $view;
        }

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
