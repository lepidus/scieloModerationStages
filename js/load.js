function insertAfter(newNode, referenceNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function createStageExhibitorNode() {
    var node = document.createElement('div');
    node.classList.add('listPanel__itemModerationStage');
    node.textContent = "Bolinha";
    return node;
}

function addStageExhibitor() {
    var submissionSubtitle = document.getElementsByClassName('listPanel__itemSubtitle');
    for (let subtitle of submissionSubtitle) {
        const hasExhibitor = subtitle.parentNode.getElementsByClassName('listPanel__itemModerationStage').length > 0;
        
        if(!hasExhibitor) {
            var exhibitorNode = createStageExhibitorNode();
            subtitle.appendChild(exhibitorNode);
        }
    }
}

function setStageToBeAddedAfterRequestsFinish() {
    var origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function() {
        this.addEventListener('load', function() {
            setTimeout(addStageExhibitor, 500);
        });
        origOpen.apply(this, arguments);
    };
}

$(document).ready(setStageToBeAddedAfterRequestsFinish);