<?php

use PHPUnit\Framework\TestCase;
use PKP\security\Role;
use APP\plugins\generic\scieloModerationStages\classes\dispatchers\WorkflowDispatcher;

/**
 * Accessible workflow stages as returned by the core getAccessibleWorkflowStages.
 */
class WorkflowDispatcherTest extends TestCase
{
    public function testTabControlsAreHiddenFromManagerAssignedOnlyAsAuthor(): void
    {
        $this->assertFalse(WorkflowDispatcher::stagesGrantStageDataUpdate([5 => [Role::ROLE_ID_AUTHOR]]));
    }

    public function testTabControlsAreShownToManagerAlsoAssignedAsManagerOrModerator(): void
    {
        $this->assertTrue(WorkflowDispatcher::stagesGrantStageDataUpdate([5 => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_MANAGER]]));
        $this->assertTrue(WorkflowDispatcher::stagesGrantStageDataUpdate([5 => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR]]));
    }

    public function testTabControlsAreShownToUnassignedManagerAndAdmin(): void
    {
        $this->assertTrue(WorkflowDispatcher::stagesGrantStageDataUpdate([1 => [Role::ROLE_ID_MANAGER], 5 => [Role::ROLE_ID_MANAGER]]));
        $this->assertTrue(WorkflowDispatcher::stagesGrantStageDataUpdate([1 => [Role::ROLE_ID_SITE_ADMIN], 5 => [Role::ROLE_ID_SITE_ADMIN]]));
    }

    public function testTabControlsFollowSubEditorAssignment(): void
    {
        $this->assertFalse(WorkflowDispatcher::stagesGrantStageDataUpdate([5 => [Role::ROLE_ID_AUTHOR]]));
        $this->assertTrue(WorkflowDispatcher::stagesGrantStageDataUpdate([5 => [Role::ROLE_ID_SUB_EDITOR]]));
        $this->assertFalse(WorkflowDispatcher::stagesGrantStageDataUpdate([5 => [Role::ROLE_ID_ASSISTANT]]));
        $this->assertFalse(WorkflowDispatcher::stagesGrantStageDataUpdate([]));
    }
}
