<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\builders;

use APP\core\Application;
use APP\submission\Submission;
use PKP\mail\Mailable;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class StageRegressionEmailBuilder
{
    private $context;
    private $submission;
    private $primaryAuthor;
    private $emailParams;

    public function setSubmission(Submission $submission): StageRegressionEmailBuilder
    {
        $this->submission = $submission;
        return $this;
    }

    public function setContext($context): StageRegressionEmailBuilder
    {
        $this->context = $context;
        return $this;
    }

    public function buildEmailParams(): StageRegressionEmailBuilder
    {
        $publication = $this->submission->getCurrentPublication();
        $this->primaryAuthor = $publication->getPrimaryAuthor();
        if (!isset($this->primaryAuthor)) {
            $authors = $publication->getData('authors');
            $this->primaryAuthor = $authors->first();
        }

        $this->context = $this->context
            ?? Application::get()->getContextDAO()->getById($this->submission->getData('contextId'));
        $request = Application::get()->getRequest();
        $faqUrl = $request->url($this->context->getPath()) . '/faq';

        $moderationStage = new ModerationStage($this->submission);

        $this->emailParams = [
            'authorName' => htmlspecialchars($this->primaryAuthor->getFullName()),
            'moderationStageName' => $moderationStage->getCurrentStageName(),
            'faqUrl' => $faqUrl,
        ];

        return $this;
    }

    public function build(): Mailable
    {
        $email = new Mailable();
        $email->from($this->context->getData('contactEmail'), $this->context->getData('contactName'))
            ->to($this->primaryAuthor->getEmail(), $this->primaryAuthor->getFullName())
            ->subject(__('plugins.generic.scieloModerationStages.emails.stageRegression.subject'))
            ->body(__('plugins.generic.scieloModerationStages.emails.stageRegression.body', $this->emailParams));

        return $email;
    }
}
