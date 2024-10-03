<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.stageAssignment.StageAssignment');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

class ModerationReminderHelperTest extends TestCase
{
    private $moderationReminderHelper;

    public function setUp(): void
    {
        $this->moderationReminderHelper = new ModerationReminderHelper();
    }

    private function createTestAssignments(): array
    {
        $firstAssignment = new StageAssignment();
        $firstAssignment->setData('submissionId', 256);
        $firstAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        $secondAssignment = new StageAssignment();
        $secondAssignment->setData('submissionId', 257);
        $secondAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        $thirdAssignment = new StageAssignment();
        $thirdAssignment->setData('submissionId', 258);
        $thirdAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        return [$firstAssignment, $secondAssignment, $thirdAssignment];
    }

    private function createModerationStageDaoMock()
    {
        $mockedDAO = $this->createMock(ModerationStageDAO::class);
        $mockedDAO->method('getSubmissionModerationStage')->willReturnMap([
            [256, SCIELO_MODERATION_STAGE_CONTENT],
            [257, SCIELO_MODERATION_STAGE_FORMAT],
            [258, SCIELO_MODERATION_STAGE_CONTENT]
        ]);

        return $mockedDAO;
    }

    public function testFilterPreModerationAssignments(): void
    {
        $assignments = $this->createTestAssignments();
        $mockedModerationStageDao = $this->createModerationStageDaoMock();
        $this->moderationReminderHelper->setModerationStageDao($mockedModerationStageDao);

        $expectedFilteredAssignments = [$assignments[0], $assignments[2]];
        $filteredAssignments = $this->moderationReminderHelper->filterPreModerationAssignments($assignments);

        $this->assertEquals($expectedFilteredAssignments, $filteredAssignments);
    }
}
