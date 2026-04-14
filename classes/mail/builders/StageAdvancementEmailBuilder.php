<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\builders;

use APP\core\Application;
use APP\submission\Submission;
use PKP\mail\Mailable;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\mail\mailables\SentToTypeModerationStage;

class StageAdvancementEmailBuilder
{
    private $context;
    private $submission;
    private $primaryAuthor;
    private $moderationStage;
    private $emailParams;

    public function setSubmission(Submission $submission): StageAdvancementEmailBuilder
    {
        $this->submission = $submission;
        return $this;
    }

    public function buildEmailParams(): StageAdvancementEmailBuilder
    {
        $publication = $this->submission->getCurrentPublication();
        $this->primaryAuthor = $publication->getPrimaryAuthor();
        if (!isset($this->primaryAuthor)) {
            $authors = $publication->getData('authors');
            $this->primaryAuthor = $authors->first();
        }

        $this->context = Application::get()->getContextDAO()->getById($this->submission->getData('contextId'));
        $request = Application::get()->getRequest();
        $faqUrl = $request->url($this->context->getPath()) . '/faq';

        $this->emailParams = [
            'authorName' => htmlspecialchars($this->primaryAuthor->getFullName()),
            'faqUrl' => $faqUrl
        ];

        return $this;
    }

    public function build(): Mailable
    {
        $context = Application::get()->getContextDAO()->getById($this->submission->getData('contextId'));
        list($emailTemplateKey, $emailTemplateClass) = $this->getEmailTemplateByModerationStage($this->moderationStage);

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            $emailTemplateKey
        );

        $email = new $emailTemplateClass($context, $this->submission, $this->emailParams);
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
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
        ];

        return $moderationStageEmailTemplateMap[$moderationStage] ?? null;
    }
}
