<?php

use PHPUnit\Framework\TestCase;
use PKP\core\JSONMessage;
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
}
