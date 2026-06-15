<?php

use PHPUnit\Framework\TestCase;
use APP\core\Application;
use APP\server\Server;
use APP\author\Author;
use APP\submission\Submission;
use APP\publication\Publication;
use PKP\core\PKPPageRouter;
use PKP\emailTemplate\EmailTemplate;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageRegressionEmailBuilder;

class StageRegressionEmailBuilderTest extends TestCase
{
    private $locale = 'en';
    private $context;
    private $primaryAuthor;
    private $submission;
    private $emailTemplate;

    public function setUp(): void
    {
        $request = Application::get()->getRequest();
        if (is_null($request->getRouter())) {
            $router = new PKPPageRouter();
            $router->setApplication(Application::get());
            $request->setRouter($router);
        }

        $this->context = $this->createTestContext();
        $this->primaryAuthor = $this->createPrimaryAuthor();
        $this->submission = $this->createTestSubmission();
        $this->emailTemplate = $this->createTestEmailTemplate();
    }

    private function createTestContext()
    {
        $context = new Server();
        $context->setData('path', 'publicknowledge');
        $context->setData('contactName', 'Example contact');
        $context->setData('contactEmail', 'example.contact@gmail.com');

        return $context;
    }

    private function createPrimaryAuthor(): Author
    {
        $author = new Author();
        $author->setData('id', 31);
        $author->setData('locale', $this->locale);
        $author->setData('email', 'caligari@gmail.com');
        $author->setData('givenName', 'Doutor', $this->locale);
        $author->setData('familyName', 'Caligari & Cesare', $this->locale);

        return $author;
    }

    private function createTestSubmission(): Submission
    {
        $publication = new Publication();
        $publication->setAllData([
            'id' => 1,
            'title' => [$this->locale => 'The Cabinet of Dr. Caligari'],
            'primaryContactId' => $this->primaryAuthor->getId(),
            'authors' => [$this->primaryAuthor],
        ]);

        $submission = new Submission();
        $submission->setAllData([
            'id' => 123,
            'currentPublicationId' => 1,
            'publications' => [$publication],
            'currentModerationStage' => ModerationStage::SCIELO_MODERATION_STAGE_FORMAT,
        ]);

        return $submission;
    }

    private function createTestEmailTemplate(): EmailTemplate
    {
        $emailTemplate = new EmailTemplate();
        $emailTemplate->setData('subject', __('emails.returnedToModerationStage.subject'), $this->locale);
        $emailTemplate->setData('body', __('emails.returnedToModerationStage.body'), $this->locale);

        return $emailTemplate;
    }

    private function createStageRegressionEmailBuilder(): StageRegressionEmailBuilder
    {
        return new class ($this->emailTemplate) extends StageRegressionEmailBuilder {
            private $testEmailTemplate;

            public function __construct($emailTemplate)
            {
                $this->testEmailTemplate = $emailTemplate;
            }

            protected function getEmailTemplate(string $emailTemplateKey)
            {
                return $this->testEmailTemplate;
            }
        };
    }

    public function testStageRegressionEmailBuilding(): void
    {
        $email = $this->createStageRegressionEmailBuilder()
            ->setSubmission($this->submission)
            ->setContext($this->context)
            ->buildEmailParams()
            ->build();

        $expectedFrom = ['name' => $this->context->getData('contactName'), 'address' => $this->context->getData('contactEmail')];
        $this->assertEquals($expectedFrom, $email->from[0]);

        $expectedTo = [['name' => $this->primaryAuthor->getFullName(), 'address' => $this->primaryAuthor->getEmail()]];
        $this->assertEquals($expectedTo, $email->to);

        $this->assertEquals($this->emailTemplate->getLocalizedData('subject'), $email->subject);
        $this->assertEquals($this->emailTemplate->getLocalizedData('body'), $email->view);
    }

    public function testStageRegressionEmailParams(): void
    {
        $email = $this->createStageRegressionEmailBuilder()
            ->setSubmission($this->submission)
            ->setContext($this->context)
            ->buildEmailParams()
            ->build();

        $request = Application::get()->getRequest();
        $faqUrl = $request->url($this->context->getPath()) . '/faq';

        $this->assertEquals('Doutor Caligari &amp; Cesare', $email->viewData['authorName']);
        $this->assertEquals(__('plugins.generic.scieloModerationStages.stages.formatStage'), $email->viewData['moderationStageName']);
        $this->assertEquals($faqUrl, $email->viewData['faqUrl']);
    }
}
