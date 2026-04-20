<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use PKP\plugins\Hook;

class SchemaEditor
{
    public function editSubmissionSchema($hookName, $params)
    {
        $schema = &$params[0];
        $newProperties = [
            'currentModerationStage' => 'string',
            'lastModerationStageChange' => 'string',
            'formatStageEntryDate' => 'string',
            'contentStageEntryDate' => 'string',
            'areaStageEntryDate' => 'string'
        ];

        foreach ($newProperties as $property => $type) {
            $schema->properties->{$property} = (object) [
                'type' => $type,
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }

        return Hook::CONTINUE;
    }

    public function editEventLogSchema($hookName, $params)
    {
        $schema = &$params[0];
        $newProperties = [
            'moderationStageName' => 'string',
        ];

        foreach ($newProperties as $property => $type) {
            $schema->properties->{$property} = (object) [
                'type' => $type,
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }

        return Hook::CONTINUE;
    }
}
