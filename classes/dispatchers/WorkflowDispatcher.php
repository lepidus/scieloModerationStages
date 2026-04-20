<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class WorkflowDispatcher
{
    private const SCIELO_BRASIL_EMAIL = 'scielo.submission@scielo.org';

    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        Hook::add('Template::Workflow::Publication', [$this, 'addToWorkflowTabs']);
        Hook::add('Template::Workflow', [$this, 'addCurrentStageStatusToWorkflow']);
        Hook::add('queryform::display', [$this, 'hideParticipantsOnDiscussionOpening']);
    }

    public function addToWorkflowTabs($hookName, $params)
    {
        $templateMgr = &$params[1];
        $output = &$params[2];
        $submission = $templateMgr->getTemplateVars('submission');

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $faqUrl = $request->url($context->getPath()) . '/faq';

        $moderationStage = new ModerationStage($submission);
        if ($moderationStage->submissionStageExists()) {
            $stageDates = $moderationStage->getStageEntryDates();
            $currentStageName = $moderationStage->getCurrentStageName(false);

            $templateMgr->assign([
                ...$stageDates,
                'submissionId' => $submission->getId(),
                'userIsAuthor' => $this->plugin->userIsAuthor($submission),
                'currentStage' => $currentStageName,
                'canAdvanceStage' => $moderationStage->canAdvanceStage(),
                'faqUrl' => $faqUrl
            ]);

            if ($moderationStage->canAdvanceStage()) {
                $templateMgr->assign('nextStage', $moderationStage->getNextStageName());
            }

            $output .= sprintf(
                '<tab id="scieloModerationStages" label="%s">%s</tab>',
                __('plugins.generic.scieloModerationStages.displayNameWorkflow'),
                $templateMgr->fetch($this->plugin->getTemplateResource('moderationStageMenu.tpl'))
            );
        }
    }

    public function addCurrentStageStatusToWorkflow($hookName, $params)
    {
        $templateMgr = &$params[1];
        $submission = $templateMgr->getTemplateVars('submission');

        if (!is_null($submission->getData('currentModerationStage'))) {
            $moderationStage = new ModerationStage($submission);

            $templateMgr->assign('currentStageName', $moderationStage->getCurrentStageName());
            $templateMgr->registerFilter("output", [$this, 'addCurrentStageStatusToWorkflowFilter']);
        }

        return Hook::CONTINUE;
    }

    public function addCurrentStageStatusToWorkflowFilter($output, $templateMgr)
    {
        if (preg_match('/<span[^>]+v-if="publicationList.length/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];

            $currentStageStatus = $templateMgr->fetch($this->plugin->getTemplateResource('currentStageStatus.tpl'));

            $output = substr_replace($output, $currentStageStatus, $posMatch, 0);
            $templateMgr->unregisterFilter('output', [$this, 'addCurrentStageStatusToWorkflowFilter']);
        }
        return $output;
    }

    public function hideParticipantsOnDiscussionOpening($hookName, $params)
    {
        $form = $params[0];
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $allParticipants = $templateMgr->getTemplateVars('allParticipants');

        $query = $form->getQuery();
        $submission = Repo::submission()->get($query->getData('assocId'));

        if ($this->plugin->userIsAuthor($submission)) {
            $author = $request->getUser();
            $newParticipantsList = [];
            $allowedUsersEmails = [
                $author->getEmail(),
                self::SCIELO_BRASIL_EMAIL
            ];

            foreach ($allParticipants as $participantId => $participantData) {
                $participant = Repo::user()->get($participantId);

                if (in_array($participant->getEmail(), $allowedUsersEmails)) {
                    $newParticipantsList[$participantId] = $participantData;
                }
            }

            $templateMgr->assign('allParticipants', $newParticipantsList);
        }

        return Hook::CONTINUE;
    }
}
