<?php

namespace APP\plugins\generic\scieloModerationStages\classes\mail\mailables;

use PKP\mail\Mailable;
use APP\server\Server;
use APP\submission\Submission;
use PKP\mail\traits\Configurable;

class SentToTypeModerationStage extends Mailable
{
    use Configurable;

    protected static ?string $name = 'emails.sentToTypeModerationStage.name';
    protected static ?string $description = 'emails.sentToTypeModerationStage.description';
    protected static ?string $emailTemplateKey = 'SENT_TO_TYPE_MODERATION_STAGE';

    public function __construct(Server $context, Submission $submission, array $variables)
    {
        parent::__construct([$context, $submission]);
        $this->addData($variables);
    }
}
