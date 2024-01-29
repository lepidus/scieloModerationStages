<?php

namespace APP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;

class AssignFirstModerationStage
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            AssignFirstModerationStage::class
        );
    }

    public function handle(SubmissionSubmitted $event): void
    {
        $submission = $event->submission;
        $moderationStage = new ModerationStage($submission);
        $moderationStage->setToFirstStage();
        $moderationStageRegister = new ModerationStageRegister();
        $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
    }
}
