<?php
use PHPUnit\Framework\TestCase;
import ('classes.submission.Submission');
import ('plugins.generic.scieloModerationStages.classes.ModerationStage');

class ModerationStageTest extends TestCase {
    private $submission;
    private $moderationStage;


    public function setUp(): void {
        $this->submission = $this->createSubmission();
        $this->moderationStage = new ModerationStage($this->submission);
    }

    private function createSubmission() {
        $submission = new Submission();
        $submission->setData('currentModerationStage', SCIELO_MODERATION_STAGE_FORMAT);
        return $submission;
    }
    
    public function testGetCurrentStageName(): void {
        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.formatStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getCurrentStageName());
    }


    public function testGetNextStageName(): void {
        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.contentStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getNextStageName());
    }
    
    public function testSendNextStage(): void {
        $this->moderationStage->sendNextStage();
        
        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.contentStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getCurrentStageName());
    }

    public function testPutOnFirstStage(): void {
        $submission = new Submission();
        $moderationStage = new ModerationStage($submission);
        $moderationStage->setToFirstStage();

        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.formatStage');
        $this->assertEquals($expectedStageName, $moderationStage->getCurrentStageName());
    }

    public function testRejectedSubmissionCantAdvanceStage(): void {
        $this->submission->setData('status', STATUS_DECLINED);
        $moderationStage = new ModerationStage($this->submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testPostedSubmissionCantAdvanceStage(): void {
        $this->submission->setData('status', STATUS_PUBLISHED);
        $moderationStage = new ModerationStage($this->submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testSubmissionOnLastStageCantAdvance(): void {
        $this->submission->setData('currentModerationStage', SCIELO_MODERATION_STAGE_AREA);
        $moderationStage = new ModerationStage($this->submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testSubmissionWithoutStageCantAdvance(): void {
        $submission = new Submission();
        $moderationStage = new ModerationStage($submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

}