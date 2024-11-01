<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use DateTime;
use PKP\mail\Mailable;
use APP\core\Application;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;

class ModerationReminderEmailBuilder
{
    public const REMINDER_TYPE_PRE_MODERATION = 'preModeration';
    public const REMINDER_TYPE_AREA_MODERATION = 'areaModeration';

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

    public function buildEmail(): Mailable
    {
        $reminderTemplateName = ($this->reminderType == self::REMINDER_TYPE_PRE_MODERATION ? 'preModerationReminder' : 'areaModerationReminder');
        $bodyParams = [
            'moderatorName' => $this->moderator->getFullName(),
            'submissions' => $this->getSubmissionsString()
        ];

        $email = new Mailable();
        $email
            ->from($this->context->getContactEmail(), $this->context->getContactName())
            ->to($this->moderator->getEmail(), $this->moderator->getFullName())
            ->cc($this->context->getContactEmail(), $this->context->getContactName())
            ->subject(__("plugins.generic.scieloModerationStages.emails.$reminderTemplateName.subject", [], $this->locale))
            ->body(__("plugins.generic.scieloModerationStages.emails.$reminderTemplateName.body", $bodyParams, $this->locale));

        return $email;
    }

    private function getSubmissionsString(): string
    {
        $submissionsString = '';
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        foreach ($this->submissions as $submission) {
            $submissionLink = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'workflow', 'access', [$submission->getId()]);
            $submissionDaysString = $this->getSubmissionDaysString($submission);

            $submissionsString .= "<p><a href=\"$submissionLink\">$submissionLink</a> - $submissionDaysString</p>";
        }

        return $submissionsString;
    }

    private function getSubmissionDaysString($submission): string
    {
        $dateSubmitted = new DateTime($submission->getData('dateSubmitted'));
        $dateModeratorAssigned = new DateTime($this->moderationStageDao->getDateOfUserAssignment($this->moderator->getId(), $submission->getId()));

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
            ($this->reminderType == self::REMINDER_TYPE_PRE_MODERATION && $daysSinceSubmission > $this->moderationTimeLimit)
            || ($this->reminderType == self::REMINDER_TYPE_AREA_MODERATION && $daysSinceAssignment > $this->moderationTimeLimit)
        ) {
            return __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.bold', ['numberOfDays' => $daysSinceSubmission], $this->locale);
        }

        return __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.regular', ['numberOfDays' => $daysSinceSubmission], $this->locale);
    }
}
