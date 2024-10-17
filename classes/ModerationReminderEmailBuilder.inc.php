<?php

import('lib.pkp.classes.mail.Mail');

class ModerationReminderEmailBuilder
{
    private $context;
    private $moderator;
    private $submissions;
    private $locale;

    public function __construct($context, $moderator, $submissions, $locale)
    {
        $this->context = $context;
        $this->moderator = $moderator;
        $this->submissions = $submissions;
        $this->locale = $locale;
    }

    public function buildEmail(): Mail
    {
        $email = new Mail();

        $email->setFrom($this->context->getContactEmail(), $this->context->getContactName());
        $email->addRecipient($this->moderator->getEmail(), $this->moderator->getFullName());
        $email->addCc($this->context->getContactEmail(), $this->context->getContactName());

        $email->setSubject(__('plugins.generic.scieloModerationStages.emails.moderationReminder.subject', [], $this->locale));

        $bodyParams = [
            'moderatorName' => $this->moderator->getFullName(),
            'submissions' => $this->getSubmissionsString()
        ];
        $email->setBody(__('plugins.generic.scieloModerationStages.emails.moderationReminder.body', $bodyParams, $this->locale));

        return $email;
    }

    private function getSubmissionsString(): string
    {
        $submissionsString = '';
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $preModerationTimeLimit = $plugin->getSetting($request->getContext()->getId(), 'preModerationTimeLimit');

        foreach ($this->submissions as $submission) {
            $submissionLink = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$submission->getId()]);
            $submissionDaysString = $this->getSubmissionDaysString($submission, $preModerationTimeLimit);

            $submissionsString .= "<p><a href=\"$submissionLink\">$submissionLink</a> - $submissionDaysString</p>";
        }

        return $submissionsString;
    }

    private function getSubmissionDaysString($submission, $preModerationTimeLimit): string
    {
        $dateSubmitted = new DateTime($submission->getData('dateSubmitted'));
        $today = new DateTime();
        $daysBetween = (int) $today->diff($dateSubmitted)->format('%a');

        if ($daysBetween < 1) {
            return __('plugins.generic.scieloModerationStages.submissionMade.lessThanADayAgo', [], $this->locale);
        }

        if ($daysBetween == 1) {
            return __('plugins.generic.scieloModerationStages.submissionMade.aDayAgo', [], $this->locale);
        }

        if ($daysBetween > $preModerationTimeLimit) {
            return __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.bold', ['numberOfDays' => $daysBetween], $this->locale);
        }

        return __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.regular', ['numberOfDays' => $daysBetween], $this->locale);
    }
}
