<?php

use PHPUnit\Framework\TestCase;

import('classes.journal.Journal');
import('lib.pkp.classes.user.User');
import('classes.submission.Submission');
import('classes.publication.Publication');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderEmailBuilder');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');

class ModerationReminderEmailBuilderTest extends TestCase
{
    private $locale = 'en_US';
    private $context;
    private $moderator;
    private $submissions;
    private $moderationTimeLimit = 2;
    private $moderationReminderEmailBuilder;

    public function setUp(): void
    {
        $this->context = $this->createTestContext();
        $this->moderator = $this->createModeratorUser();
        $this->submissions = $this->createTestSubmissions();
        $this->moderationReminderEmailBuilder = new ModerationReminderEmailBuilder(
            $this->context,
            $this->moderator,
            $this->submissions,
            $this->locale,
            REMINDER_TYPE_PRE_MODERATION,
            $this->moderationTimeLimit
        );
        $mockedModerationStageDao = $this->crateMockModerationStageDao();
        $this->moderationReminderEmailBuilder->setModerationStageDao($mockedModerationStageDao);
    }

    private function createTestContext()
    {
        $context = new Journal();
        $context->setData('contactName', 'Example contact');
        $context->setData('contactEmail', 'example.contact@gmail.com');

        return $context;
    }

    private function createModeratorUser(): User
    {
        $moderator = new User();
        $moderator->setData('email', 'juancarlo.rodriguez@gmail.com');
        $moderator->setData('givenName', 'Juan Carlo', $this->locale);
        $moderator->setData('familyName', 'Rodriguez', $this->locale);

        return $moderator;
    }

    private function createTestSubmissions(): array
    {
        $fiveDaysAgo = new DateTime();
        $threeDaysAgo = new DateTime();
        $today = new DateTime();
        $fiveDaysAgo = $fiveDaysAgo->modify('-5 days')->format('Y-m-d H:i:s');
        $threeDaysAgo = $threeDaysAgo->modify('-3 days')->format('Y-m-d H:i:s');
        $today = $today->format('Y-m-d') . ' 00:00:00';

        $firstSubmission = new Submission();
        $firstSubmission->setAllData([
            'id' => 123,
            'dateSubmitted' => $fiveDaysAgo
        ]);

        $secondSubmission = new Submission();
        $secondSubmission->setAllData([
            'id' => 124,
            'dateSubmitted' => $threeDaysAgo
        ]);

        $thirdSubmission = new Submission();
        $thirdSubmission->setAllData([
            'id' => 125,
            'dateSubmitted' => $today
        ]);

        return [$firstSubmission, $secondSubmission, $thirdSubmission];
    }

    private function crateMockModerationStageDao()
    {
        $threeDaysAgo = new DateTime();
        $yesterday = new DateTime();
        $today = new DateTime();
        $threeDaysAgo = $threeDaysAgo->modify('-3 days')->format('Y-m-d H:i:s');
        $yesterday = $yesterday->modify('-1 days')->format('Y-m-d H:i:s');
        $today = $today->format('Y-m-d') . ' 00:00:00';

        $mockModerationStageDao = $this->getMockBuilder(ModerationStageDAO::class)
            ->setMethods(['getDateOfUserAssignment'])
            ->getMock();
        $mockModerationStageDao->expects($this->any())
            ->method('getDateOfUserAssignment')
            ->will($this->onConsecutiveCalls($yesterday, $threeDaysAgo, $today));

        return $mockModerationStageDao;
    }

    private function getSubmissionsString($reminderType): string
    {
        $request = Application::get()->getRequest();
        $dispatcher = Application::get()->getDispatcher();
        $request->setDispatcher($dispatcher);

        $firstSubmissionUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$this->submissions[0]->getId()]);
        $secondSubmissionUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$this->submissions[1]->getId()]);
        $thirdSubmissionUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'workflow', 'access', [$this->submissions[2]->getId()]);

        $firstSubmissionFontWeight = ($reminderType == REMINDER_TYPE_PRE_MODERATION ? 'bold' : 'regular');
        $firstSubmissionDaysCount = __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.' . $firstSubmissionFontWeight, ['numberOfDays' => 5]);
        $secondSubmissionDaysCount = __('plugins.generic.scieloModerationStages.submissionMade.nDaysAgo.bold', ['numberOfDays' => 3]);
        $thirdSubmissionDaysCount = __('plugins.generic.scieloModerationStages.submissionMade.lessThanADayAgo');

        $submissionsString = "<p><a href=\"$firstSubmissionUrl\">$firstSubmissionUrl</a> - $firstSubmissionDaysCount</p>";
        $submissionsString .= "<p><a href=\"$secondSubmissionUrl\">$secondSubmissionUrl</a> - $secondSubmissionDaysCount</p>";
        $submissionsString .= "<p><a href=\"$thirdSubmissionUrl\">$thirdSubmissionUrl</a> - $thirdSubmissionDaysCount</p>";

        return $submissionsString;
    }

    public function testPreModerationReminderEmailBuilting(): void
    {
        $email = $this->moderationReminderEmailBuilder->buildEmail();

        $expectedFrom = ['name' => $this->context->getContactName(), 'email' => $this->context->getContactEmail()];
        $this->assertEquals($expectedFrom, $email->getData('from'));

        $expectedRecipients = [['name' => $this->moderator->getFullName(), 'email' => $this->moderator->getEmail()]];
        $this->assertEquals($expectedRecipients, $email->getData('recipients'));

        $expectedCc = [['name' => $this->context->getContactName(), 'email' => $this->context->getContactEmail()]];
        $this->assertEquals($expectedCc, $email->getData('ccs'));

        $expectedSubject = __('plugins.generic.scieloModerationStages.emails.preModerationReminder.subject');
        $this->assertEquals($expectedSubject, $email->getData('subject'));

        $bodyParams = [
            'moderatorName' => $this->moderator->getFullName(),
            'submissions' => $this->getSubmissionsString(REMINDER_TYPE_PRE_MODERATION)
        ];
        $expectedBody = __('plugins.generic.scieloModerationStages.emails.preModerationReminder.body', $bodyParams);
        $this->assertEquals($expectedBody, $email->getData('body'));
    }

    public function testAreaModerationReminderEmailBuilting(): void
    {
        $this->moderationReminderEmailBuilder->setReminderType(REMINDER_TYPE_AREA_MODERATION);

        $email = $this->moderationReminderEmailBuilder->buildEmail();

        $expectedFrom = ['name' => $this->context->getContactName(), 'email' => $this->context->getContactEmail()];
        $this->assertEquals($expectedFrom, $email->getData('from'));

        $expectedRecipients = [['name' => $this->moderator->getFullName(), 'email' => $this->moderator->getEmail()]];
        $this->assertEquals($expectedRecipients, $email->getData('recipients'));

        $expectedCc = [['name' => $this->context->getContactName(), 'email' => $this->context->getContactEmail()]];
        $this->assertEquals($expectedCc, $email->getData('ccs'));

        $expectedSubject = __('plugins.generic.scieloModerationStages.emails.areaModerationReminder.subject');
        $this->assertEquals($expectedSubject, $email->getData('subject'));

        $bodyParams = [
            'moderatorName' => $this->moderator->getFullName(),
            'submissions' => $this->getSubmissionsString(REMINDER_TYPE_AREA_MODERATION)
        ];
        $expectedBody = __('plugins.generic.scieloModerationStages.emails.areaModerationReminder.body', $bodyParams);
        $this->assertEquals($expectedBody, $email->getData('body'));
    }
}
