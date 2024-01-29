<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\security\Validation;
use PKP\log\event\PKPSubmissionEventLogEntry;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class ModerationStageRegister
{
    public function registerModerationStageOnDatabase(ModerationStage $stage)
    {
        Repo::submission()->edit($stage->submission, []);
    }

    public function registerModerationStageOnSubmissionlog(ModerationStage $stage)
    {
        $stageName = $stage->getCurrentStageName();
        $submission = $stage->submission;

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'userId' => Validation::loggedInAs(),
            'message' => 'plugins.generic.scieloModerationStages.log.submissionSentToModerationStage',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'moderationStageName' => $stageName,
        ]);
        Repo::eventLog()->add($eventLog);
    }
}
