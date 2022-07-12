function insertAfter(newNode, referenceNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function createStageExhibitorNode(moderationStageName) {
    var node = document.createElement('div');
    node.classList.add('listPanel__itemModerationStage');
    node.textContent = moderationStageName;
    return node;
}

function getSubmissionIdFromDiv(parentDiv) {
    var id = parentDiv.textContent.trim().split(' ')[0];
    return id;
}

function addStageExhibitor() {
    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        const hasExhibitor = subtitle.parentNode.getElementsByClassName('listPanel__itemModerationStage').length > 0;
        if(!hasExhibitor) {
            const submissionId = getSubmissionIdFromDiv(subtitle.parentNode);
            $.get(
                app.moderationStagesHandlerUrl + 'get-submission-moderation-stage',
                {
                    submissionId: submissionId,
                },
                function (result){
                    result = JSON.parse(result);
                    if(result['moderationStageName'] != '') {
                        var exhibitorNode = createStageExhibitorNode(result['moderationStageName']);
                        subtitle.appendChild(exhibitorNode);
                    }
                }
            );
        }
    }
}

function setStageToBeAddedAfterRequestsFinish() {
    var origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
        this.addEventListener('load', function() {
            var url = this.responseURL;
            if(url.search('_submissions') >= 0) {
                setTimeout(addStageExhibitor, 500);
            }
        });
        origOpen.apply(this, arguments);
    };
}

$(document).ready(setStageToBeAddedAfterRequestsFinish);