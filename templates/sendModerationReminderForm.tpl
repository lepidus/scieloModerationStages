<script>
    $(function() {ldelim}
        $('#sendModerationReminderForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="sendModerationReminderForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="sendModerationReminder" save=true}">
    <div id="sendModerationReminder">
        {csrf}
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="sendModerationReminderFormNotification"}

        {fbvFormSection id="roleSection" label="plugins.generic.scieloModerationStages.sendModerationReminder.role.title"}
            {fbvElement type="select" id="reminderRole" name="reminderRole" from=$roles required="true" label="plugins.generic.scieloModerationStages.sendModerationReminder.role.description" translate=false size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}

        {fbvFormSection id="responsibleSection" label="plugins.generic.scieloModerationStages.sendModerationReminder.responsible.title"}
            <input type="hidden" id="responsiblesUserGroupId" name="responsiblesUserGroupId" value="{$responsiblesUserGroupId|escape}" />
            {fbvElement type="select" id="responsible" name="responsible" from=$responsibles required="true" label="plugins.generic.scieloModerationStages.sendModerationReminder.responsible.description" translate=false size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}

        {fbvFormSection id="areaModeratorSection" label="plugins.generic.scieloModerationStages.sendModerationReminder.areaModerator.title"}
            <input type="hidden" id="areaModeratorsUserGroupId" name="areaModeratorsUserGroupId" value="{$areaModeratorsUserGroupId|escape}" />
            {fbvElement type="select" id="areaModerator" name="areaModerator" from=$areaModerators required="true" label="plugins.generic.scieloModerationStages.sendModerationReminder.areaModerator.description" translate=false size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}

        {fbvFormSection id="reminderBodySection" label="plugins.generic.scieloModerationStages.sendModerationReminder.reminderBody.title"}
			{fbvElement type="textarea" id="reminderBody" name="reminderBody" label="plugins.generic.scieloModerationStages.sendModerationReminder.reminderBody.description" rich=true value=$reminderBody}
		{/fbvFormSection}

        {fbvFormButtons submitText="plugins.generic.scieloModerationStages.send"}
        <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
    </div>
</form>

{capture assign=getReminderBodyUrl}{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler" op="getReminderBody" escape=false}{/capture}
<script>
    function updateReminderBody(response) {ldelim}
        let reminderBodyTextarea = $('textarea[name=reminderBody]');
        let tinyTextarea = tinyMCE.EditorManager.get(reminderBodyTextarea.attr('id'));

        response = JSON.parse(response);
        tinyTextarea.setContent(response['reminderBody']);
    {rdelim}

    function getReminderBody(userId, userGroupId) {ldelim}
        let selectedRole = $('#reminderRole').val();
        $.get(
            "{$getReminderBodyUrl}",
            {ldelim}
                role: selectedRole,
                user: userId,
                userGroup: userGroupId
            {rdelim},
            updateReminderBody
        );
    {rdelim}

    $(function(){ldelim}
        $('#responsible').change(function () {
            let responsiblesUserGroupId = $('#responsiblesUserGroupId').val();
            let responsibleId = $('#responsible').val();
            getReminderBody(responsibleId, responsiblesUserGroupId);
        });
        $('#areaModerator').change(function () {
            let areaModeratorsUserGroupId = $('#areaModeratorsUserGroupId').val();
            let areaModeratorId = $('#areaModerator').val();
            getReminderBody(areaModeratorId, areaModeratorsUserGroupId);
        });

        $('#reminderRole').change(function () {
            let selectedRole = $('#reminderRole').val();
        
            if (selectedRole === 'preModeration') {
                $('#responsibleSection').show();
                $('#areaModeratorSection').hide();
            } else if (selectedRole === 'areaModeration') {
                $('#responsibleSection').hide();
                $('#areaModeratorSection').show();
            }
        });

        $('#areaModeratorSection').hide();
    {rdelim});
</script>
