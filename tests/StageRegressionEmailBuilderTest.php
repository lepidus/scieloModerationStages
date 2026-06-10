<?php

use PHPUnit\Framework\TestCase;
use APP\core\Application;
use APP\server\Server;
use APP\author\Author;
use APP\submission\Submission;
use APP\publication\Publication;
use PKP\core\PKPPageRouter;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageRegressionEmailBuilder;

class StageRegressionEmailBuilderTest extends TestCase
{
    private $locale = 'en';
    private $context;
    private $primaryAuthor;
    private $submission;

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
        $author->setData('email', 'caligari@gmail.com');
        $author->setData('givenName', 'Doutor', $this->locale);
        $author->setData('familyName', 'Caligari', $this->locale);

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

    public function testStageRegressionEmailBuilding(): void
    {
        $email = (new StageRegressionEmailBuilder())
            ->setSubmission($this->submission)
            ->setContext($this->context)
            ->buildEmailParams()
            ->build();

        $expectedFrom = ['name' => $this->context->getData('contactName'), 'address' => $this->context->getData('contactEmail')];
        $this->assertEquals($expectedFrom, $email->from[0]);

        $expectedTo = [['name' => $this->primaryAuthor->getFullName(), 'address' => $this->primaryAuthor->getEmail()]];
        $this->assertEquals($expectedTo, $email->to);

        $expectedSubject = __('plugins.generic.scieloModerationStages.emails.stageRegression.subject');
        $this->assertEquals($expectedSubject, $email->subject);

        $request = Application::get()->getRequest();
        $faqUrl = $request->url($this->context->getPath()) . '/faq';

        $bodyParams = [
            'authorName' => $this->primaryAuthor->getFullName(),
            'moderationStageName' => __('plugins.generic.scieloModerationStages.stages.formatStage'),
            'faqUrl' => $faqUrl,
        ];
        $expectedBody = __('plugins.generic.scieloModerationStages.emails.stageRegression.body', $bodyParams);
        $this->assertEquals($expectedBody, $email->view);
    }
}
