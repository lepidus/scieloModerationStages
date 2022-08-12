{fbvFormSection id="checkboxSendNextStageDiv" title="plugins.generic.scieloModerationStages.checkboxTitle" list=true required=true}
    {translate key="plugins.generic.scieloModerationStages.checkboxSendNextStage" currentStage=$currentStage nextStage=$nextStage}
	{fbvElement type="radio" name="sendNextStage" id="checkboxSendNextStageAssignYes" value="1" label="common.yes" required=true checked=false}
	{fbvElement type="radio" name="sendNextStage" id="checkboxSendNextStageAssignNo" value="0" label="common.no" required=true checked=false}
{/fbvFormSection}

<script>

    const fieldset = document.getElementById('notifyFormArea');
    const fieldsetLastChild = fieldset.lastElementChild;
    const checkboxSendNextStageDiv = document.getElementById('checkboxSendNextStageDiv');
    
    fieldset.insertBefore(checkboxSendNextStageDiv, fieldsetLastChild);
</script>
