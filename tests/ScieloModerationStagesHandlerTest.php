<?php

use PHPUnit\Framework\TestCase;
use PKP\core\JSONMessage;
use PKP\security\Role;
use APP\plugins\generic\scieloModerationStages\tests\helpers\TestableModerationStagesHandler;
use APP\plugins\generic\scieloModerationStages\tests\helpers\FakeCsrfRequest;

class ScieloModerationStagesHandlerTest extends TestCase
{
    public function testUpdateSubmissionStageDataRejectsRequestWithoutValidCsrfToken(): void
    {
        $handler = new TestableModerationStagesHandler();

        $response = $handler->updateSubmissionStageData(['submissionId' => 1], new FakeCsrfRequest(false));

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testUpdateSubmissionStageDataRejectsInvalidEntryDate(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach (['2024-02-30', '2024-01-01T00:00', '<b>x</b>', ['2024-01-01']] as $invalidDate) {
            $response = $handler->updateSubmissionStageData(
                ['submissionId' => 1, 'formatStageEntryDate' => $invalidDate],
                new FakeCsrfRequest(true)
            );

            $this->assertInstanceOf(JSONMessage::class, $response);
            $this->assertFalse($response->getStatus());
        }
    }

    public function testStageDataUpdateIsRestrictedToEditorialManagers(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach ([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR] as $roleId) {
            $this->assertContains('updateSubmissionStageData', $handler->getRoleAssignment($roleId));
        }
        foreach ([Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_READER] as $roleId) {
            $this->assertNotContains('updateSubmissionStageData', (array) $handler->getRoleAssignment($roleId));
        }
    }

    public function testReminderBodyIsRestrictedToManagers(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach ([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER] as $roleId) {
            $this->assertContains('getReminderBody', $handler->getRoleAssignment($roleId));
        }
        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER] as $roleId) {
            $this->assertNotContains('getReminderBody', (array) $handler->getRoleAssignment($roleId));
        }
    }

    public function testAssistantKeepsReadAccessToExhibitData(): void
    {
        $handler = new TestableModerationStagesHandler();

        $this->assertSame(['getSubmissionExhibitData'], $handler->getRoleAssignment(Role::ROLE_ID_ASSISTANT));
        $this->assertSame(['getSubmissionExhibitData'], $handler->getRoleAssignment(Role::ROLE_ID_AUTHOR));
    }

    public function testEditorialAccessIsDecidedByRolesOnTheSubmission(): void
    {
        $this->assertFalse(TestableModerationStagesHandler::stagesGrantEditorialAccess([5 => [Role::ROLE_ID_AUTHOR]]));
        $this->assertFalse(TestableModerationStagesHandler::stagesGrantEditorialAccess([]));
        $this->assertTrue(TestableModerationStagesHandler::stagesGrantEditorialAccess([5 => [Role::ROLE_ID_SUB_EDITOR]]));
        $this->assertTrue(TestableModerationStagesHandler::stagesGrantEditorialAccess([5 => [Role::ROLE_ID_MANAGER]]));
    }

    public function testUpdateSubmissionStageDataRejectsServerEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->accessibleWorkflowStages = [5 => [Role::ROLE_ID_AUTHOR]];

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'stageChangeAction' => 'advance', 'formatStageEntryDate' => '2020-01-01'],
            new FakeCsrfRequest(true)
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testStageDataUpdateRequiresUpdateRoleOnTheSubmission(): void
    {
        $roles = TestableModerationStagesHandler::STAGE_DATA_UPDATE_ROLES;

        $this->assertFalse(TestableModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_AUTHOR]], $roles));
        $this->assertFalse(TestableModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_ASSISTANT]], $roles));
        $this->assertTrue(TestableModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_SUB_EDITOR]], $roles));
        $this->assertTrue(TestableModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_MANAGER]], $roles));
    }
}
