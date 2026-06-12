{*
  * Copyright (c) 2022 - 2024 Lepidus Tecnologia
  * Copyright (c) 2022 - 2024 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/scieloModerationStages/styles/moderationStageStyleSheet.css">
{capture assign=updateStageEntryDatesUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler" op="updateSubmissionStageData" escape=false}{/capture}

<div class="pkp_form" id="moderationStageEntriesForm">
    {if $userIsAuthor}
        <div class="currentStageInfo">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.currentStageInfo"}</label>
            {translate key="{$currentStage}.workflowDescription" faqUrl=$faqUrl}
        </div>
    {/if}

    {if $formatStageEntryDate}
        <div id="formatStageEntryDateDiv" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.formatStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='formatStageEntryDate' name='formatStageEntryDate' value="{$formatStageEntryDate}" {if $userIsAuthor}disabled{/if}/>
        </div>
    {/if}
    {if $contentStageEntryDate}
        <div id="contentStageEntryDateDiv" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.contentStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='contentStageEntryDate' name='contentStageEntryDate' value="{$contentStageEntryDate}"{if $userIsAuthor}disabled{/if}/>
        </div>
    {/if}
    {if $areaStageEntryDate}
        <div id="areaStageEntryDateDiv" class="stageDateField">
            <label class="label">{translate key="plugins.generic.scieloModerationStages.stages.areaStage"}</label>
            <label class="description">{translate key="plugins.generic.scieloModerationStages.menuDates.fieldDescription"}</label>
            <input type="date" id='areaStageEntryDate' name='areaStageEntryDate' value="{$areaStageEntryDate}"{if $userIsAuthor}disabled{/if}/>
        </div>
    {/if}

    {if not $userIsAuthor}
        {if $canAdvanceStage or $canRegressStage}
            <div id="stageChangeDiv">
                {capture assign=currentStageName}{translate key=$currentStage}{/capture}
                <label class="label">{translate key="plugins.generic.scieloModerationStages.stageChangeField"}</label>
                <label class="description">
                    {translate key="plugins.generic.scieloModerationStages.stageChange.description" currentStage=$currentStageName}
                </label>
                {if $canAdvanceStage}
                    <input type="radio" id="stageChangeActionAdvance" name="stageChangeAction" value="advance"/>
                    {translate key="plugins.generic.scieloModerationStages.stageChange.advance" nextStage=$nextStage}<br>
                {/if}
                {if $canRegressStage}
                    <input type="radio" id="stageChangeActionRegress" name="stageChangeAction" value="regress"/>
                    {translate key="plugins.generic.scieloModerationStages.stageChange.regress" previousStage=$previousStage}<br>
                {/if}
                <input type="radio" id="stageChangeActionStay" name="stageChangeAction" value="stay" checked="checked"/>
                {translate key="plugins.generic.scieloModerationStages.stageChange.stay"}<br>
            </div>
        {/if}

        <div class="formButtons">
            <button id="moderationStageSubmit" type="button" class="pkp_button submitFormButton">{translate key="common.save"}</button>
        </div>
    {/if}
</div>

{if not $userIsAuthor}
    <script>
        function updatedStageDatesSuccess(){ldelim}
            alert("{translate key="form.saved"}");
        {rdelim}

        async function requestUpdateStageEntryDates(e){ldelim}
            $.post(
                "{$updateStageEntryDatesUrl}",
                {ldelim}
                    submissionId: {$submissionId},
                    {if $canAdvanceStage or $canRegressStage}
                        sendNextStage: $('input[name=stageChangeAction]:checked').val() == 'advance' ? 1 : 0,
                        sendPreviousStage: $('input[name=stageChangeAction]:checked').val() == 'regress' ? 1 : 0,
                    {/if}
                    {if $formatStageEntryDate} formatStageEntryDate: $('#formatStageEntryDate').val(), {/if}
                    {if $contentStageEntryDate} contentStageEntryDate: $('#contentStageEntryDate').val(), {/if}
                    {if $areaStageEntryDate} areaStageEntryDate: $('#areaStageEntryDate').val(), {/if}
                {rdelim},
                updatedStageDatesSuccess()
            );
        {rdelim}

        $(function(){ldelim}
            $('#moderationStageSubmit').click(requestUpdateStageEntryDates);
        {rdelim});
    </script>
{/if}