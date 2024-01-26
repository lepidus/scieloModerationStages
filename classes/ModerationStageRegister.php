<?php

import('classes.log.SubmissionEventLogEntry');
import('lib.pkp.classes.log.SubmissionLog');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');

class ModerationStageRegister
{
    public function registerModerationStageOnDatabase($moderationStage)
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submissionDao->updateObject($moderationStage->submission);
    }

    public function registerModerationStageOnSubmissionlog($moderationStage)
    {
        $moderationStageName = $moderationStage->getCurrentStageName();
        $request = Application::get()->getRequest();
        SubmissionLog::logEvent($request, $moderationStage->submission, SUBMISSION_LOG_METADATA_UPDATE, 'plugins.generic.scieloModerationStages.log.submissionSentToModerationStage', ['moderationStageName' => $moderationStageName]);
    }
}
