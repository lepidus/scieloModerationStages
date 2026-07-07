<?php

namespace APP\plugins\generic\scieloModerationStages\tests\helpers;

class FakeCsrfRequest
{
    public function __construct(private bool $csrfIsValid)
    {
    }

    public function checkCSRF(): bool
    {
        return $this->csrfIsValid;
    }
}
