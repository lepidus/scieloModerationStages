<?php

namespace APP\plugins\generic\scieloModerationStages\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageAdvancementEmailBuilder;

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
        Hook::add('queryform::display', [$this, 'hideParticipantsOnDiscussionOpening']);

        Hook::add('addparticipantform::display', [$this, 'addStageAdvanceToAssignForm']);
        Hook::add('addparticipantform::execute', [$this, 'sendSubmissionToNextModerationStage']);
    }

    public function hideParticipantsOnDiscussionOpening($hookName, $params)
    {
        $form = $params[0];
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $allParticipants = $templateMgr->getTemplateVars('allParticipants');

        $query = $form->getQuery();
        $submission = Repo::submission()->get($query->assocId);

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

            $templateMgr->registerFilter("output", [$this, 'addCheckboxesToAssignForm']);
        }

        return false;
    }

    public function addCheckboxesToAssignForm($output, $templateMgr)
    {
        if (preg_match('/<div[^>]+class="section formButtons/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];

            $sentNextStageOutput = $templateMgr->fetch($this->plugin->getTemplateResource('sentNextStage.tpl'));

            $output = substr_replace($output, $sentNextStageOutput, $posMatch, 0);
            $templateMgr->unregisterFilter('output', [$this, 'addCheckboxesToAssignForm']);
        }
        return $output;
    }

    public function sendSubmissionToNextModerationStage($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $form = $params[0];
        $requestVars = $request->getUserVars();

        if (!empty($requestVars['sendNextStage'])) {
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
