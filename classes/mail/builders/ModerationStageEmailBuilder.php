<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\builders;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\mail\Mailable;

abstract class ModerationStageEmailBuilder
{
    protected $context;
    protected $submission;
    protected $primaryAuthor;
    protected $emailParams;

    public function setSubmission(Submission $submission): static
    {
        $this->submission = $submission;
        return $this;
    }

    public function setContext($context): static
    {
        $this->context = $context;
        return $this;
    }

    public function buildEmailParams(): static
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

        $this->emailParams = [
            'authorName' => htmlspecialchars($this->primaryAuthor->getFullName()),
            'faqUrl' => $faqUrl,
        ];

        return $this;
    }

    abstract public function build(): Mailable;

    protected function getEmailTemplate(string $emailTemplateKey)
    {
        return Repo::emailTemplate()->getByKey($this->context->getId(), $emailTemplateKey);
    }
}
