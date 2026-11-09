<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.security.Role');
import('plugins.generic.scieloModerationStages.ScieloModerationStagesPlugin');

class ScieloModerationStagesPluginTest extends TestCase
{
    public function testWorkflowTabAllowsStageDataUpdateToManagerWhoIsAlsoAuthor(): void
    {
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_MANAGER, ROLE_ID_AUTHOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]]
        ));
    }

    public function testWorkflowTabAllowsStageDataUpdateToSiteAdmin(): void
    {
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate([ROLE_ID_SITE_ADMIN], []));
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_SITE_ADMIN, ROLE_ID_AUTHOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]]
        ));
    }

    public function testWorkflowTabDeniesStageDataUpdateToSubEditorWhoIsOnlyAuthor(): void
    {
        $this->assertFalse(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]]
        ));
        $this->assertFalse(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_ASSISTANT],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_ASSISTANT]]
        ));
    }

    public function testWorkflowTabAllowsStageDataUpdateToAssignedSubEditor(): void
    {
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_SUB_EDITOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_SUB_EDITOR]]
        ));
    }
}
