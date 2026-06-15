<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;

class ReturnedToModerationStage extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.returnedToModerationStage.name';
    protected static ?string $description = 'emails.returnedToModerationStage.description';
    protected static ?string $emailTemplateKey = 'RETURNED_TO_MODERATION_STAGE';

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
