<?php

use PHPUnit\Framework\TestCase;
use PKP\core\JSONMessage;
use APP\plugins\generic\scieloModerationStages\tests\helpers\TestableModerationStagesHandler;
use APP\plugins\generic\scieloModerationStages\tests\helpers\FakeCsrfRequest;

class ScieloModerationStagesHandlerTest extends TestCase
{
    public function testExhibitDataHidesEditorialDataFromAuthorsEvenWhenClientClaimsOtherwise(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->isAuthor = true;

        $output = json_decode($handler->getSubmissionExhibitData(
            ['submissionId' => 1, 'userIsAuthor' => 0],
            null
        ), true);

        $this->assertArrayNotHasKey('Responsibles', $output);
        $this->assertSame('Some stage', $output['ModerationStage']);
    }

    public function testExhibitDataShowsEditorialDataToEditorialUsers(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->isAuthor = false;

        $output = json_decode($handler->getSubmissionExhibitData(
            ['submissionId' => 1, 'userIsAuthor' => 1],
            null
        ), true);

        $this->assertArrayHasKey('Responsibles', $output);
    }

    public function testUpdateSubmissionStageDataRejectsRequestWithoutValidCsrfToken(): void
    {
        $handler = new TestableModerationStagesHandler();

        $response = $handler->updateSubmissionStageData(['submissionId' => 1], new FakeCsrfRequest(false));

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }
}
