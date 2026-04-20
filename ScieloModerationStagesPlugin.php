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
use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\db\DAORegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\core\JSONMessage;
use APP\plugins\generic\scieloModerationStages\classes\SchemaEditor;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageAdvancementEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\observers\listeners\AssignFirstModerationStage;

class ScieloModerationStagesPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            Event::subscribe(new AssignFirstModerationStage());

            Hook::add('addparticipantform::display', [$this, 'addStageAdvanceToAssignForm']);
            Hook::add('addparticipantform::execute', [$this, 'sendSubmissionToNextModerationStage']);

            Hook::add('LoadComponentHandler', [$this, 'setupScieloModerationStagesHandler']);

            Hook::add('AcronPlugin::parseCronTab', [$this, 'addTasksToCrontab']);
            Hook::add('TemplateManager::display', [$this, 'addMessageToSubmissionComplete']);

            $this->editSchemas();
            $this->loadDispatcherClasses();
            $this->addHandlerURLToJavaScript();
        }
        return $success;
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
        Hook::add('Schema::add::submission', [$schemaEditor, 'editSubmissionSchema']);
        Hook::add('Schema::add::eventLog', [$schemaEditor, 'editEventLogSchema']);
    }

    public function addHandlerURLToJavaScript()
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $handlerUrl = $request->getDispatcher()->url($request, Application::ROUTE_COMPONENT, null, 'plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler');
        $data = ['moderationStagesHandlerUrl' => $handlerUrl];

        $templateMgr->addJavaScript('ModerationStagesHandler', 'app = ' . json_encode($data) . ';', ['contexts' => 'backend', 'inline' => true]);
    }

    public function addTasksToCrontab($hookName, $params)
    {
        $taskFilesPath = &$params[0];
        $taskFilesPath[] = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
        return false;
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

    public function addStageAdvanceToAssignForm($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $submission = $params[0]->getSubmission();
        $moderationStage = new ModerationStage($submission);

        if ($moderationStage->canAdvanceStage()) {
            $currentStageName = $moderationStage->getCurrentStageName();
            $nextStageName = $moderationStage->getNextStageName();

            $templateMgr->assign('currentStage', $currentStageName);
            $templateMgr->assign('nextStage', $nextStageName);

            $templateMgr->registerFilter("output", array($this, 'addCheckboxesToAssignForm'));
        }

        return false;
    }

    public function addCheckboxesToAssignForm($output, $templateMgr)
    {
        if (preg_match('/<div[^>]+class="section formButtons/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];

            $sentNextStageOutput = $templateMgr->fetch($this->getTemplateResource('sentNextStage.tpl'));

            $output = substr_replace($output, $sentNextStageOutput, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCheckboxesToAssignForm'));
        }
        return $output;
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

    public function getStyleSheet()
    {
        return $this->getPluginPath() . '/styles/moderationStageStyleSheet.css';
    }

    public function userIsAuthor($submission)
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = array();
        if ($currentUser) {
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $submission->getData('stageId'));

            while ($stageAssignment = $stageAssignmentsResult->next()) {
                $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId(), $submission->getData('contextId'));
                $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
            }
        }

        return $currentUserAssignedRoles[0] == Role::ROLE_ID_AUTHOR;
    }

    public function sendSubmissionToNextModerationStage($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $form = $params[0];
        $requestVars = $request->getUserVars();

        if ($requestVars['sendNextStage']) {
            $submission = $form->getSubmission();
            $moderationStage = new ModerationStage($submission);

            if ($moderationStage->canAdvanceStage()) {
                $moderationStage->sendNextStage();
                $moderationStageRegister = new ModerationStageRegister();
                $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
                $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);

                $emailBuilder = new StageAdvancementEmailBuilder();
                $email = $emailBuilder->setSubmission($submission)
                    ->buildEmailParams()
                    ->build();
                Mail::send($email);
            }
        }
    }
}
