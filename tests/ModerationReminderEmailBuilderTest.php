<?php

use PHPUnit\Framework\TestCase;

import('classes.submission.Submission');
import('classes.publication.Publication');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderEmailBuilder');

class ModerationReminderEmailBuilderTest extends TestCase
{
    private $submissions;
    private $moderationReminderEmailBuilder;

    public function setUp(): void
    {
        $this->submissions = $this->createTestSubmissions();
        $this->moderationReminderEmailBuilder = new ModerationReminderEmailBuilder($this->submissions);
    }

    private function createTestSubmissions(): array
    {
        $threeDaysAgo = $today = new DateTime();
        $threeDaysAgo->modify('-3 days');
        $threeDaysAgo = $threeDaysAgo->format('Y-m-d H:i:s');
        $today = $today->format('Y-m-d') . ' 00:00:00';

        $firstSubmission = new Submission();
        $firstSubmission->setAllData([
            'id' => 123,
            'dateSubmitted' => $threeDaysAgo
        ]);

        $secondSubmission = new Submission();
        $secondSubmission->setAllData([
            'id' => 124,
            'dateSubmitted' => $today
        ]);

        return [$firstSubmission, $secondSubmission];
    }

    private function getSubmissionsString(): string
    {
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        $submissionsString = '<p>' . $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$this->submissions[0]->getId()]);
        $submissionsString .= ' - ' . __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo', ['numberOfDays' => 3]) . '</p>';

        $submissionsString .= '<p>' . $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$this->submissions[1]->getId()]);
        $submissionsString .= ' - ' . __('plugins.generic.scieloModerationStages.submissionMade.lessThanADayAgo') . '</p>';

        return $submissionsString;
    }

    public function testModerationReminderEmailBuilting(): void
    {
        $email = $this->moderationReminderEmailBuilder->buildEmail();

        $expectedSubject = __('plugins.generic.scieloModerationStages.emails.moderationReminder.subject');
        $this->assertEquals($expectedSubject, $email->getData('subject'));

        $bodyParams = [
            'moderatorName' => 'Juan Carlo',
            'submissions' => $this->getSubmissionsString()
        ];
        $expectedBody = __('plugins.generic.scieloModerationStages.emails.moderationReminder.body', $bodyParams);
        $this->assertEquals($expectedBody, $email->getData('body'));
    }
}
