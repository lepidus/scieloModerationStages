<?php

/**
 * @file plugins/reports/scieloModerationStages/ScieloModerationStagesPlugin.inc.php
 *
 * Copyright (c) 2022 - 2024 Lepidus Tecnologia
 * Copyright (c) 2022 - 2024 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class ScieloModerationStagesPlugin
 * @ingroup plugins_generic_scieloModerationStages
 *
 * @brief SciELO Moderation Stages Plugin
 */

namespace APP\plugins\generic\scieloModerationStages;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\scheduledTask\PKPScheduler;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use Illuminate\Support\Facades\Event;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\core\JSONMessage;
use APP\plugins\generic\scieloModerationStages\classes\SchemaEditor;
use APP\plugins\generic\scieloModerationStages\classes\observers\listeners\AssignFirstModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\tasks\SendModerationReminders;

class ScieloModerationStagesPlugin extends GenericPlugin implements HasTaskScheduler
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            Event::subscribe(new AssignFirstModerationStage());

            Hook::add('LoadComponentHandler', [$this, 'setupScieloModerationStagesHandler']);
            Hook::add('TemplateManager::display', [$this, 'addMessageToSubmissionComplete']);

            $this->editSchemas();
            $this->loadDispatcherClasses();
            $this->addHandlerURLToJavaScript();
            $this->addBackendUiAssets();
        }
        return $success;
    }

    private function addBackendUiAssets(): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->addJavaScript(
            'ScieloModerationStages',
            "{$request->getBaseUrl()}/{$this->getPluginPath()}/public/build/build.iife.js",
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
            ]
        );
        $templateMgr->addStyleSheet(
            'ScieloModerationStagesStyle',
            "{$request->getBaseUrl()}/{$this->getPluginPath()}/public/build/build.css",
            ['contexts' => ['backend']]
        );
    }

    private function loadDispatcherClasses(): void
    {
        $dispatcherClasses = [
            'DashboardDispatcher',
            'WorkflowDispatcher'
        ];

        foreach ($dispatcherClasses as $dispatcherClass) {
            $dispatcherClass = 'APP\plugins\generic\scieloModerationStages\classes\dispatchers\\' . $dispatcherClass;
            $dispatcher = new $dispatcherClass($this);
        }
    }

    private function editSchemas()
    {
        $schemaEditor = new SchemaEditor();
        Hook::add('Schema::get::submission', [$schemaEditor, 'editSubmissionSchema']);
        Hook::add('Schema::get::eventLog', [$schemaEditor, 'editEventLogSchema']);
    }

    public function addHandlerURLToJavaScript()
    {
        $request = Application::get()->getRequest();

        if (!$request->getContext()) {
            return;
        }

        $templateMgr = TemplateManager::getManager($request);
        $dispatcher = $request->getDispatcher();
        $component = 'plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler';

        // PKPComponentRouter::url() requires an explicit operation since 3.5,
        // so each endpoint URL is built individually.
        $handlerOps = [
            'getModerationTabData',
            'updateSubmissionStageData',
            'getUserIsAuthor',
            'getSubmissionExhibitData',
            'getModerationStageCounts',
        ];
        $handlerUrls = [];
        foreach ($handlerOps as $op) {
            $handlerUrls[$op] = $dispatcher->url($request, Application::ROUTE_COMPONENT, null, $component, $op);
        }
        $data = ['moderationStagesHandlerUrls' => $handlerUrls];

        $templateMgr->addJavaScript('ModerationStagesHandler', 'app = ' . json_encode($data) . ';', ['contexts' => 'backend', 'inline' => true]);
    }

    public function registerSchedules(PKPScheduler $scheduler): void
    {
        $scheduler->addSchedule(new SendModerationReminders())
            ->weeklyOn(1, '00:00')
            ->name(SendModerationReminders::class)
            ->withoutOverlapping();
    }

    public function getDisplayName()
    {
        return __('plugins.generic.scieloModerationStages.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.scieloModerationStages.description');
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        return array_merge(
            [
                new LinkAction(
                    'sendModerationReminder',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'sendModerationReminder', 'plugin' => $this->getName(), 'category' => 'generic']),
                        __('plugins.generic.scieloModerationStages.sendModerationReminder')
                    ),
                    __('plugins.generic.scieloModerationStages.sendModerationReminder')
                ),
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        __('plugins.generic.scieloModerationStages.settings.title')
                    ),
                    __('manager.plugins.settings')
                )
            ],
            parent::getActions($request, $actionArgs)
        );
    }

    public function manage($args, $request)
    {
        $context = $request->getContext();
        $contextId = ($context == null) ? 0 : $context->getId();

        switch ($request->getUserVar('verb')) {
            case 'settings':
                return $this->handlePluginForm($request, $contextId, 'ScieloModerationStagesSettingsForm');
            case 'sendModerationReminder':
                return $this->handlePluginForm($request, $contextId, 'SendModerationReminderForm');
        }
        return parent::manage($args, $request);
    }

    private function handlePluginForm($request, $contextId, $formClass)
    {
        $formClass = 'APP\plugins\generic\scieloModerationStages\form\\' . $formClass;
        $form = new $formClass($this, $contextId);
        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        } else {
            $form->initData();
        }
        return new JSONMessage(true, $form->fetch($request));
    }

    public function setupScieloModerationStagesHandler($hookName, $params)
    {
        $component = &$params[0];
        if ($component == 'plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler') {
            return true;
        }
        return false;
    }

    public function addNewPropsToSubmissionSchema($hookName, $params)
    {
        $schema = &$params[0];
        $newProperties = [
            'currentModerationStage' => 'string',
            'lastModerationStageChange' => 'string',
            'formatStageEntryDate' => 'string',
            'contentStageEntryDate' => 'string',
            'areaStageEntryDate' => 'string'
        ];

        foreach ($newProperties as $property => $type) {
            $schema->properties->{$property} = (object) [
                'type' => $type,
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }

        return Hook::CONTINUE;
    }

    public function addNewPropsToEventLogSchema($hookName, $params)
    {
        $schema = &$params[0];
        $newProperties = [
            'moderationStageName' => 'string',
        ];

        foreach ($newProperties as $property => $type) {
            $schema->properties->{$property} = (object) [
                'type' => $type,
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }

        return Hook::CONTINUE;
    }

    public function addMessageToSubmissionComplete($hookName, $params)
    {
        $template = &$params[1];
        if ($template === 'submission/complete.tpl') {
            $templateMgr = $params[0];
            $templateMgr->registerFilter('output', [$this, 'addMessageToSubmissionCompleteFilter']);
        }
        return false;
    }

    public function addMessageToSubmissionCompleteFilter($output, $templateMgr)
    {
        if (preg_match('/class="app__contentPanel"/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $contentPanelPos = $matches[0][1];
            $afterContentPanel = substr($output, $contentPanelPos);
            if (preg_match('/<\/div>/', $afterContentPanel, $divMatches, PREG_OFFSET_CAPTURE)) {
                $insertPos = $contentPanelPos + $divMatches[0][1];
                $submissionCompleteMsg = $templateMgr->fetch($this->getTemplateResource('submissionCompleteMsg.tpl'));
                $output = substr_replace($output, $submissionCompleteMsg, $insertPos, 0);
                $templateMgr->unregisterFilter('output', [$this, 'addMessageToSubmissionCompleteFilter']);
            }
        }
        return $output;
    }

    public function userIsAuthor($submission)
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = array();
        if ($currentUser) {
            $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withUserId($currentUser->getId())
                ->withStageIds([$submission->getData('stageId')])
                ->get();

            foreach ($stageAssignments as $stageAssignment) {
                $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId, $submission->getData('contextId'));
                $currentUserAssignedRoles[] = (int) $userGroup->roleId;
            }
        }

        return $currentUserAssignedRoles[0] == Role::ROLE_ID_AUTHOR;
    }
}
