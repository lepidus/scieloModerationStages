<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.security.Role');
import('lib.pkp.classes.core.JSONMessage');
import('classes.submission.Submission');
import('plugins.generic.scieloModerationStages.tests.helpers.FakeModerationStagesRequest');
import('plugins.generic.scieloModerationStages.tests.helpers.TestableModerationStagesHandler');

class ScieloModerationStagesHandlerTest extends TestCase
{
    public function testUpdateSubmissionStageDataRejectsRequestWithoutValidCsrfToken(): void
    {
        $handler = new TestableModerationStagesHandler();

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'sendNextStage' => 1],
            new FakeModerationStagesRequest(true, false)
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
        $this->assertFalse($handler->submissionWasLoaded);
    }

    public function testUpdateSubmissionStageDataRejectsGetRequest(): void
    {
        $handler = new TestableModerationStagesHandler();

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'sendNextStage' => 1],
            new FakeModerationStagesRequest(false, true)
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
        $this->assertFalse($handler->submissionWasLoaded);
    }

    public function testStageDataUpdateIsRestrictedToEditorialManagers(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach ([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR] as $roleId) {
            $this->assertContains('updateSubmissionStageData', $handler->getRoleAssignment($roleId));
        }
        foreach ([ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_READER] as $roleId) {
            $this->assertNotContains('updateSubmissionStageData', (array) $handler->getRoleAssignment($roleId));
        }
    }

    public function testReminderBodyIsRestrictedToManagers(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach ([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER] as $roleId) {
            $this->assertContains('getReminderBody', $handler->getRoleAssignment($roleId));
        }
        foreach ([ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_READER] as $roleId) {
            $this->assertNotContains('getReminderBody', (array) $handler->getRoleAssignment($roleId));
        }
    }

    public function testAuthorOnlyReachesReadOperations(): void
    {
        $handler = new TestableModerationStagesHandler();

        $authorOperations = $handler->getRoleAssignment(ROLE_ID_AUTHOR);
        sort($authorOperations);

        $this->assertSame(['getSubmissionExhibitData', 'getUserIsAuthor'], $authorOperations);
    }

    public function testExhibitDataIgnoresClientSuppliedAuthorFlag(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userIsEditorial = false;
        $handler->scopedSubmissionId = 7;

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 99, 'userIsAuthor' => 0], null), true);

        $this->assertSame(7, $response['submissionId']);
        $this->assertArrayNotHasKey('Responsibles', $response);
    }

    public function testExhibitDataIncludesEditorialDataForEditorialUsers(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userIsEditorial = true;

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1, 'userIsAuthor' => 1], null), true);

        $this->assertSame('Responsible: Jane Doe', $response['Responsibles']);
    }
}
