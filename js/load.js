function insertAfter(newNode, referenceNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function createExhibitorNode(submissionId, type) {
    var node = document.createElement('div');
    node.classList.add('listPanel__item' + type);
    node.classList.add('submission' + type + '--' + submissionId);
    node.classList.add('withoutDataYet');
    return node;
}

function createStageExhibitorNode(submissionId) {
    return createExhibitorNode(submissionId, 'ModerationStage');
}

function createAreaModeratorExhibitorNode(submissionId) {
    return createExhibitorNode(submissionId, 'AreaModerator');
}

function updateExhibitorNodes(response) {
    response = JSON.parse(response);
    const submissionId = response['submissionId'];
    if(response['moderationStageName'] != '') {
        var exhibitorNodes = document.getElementsByClassName('submissionModerationStage--' + submissionId);
        for(let exhibitorNode of exhibitorNodes) {
            exhibitorNode.textContent = response['moderationStageName'];
            exhibitorNode.classList.remove('withoutDataYet');
        }
    }

    if(response['areaModerators'] != '') {
        var exhibitorNodes = document.getElementsByClassName('submissionAreaModerator--' + submissionId);
        for(let exhibitorNode of exhibitorNodes) {
            exhibitorNode.textContent = response['areaModerators'];
            exhibitorNode.classList.remove('withoutDataYet');
        }
    }
}

function getSubmissionIdFromDiv(parentDiv) {
    var id = parentDiv.textContent.trim().split(' ')[0];
    return id;
}

function addSubmissionExhibitors() {
    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        const hasExhibitors = subtitle.parentNode.getElementsByClassName('listPanel__itemModerationStage').length > 0;
        if(!hasExhibitors) {
            const submissionId = getSubmissionIdFromDiv(subtitle.parentNode);
            $.get(
                app.moderationStagesHandlerUrl + 'get-submission-exhibit-data',
                {
                    submissionId: submissionId,
                },
                updateExhibitorNodes
            );

            var stageExhibitorNode = createStageExhibitorNode(submissionId);
            var areaModeratorExhibitorNode = createAreaModeratorExhibitorNode(submissionId);
            insertAfter(stageExhibitorNode, subtitle);
            insertAfter(areaModeratorExhibitorNode, stageExhibitorNode);
        }
    }
}

function setExhibitorsToBeAddedAfterRequestsFinish() {
    var origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
        this.addEventListener('load', function() {
            var url = this.responseURL;
            if(url.search('_submissions') >= 0) {
                setTimeout(addSubmissionExhibitors, 500);
            }
        });
        origOpen.apply(this, arguments);
    };
}

$(document).ready(setExhibitorsToBeAddedAfterRequestsFinish);