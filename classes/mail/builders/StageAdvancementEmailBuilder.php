<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\builders;

use APP\submission\Submission;
use PKP\mail\Mailable;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\mail\mailables\{
    SentToAreaModerationStage,
    SentToTypeModerationStage
};

class StageAdvancementEmailBuilder extends ModerationStageEmailBuilder
{
    private $moderationStage;

    public function setSubmission(Submission $submission): static
    {
        parent::setSubmission($submission);
        $this->moderationStage = $submission->getData('currentModerationStage');
        return $this;
    }

    public function build(): Mailable
    {
        list($emailTemplateKey, $emailTemplateClass) = $this->getEmailTemplateByModerationStage($this->moderationStage);
        $emailTemplate = $this->getEmailTemplate($emailTemplateKey);

        $email = new $emailTemplateClass($this->context, $this->submission, $this->emailParams);
        $email->from($this->context->getData('contactEmail'), $this->context->getData('contactName'));
        $email->to([
            ['name' => $this->primaryAuthor->getFullName(), 'email' => $this->primaryAuthor->getEmail()]
        ]);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($emailTemplate->getLocalizedData('body'));

        return $email;
    }

    private function getEmailTemplateByModerationStage(int $moderationStage): ?array
    {
        $moderationStageEmailTemplateMap = [
            ModerationStage::SCIELO_MODERATION_STAGE_CONTENT => ['SENT_TO_TYPE_MODERATION_STAGE', SentToTypeModerationStage::class],
            ModerationStage::SCIELO_MODERATION_STAGE_AREA => ['SENT_TO_AREA_MODERATION_STAGE', SentToAreaModerationStage::class],
        ];

        return $moderationStageEmailTemplateMap[$moderationStage] ?? null;
    }
}
