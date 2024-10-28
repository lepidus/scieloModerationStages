<?php

import('lib.pkp.classes.mail.Mail');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');

define('REMINDER_TYPE_PRE_MODERATION', 'moderation');
define('REMINDER_TYPE_AREA_MODERATION', 'areaModeration');

class ModerationReminderEmailBuilder
{
    private $context;
    private $moderator;
    private $submissions;
    private $locale;
    private $reminderType;
    private $moderationTimeLimit;
    private $moderationStageDao;

    public function __construct($context, $moderator, $submissions, $locale, $reminderType, $moderationTimeLimit)
    {
        $this->context = $context;
        $this->moderator = $moderator;
        $this->submissions = $submissions;
        $this->locale = $locale;
        $this->reminderType = $reminderType;
        $this->moderationTimeLimit = $moderationTimeLimit;

        $this->moderationStageDao = new ModerationStageDAO();
    }

    public function setModerationStageDao($moderationStageDao)
    {
        $this->moderationStageDao = $moderationStageDao;
    }
    public function setReminderType($reminderType)
    {
        $this->reminderType = $reminderType;
    }

    public function buildEmail(): Mail
    {
        $reminderTemplateName = ($this->reminderType == REMINDER_TYPE_PRE_MODERATION ? 'preModerationReminder' : 'areaModerationReminder');
        $email = new Mail();

        $email->setFrom($this->context->getContactEmail(), $this->context->getContactName());
        $email->addRecipient($this->moderator->getEmail(), $this->moderator->getFullName());
        $email->addCc($this->context->getContactEmail(), $this->context->getContactName());

        $email->setSubject(__("plugins.generic.scieloModerationStages.emails.$reminderTemplateName.subject", [], $this->locale));

        $bodyParams = [
            'moderatorName' => $this->moderator->getFullName(),
            'submissions' => $this->getSubmissionsString()
        ];
        $email->setBody(__("plugins.generic.scieloModerationStages.emails.$reminderTemplateName.body", $bodyParams, $this->locale));

        return $email;
    }

    private function getSubmissionsString(): string
    {
        $submissionsString = '';
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        foreach ($this->submissions as $submission) {
            $submissionLink = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$submission->getId()]);
            $submissionDaysString = $this->getSubmissionDaysString($submission);

            $submissionsString .= "<p><a href=\"$submissionLink\">$submissionLink</a> - $submissionDaysString</p>";
        }

        return $submissionsString;
    }

    private function getSubmissionDaysString($submission): string
    {
        $dateSubmitted = new DateTime($submission->getData('dateSubmitted'));
        $dateModeratorAssigned = new DateTime($this->moderationStageDao->getDateOfUserAssignment($this->moderator, $submission->getId()));

        $today = new DateTime();
        $daysSinceSubmission = (int) $today->diff($dateSubmitted)->format('%a');
        $daysSinceAssignment = (int) $today->diff($dateModeratorAssigned)->format('%a');

        if ($daysSinceSubmission < 1) {
            return __('plugins.generic.scieloModerationStages.submissionMade.lessThanADayAgo', [], $this->locale);
        }

        if ($daysSinceSubmission == 1) {
            return __('plugins.generic.scieloModerationStages.submissionMade.aDayAgo', [], $this->locale);
        }

        if (
            ($this->reminderType == REMINDER_TYPE_PRE_MODERATION && $daysSinceSubmission > $this->moderationTimeLimit)
            || ($this->reminderType == REMINDER_TYPE_AREA_MODERATION && $daysSinceAssignment > $this->moderationTimeLimit)
        ) {
            return __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.bold', ['numberOfDays' => $daysSinceSubmission], $this->locale);
        }

        return __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.regular', ['numberOfDays' => $daysSinceSubmission], $this->locale);
    }
}
