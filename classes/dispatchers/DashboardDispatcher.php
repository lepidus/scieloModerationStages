<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use PKP\security\Role;
use APP\core\Application;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\components\forms\FieldSelectUsers;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderHelper;

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
        Hook::add('Form::config::after', [$this, 'addResponsiblesFilterField']);
        Hook::add('User::Collector', [$this, 'filterResponsiblesUsers']);
    }

    /**
     * Returns the abbrev id of the resp user group for the given context id.
     */
    private function respUserGroupId(int $contextId): ?int
    {
        $respUserGroup = (new ModerationReminderHelper())->getResponsiblesUserGroup($contextId);

        return $respUserGroup?->id;
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
        $context = $request->getContext();
        $moderationStages = $request->getUserVar('moderationStages');

        if ($moderationStages) {
            $query->leftJoin('submission_settings as sub_s', 's.submission_id', '=', 'sub_s.submission_id')
                ->where('sub_s.setting_name', 'currentModerationStage')
                ->whereIn('sub_s.setting_value', $moderationStages);
        }

        $responsibles = $request->getUserVar('responsibles');
        if ($responsibles && $context) {
            $responsibleUserIds = array_map('intval', (array) $responsibles);
            $respUserGroupId = $this->respUserGroupId($context->getId());

            if ($respUserGroupId) {
                $query->whereExists(function ($subQuery) use ($responsibleUserIds, $respUserGroupId) {
                    $subQuery->from('stage_assignments as resp_sa')
                        ->whereColumn('resp_sa.submission_id', 's.submission_id')
                        ->where('resp_sa.user_group_id', $respUserGroupId)
                        ->whereIn('resp_sa.user_id', $responsibleUserIds);
                });
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * Adds a "Responsibles" autosuggest filter to the submissions dashboard
     * filters form, mirroring the native "Assigned to Moderator" filter but
     * listing only users in the "resp" (Responsibles) user group.
     */
    public function addResponsiblesFilterField($hookName, $params)
    {
        $config = &$params[0];
        $form = $params[1];

        if (($config['id'] ?? null) !== 'submissionFilters') {
            return Hook::CONTINUE;
        }

        $userRoles = (array) ($form->userRoles ?? []);
        if (empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles))) {
            return Hook::CONTINUE;
        }

        $context = $form->context ?? null;
        if (!$context || !$this->respUserGroupId($context->getId())) {
            return Hook::CONTINUE;
        }

        $request = Application::get()->getRequest();
        $field = new FieldSelectUsers('responsibles', [
            'groupId' => 'default',
            'label' => __('plugins.generic.scieloModerationStages.filter.responsibles'),
            'value' => [],
            'apiUrl' => $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $context->getPath(),
                'users',
                null,
                null,
                ['scieloModerationResponsibles' => 1]
            ),
        ]);

        $config['fields'][] = $field->getConfig();

        return Hook::CONTINUE;
    }

    /**
     * Restricts the users autosuggest of the "Responsibles" filter to members
     * of the "resp" user group. Only acts when the request carries the
     * scieloModerationResponsibles flag set by addResponsiblesFilterField().
     */
    public function filterResponsiblesUsers($hookName, $params)
    {
        $query = $params[0];
        $request = Application::get()->getRequest();

        if (!$request->getUserVar('scieloModerationResponsibles')) {
            return Hook::CONTINUE;
        }

        $context = $request->getContext();
        if (!$context) {
            return Hook::CONTINUE;
        }

        $respUserGroupId = $this->respUserGroupId($context->getId());
        if (!$respUserGroupId) {
            return Hook::CONTINUE;
        }

        $query->whereExists(function ($subQuery) use ($respUserGroupId) {
            $subQuery->from('user_user_groups as resp_uug')
                ->whereColumn('resp_uug.user_id', 'u.user_id')
                ->where('resp_uug.user_group_id', $respUserGroupId);
        });

        return Hook::CONTINUE;
    }
}
