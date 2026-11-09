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
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]];
        $handler->scopedSubmissionId = 7;

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 99, 'userIsAuthor' => 0], null), true);

        $this->assertSame(7, $response['submissionId']);
        $this->assertArrayNotHasKey('Responsibles', $response);
    }

    public function testExhibitDataIsHiddenFromServerEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userIsEditorial = true;
        $handler->userRoles = [ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]];

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1, 'userIsAuthor' => 0], null), true);

        $this->assertArrayNotHasKey('Responsibles', $response);
    }

    public function testExhibitDataIncludesEditorialDataForAssignedEditor(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [ROLE_ID_SUB_EDITOR];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_SUB_EDITOR]];

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1, 'userIsAuthor' => 1], null), true);

        $this->assertSame('Responsible: Jane Doe', $response['Responsibles']);
    }

    public function testExhibitDataIncludesEditorialDataForSiteAdmin(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [ROLE_ID_SITE_ADMIN];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_SITE_ADMIN]];

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1], null), true);

        $this->assertSame('Responsible: Jane Doe', $response['Responsibles']);
    }

    public function testExhibitDataIncludesEditorialDataForSiteAdminWhoIsAlsoAuthorOfSubmission(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [ROLE_ID_SITE_ADMIN, ROLE_ID_AUTHOR];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]];

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1], null), true);

        $this->assertSame('Responsible: Jane Doe', $response['Responsibles']);
    }

    public function testExhibitDataIncludesEditorialDataForManagerWhoIsAlsoAuthorOfSubmission(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [ROLE_ID_MANAGER, ROLE_ID_AUTHOR];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]];

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1], null), true);

        $this->assertSame('Responsible: Jane Doe', $response['Responsibles']);
    }

    public function testStageDataUpdateIsAllowedToManagersAndAdminsWhoAreAlsoAuthors(): void
    {
        $roles = ScieloModerationStagesHandler::STAGE_DATA_UPDATE_ROLES;
        $authorStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]];

        $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess([ROLE_ID_MANAGER, ROLE_ID_AUTHOR], $authorStages, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess([ROLE_ID_SITE_ADMIN], [], $roles));
        $this->assertFalse(ScieloModerationStagesHandler::rolesGrantSubmissionAccess([ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR], $authorStages, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
            [ROLE_ID_SUB_EDITOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_SUB_EDITOR]],
            $roles
        ));
    }

    public function testUpdateSubmissionStageDataRejectsServerEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]];

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'sendNextStage' => 1, 'formatStageEntryDate' => '2020-01-01'],
            new FakeModerationStagesRequest(true, true)
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testStageDataUpdateRequiresUpdateRoleOnTheSubmission(): void
    {
        $roles = ScieloModerationStagesHandler::STAGE_DATA_UPDATE_ROLES;

        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole([WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]], $roles));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole([WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_ASSISTANT]], $roles));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole([], $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole([WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_SUB_EDITOR]], $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole([WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_MANAGER]], $roles));
    }

    public function testExhibitDataIncludesEditorialDataForUnassignedManager(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [ROLE_ID_MANAGER];
        $handler->accessibleWorkflowStages = [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_MANAGER]];

        $response = json_decode($handler->getSubmissionExhibitData(['submissionId' => 1], null), true);

        $this->assertSame('Responsible: Jane Doe', $response['Responsibles']);
    }
}
