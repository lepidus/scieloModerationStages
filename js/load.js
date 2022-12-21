let exhibitorNodesAll = ['ModerationStage', 'exhibitorsSeparator', 'Responsibles', 'AreaModerators'];
let exhibitorNodesAdmin = ['TimeSubmitted', 'TimeResponsible', 'TimeAreaModerator'];
var userIsAuthor = '1';

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

function createExhibitorsSeparator(submissionId) {
    var node = document.createElement('hr');
    node.classList.add('exhibitorsSeparator');
    node.classList.add('submissionExhibitorSeparator' + '--' + submissionId);
    node.style.display = 'none';
    return node;
}

function addLineBreakAfterExhibitor(exhibitorNode) {
    var br = document.createElement('br');
    insertAfter(br, exhibitorNode);
    exhibitorNode.classList.add('exhibitorWithLineBreak');
}

function updateExhibitorNode(classNamePrefix, text, submissionId) {
    var exhibitorNodes = document.getElementsByClassName(classNamePrefix + '--' + submissionId);
    for(let exhibitorNode of exhibitorNodes) {
        exhibitorNode.textContent = text;
        exhibitorNode.classList.remove('withoutDataYet');
        if(!exhibitorNode.classList.contains('exhibitorWithLineBreak')) {
            addLineBreakAfterExhibitor(exhibitorNode);
        }
    }
}

function updateExhibitorsSeparator(submissionId) {
    var exhibitorsSeparators = document.getElementsByClassName('submissionExhibitorSeparator--' + submissionId);
    for(let separator of exhibitorsSeparators) {
        separator.style.display = 'block';
    }
}

function addRedColorToTimeExhibitor(classNamePrefix, submissionId) {
    var exhibitorNodes = document.getElementsByClassName(classNamePrefix + '--' + submissionId);
    for(let exhibitorNode of exhibitorNodes) {
        exhibitorNode.classList.add('itemTimeRed');
    }
}

function updateExhibitorNodes(response) {
    response = JSON.parse(response);
    const submissionId = response['submissionId'];
    
    for (const exhibitorNodeName of exhibitorNodesAll) {
        if(exhibitorNodeName == 'exhibitorsSeparator') {
            updateExhibitorsSeparator(submissionId);
        }
        else if(response[exhibitorNodeName] != '') {
            updateExhibitorNode('submission'+exhibitorNodeName, response[exhibitorNodeName], submissionId);
        }
    }

    if(userIsAuthor == false) {
        for (const exhibitorNodeName of exhibitorNodesAdmin) {
            if(response[exhibitorNodeName] != '') {
                updateExhibitorNode('submission'+exhibitorNodeName, response[exhibitorNodeName], submissionId);

                if(exhibitorNodeName+'RedFlag' in response) {
                    addRedColorToTimeExhibitor('submission'+exhibitorNodeName, submissionId);
                }
            }
        }
    }
}

function getSubmissionIdFromDiv(parentDiv) {
    var id = parentDiv.textContent.trim().split(' ')[0];
    return id;
}

async function addSubmissionExhibitors() {
    userIsAuthor = await $.get(
        app.moderationStagesHandlerUrl + 'get-user-is-author'
    );

    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        const hasExhibitors = subtitle.parentNode.getElementsByClassName('listPanel__itemModerationStage').length > 0;
        if(!hasExhibitors) {
            const submissionId = getSubmissionIdFromDiv(subtitle.parentNode);
            $.get(
                app.moderationStagesHandlerUrl + 'get-submission-exhibit-data',
                {
                    submissionId: submissionId,
                    userIsAuthor: userIsAuthor
                },
                updateExhibitorNodes
            );

            var previousNode = subtitle;
            var newExhibitorNode = null;
            for (const exhibitorNodeName of exhibitorNodesAll) {
                if(exhibitorNodeName == 'exhibitorsSeparator')
                    newExhibitorNode = createExhibitorsSeparator(submissionId);
                else
                    newExhibitorNode = createExhibitorNode(submissionId, exhibitorNodeName);
                insertAfter(newExhibitorNode, previousNode);
                previousNode = newExhibitorNode;
            }

            if(userIsAuthor == false) {
                newExhibitorNode = createExhibitorsSeparator(submissionId);
                insertAfter(newExhibitorNode, previousNode);
                previousNode = newExhibitorNode;

                for(const exhibitorNodeName of exhibitorNodesAdmin) {
                    newExhibitorNode = createExhibitorNode(submissionId, exhibitorNodeName);
                    insertAfter(newExhibitorNode, previousNode);
                    previousNode = newExhibitorNode;
                }
            }
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