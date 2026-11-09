<?php

use PHPUnit\Framework\TestCase;
use PKP\security\Role;
use APP\plugins\generic\scieloModerationStages\classes\dispatchers\WorkflowDispatcher;

class WorkflowDispatcherTest extends TestCase
{
    public function testManagerWhoIsAlsoAuthorOfSubmissionCanUpdateStageData(): void
    {
        $this->assertTrue(WorkflowDispatcher::rolesGrantStageDataUpdate(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR],
            [5 => [Role::ROLE_ID_AUTHOR]]
        ));
    }

    public function testSiteAdminCanUpdateStageData(): void
    {
        $this->assertTrue(WorkflowDispatcher::rolesGrantStageDataUpdate([Role::ROLE_ID_SITE_ADMIN], []));
        $this->assertTrue(WorkflowDispatcher::rolesGrantStageDataUpdate(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_AUTHOR],
            [5 => [Role::ROLE_ID_AUTHOR]]
        ));
    }

    public function testSubEditorWhoIsOnlyAuthorOfSubmissionCannotUpdateStageData(): void
    {
        $this->assertFalse(WorkflowDispatcher::rolesGrantStageDataUpdate(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR],
            [5 => [Role::ROLE_ID_AUTHOR]]
        ));
        $this->assertFalse(WorkflowDispatcher::rolesGrantStageDataUpdate([Role::ROLE_ID_SUB_EDITOR], []));
    }

    public function testAssignedSubEditorCanUpdateStageData(): void
    {
        $this->assertTrue(WorkflowDispatcher::rolesGrantStageDataUpdate(
            [Role::ROLE_ID_SUB_EDITOR],
            [5 => [Role::ROLE_ID_SUB_EDITOR]]
        ));
    }

    public function testAssistantCannotUpdateStageData(): void
    {
        $this->assertFalse(WorkflowDispatcher::rolesGrantStageDataUpdate(
            [Role::ROLE_ID_ASSISTANT],
            [5 => [Role::ROLE_ID_ASSISTANT]]
        ));
    }
}
