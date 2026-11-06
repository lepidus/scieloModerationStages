<?php

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\scieloModerationStages\classes\SchemaEditor;

class SchemaEditorTest extends TestCase
{
    private const MODERATION_PROPERTIES = [
        'currentModerationStage',
        'lastModerationStageChange',
        'formatStageEntryDate',
        'contentStageEntryDate',
        'areaStageEntryDate',
    ];

    public function testModerationPropertiesAreReadOnlyInApi(): void
    {
        $schema = (object) ['properties' => new stdClass()];
        $params = [&$schema];

        (new SchemaEditor())->editSubmissionSchema('Schema::get::submission', $params);

        foreach (self::MODERATION_PROPERTIES as $property) {
            $this->assertTrue(
                $schema->properties->{$property}->writeDisabledInApi ?? false,
                "{$property} must not be writable through the REST API"
            );
            $this->assertTrue($schema->properties->{$property}->apiSummary);
        }
    }
}
