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

    public function testModerationReminderEmailBuilting(): void
    {
        $email = $this->moderationReminderEmailBuilder->buildEmail();

        $this->assertEquals(
            __('plugins.generic.scieloModerationStages.emails.moderationReminder.subject'),
            $email->getData('subject')
        );

        $this->assertTrue(str_contains($email->getData('body'), '/access/123'));
        $this->assertTrue(str_contains($email->getData('body'), __('plugins.generic.scieloModerationStages.submissionMade.lessThanADayAgo')));
        $this->assertTrue(str_contains($email->getData('body'), '/access/124'));
        $this->assertTrue(str_contains($email->getData('body'), __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo')));
    }
}
