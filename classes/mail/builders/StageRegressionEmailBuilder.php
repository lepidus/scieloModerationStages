<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\builders;

use PKP\mail\Mailable;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\mail\mailables\ReturnedToModerationStage;

class StageRegressionEmailBuilder extends ModerationStageEmailBuilder
{
    public function buildEmailParams(): static
    {
        parent::buildEmailParams();

        $moderationStage = new ModerationStage($this->submission);
        $this->emailParams['moderationStageName'] = $moderationStage->getCurrentStageName();

        return $this;
    }

    public function build(): Mailable
    {
        $emailTemplate = $this->getEmailTemplate(ReturnedToModerationStage::getEmailTemplateKey());

        $email = new ReturnedToModerationStage($this->context, $this->submission, $this->emailParams);
        $email->from($this->context->getData('contactEmail'), $this->context->getData('contactName'))
            ->to($this->primaryAuthor->getEmail(), $this->primaryAuthor->getFullName())
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'));

        return $email;
    }
}
